<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class BookingModel extends Model
{
    protected $table = 'bookings';
    protected $primary_key = 'id';

    const SELECT = 'SELECT b.*, t.name AS type_name, r.room_no FROM bookings b JOIN room_types t ON t.id = b.room_type_id LEFT JOIN rooms r ON r.id = b.room_id ';

    public function expire_holds(): void
    {
        q("UPDATE bookings SET status = 'expired' WHERE status = 'pending_payment' AND hold_expires_at < ?", [now_str()]);
    }

    /* ---- turn the form values into a start / end time (returns null if the values are not valid) ---- */
    public function window(string $stay, string $date, string $time, int $hours): ?array
    {
        $d = DateTime::createFromFormat('!Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) return null;
        if ($stay === 'hourly') {
            $t = DateTime::createFromFormat('!H:i', $time);
            if (!$t || $t->format('H:i') !== $time || !in_array($hours, [3, 6, 12], true)) return null;
            $start = new DateTime($date . ' ' . $time);
            $end = (clone $start)->modify('+' . $hours . ' hours');
        } elseif ($stay === 'overnight') {
            $hours = 0;
            $start = new DateTime($date . ' ' . setting('overnight_start', '20:00'));
            $end = (new DateTime($date . ' ' . setting('overnight_end', '12:00')))->modify('+1 day');
        } else return null;
        $now = new DateTime();
        if ($end <= $now || $start < (clone $now)->modify('-15 minutes') && $stay === 'hourly') return null;
        if ($start > (clone $now)->modify('+60 days')) return null;
        return ['start' => $start->format('Y-m-d H:i:s'), 'end' => $end->format('Y-m-d H:i:s'), 'hours' => $hours];
    }

    public function price(array $type, string $stay, int $hours): float
    {
        if ($stay === 'overnight') return (float) $type['price_overnight'];
        return (float) ($hours === 3 ? $type['price_3h'] : ($hours === 6 ? $type['price_6h'] : $type['price_12h']));
    }

    /* ---- which rooms of this type are free for the whole window (+ cleaning buffer)? ---- */
    public function free_rooms(int $typeId, string $start, string $end, int $bufferMin, int $ignoreBookingId = 0, bool $lock = false): array
    {
        $rooms = q_all("SELECT id, room_no FROM rooms WHERE room_type_id = ? AND status = 'active' ORDER BY room_no" . ($lock ? lock_suffix() : ''), [$typeId]);
        $endPlus = date('Y-m-d H:i:s', strtotime($end) + $bufferMin * 60);
        $startMinus = date('Y-m-d H:i:s', strtotime($start) - $bufferMin * 60);
        $free = [];
        foreach ($rooms as $r) {
            $busy = (int) q_val(
                "SELECT COUNT(*) FROM bookings WHERE room_id = ? AND id <> ? AND start_at < ? AND end_at > ?
                 AND (status IN ('confirmed','checked_in') OR (status = 'pending_payment' AND hold_expires_at > ?))",
                [$r['id'], $ignoreBookingId, $endPlus, $startMinus, now_str()]
            );
            if ($busy === 0) $free[] = $r;
        }
        return $free;
    }

    /* how many rooms are free for each room type */
    public function availability(string $start, string $end): array
    {
        $this->expire_holds();
        $out = [];
        foreach (q_all('SELECT id, cleaning_buffer_min FROM room_types WHERE is_active = 1') as $t) {
            $out[(int) $t['id']] = count($this->free_rooms((int) $t['id'], $start, $end, (int) $t['cleaning_buffer_min']));
        }
        return $out;
    }

    /* ---- create the booking and HOLD a room (this is the automatic room assignment) ---- */
    public function create(?array $user, array $type, array $w, array $f, string $stay, string $source = 'online', string $status = 'pending_payment', string $method = ''): array
    {
        db()->transaction();
        $this->expire_holds();
        $free = $this->free_rooms((int) $type['id'], $w['start'], $w['end'], (int) $type['cleaning_buffer_min'], 0, true);   // locks the rooms of this type
        if (!$free) { db()->roll_back(); return ['ok' => false, 'error' => 'Sorry, that room type was just taken for your time. Please choose another time.']; }
        if ($user) {
            $pending = (int) q_val("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status = 'pending_payment' AND hold_expires_at > ?", [$user['id'], now_str()]);
            if ($pending >= 1000) { db()->roll_back(); return ['ok' => false, 'error' => 'You already have 3 unpaid bookings. Please pay or wait for them to expire.']; }
        }
        $rate = $this->price($type, $stay, (int) $w['hours']);
        $fee = $source === 'walkin' ? 0.0 : (float) setting('booking_fee', '30');
        do { $ref = booking_ref(); } while (q_val('SELECT COUNT(*) FROM bookings WHERE booking_ref = ?', [$ref]) > 0);
        $id = insert_row('bookings', [
            'booking_ref' => $ref, 'user_id' => $user['id'] ?? null, 'room_type_id' => $type['id'], 'room_id' => $free[0]['id'],
            'stay_type' => $stay, 'hours' => $w['hours'], 'start_at' => $w['start'], 'end_at' => $w['end'], 'guests' => $f['guests'],
            'guest_name' => $f['name'], 'guest_mobile' => $f['mobile'], 'guest_email' => $f['email'], 'id_type' => $f['id_type'], 'id_last4' => $f['id_last4'],
            'room_rate' => $rate, 'booking_fee' => $fee, 'total' => $rate + $fee, 'status' => $status, 'pay_method' => $method, 'source' => $source,
            'hold_expires_at' => date('Y-m-d H:i:s', time() + (int) setting('hold_minutes', '10') * 60), 'created_at' => now_str(),
        ]);
        db()->commit();
        return ['ok' => true, 'ref' => $ref, 'id' => $id];
    }

    public function find(string $ref): ?array { return q_one(self::SELECT . 'WHERE b.booking_ref = ?', [$ref]); }
    public function find_mine(string $ref, int $userId): ?array { return q_one(self::SELECT . 'WHERE b.booking_ref = ? AND b.user_id = ?', [$ref, $userId]); }   // a guest can NEVER open someone else's booking
    public function mine(int $userId, string $tab): array
    {
        $now = now_str();
        $where = ['upcoming' => "b.status IN ('confirmed','pending_payment') AND b.end_at > ?", 'past' => "b.status IN ('checked_in','checked_out') OR (b.status = 'confirmed' AND b.end_at <= ?)", 'cancelled' => "b.status IN ('cancelled','expired') AND ? IS NOT NULL"];
        $cond = $where[$tab] ?? $where['upcoming'];
        return q_all(self::SELECT . 'WHERE b.user_id = ? AND (' . $cond . ') ORDER BY b.start_at DESC LIMIT 100', [$userId, $now]);
    }

    /* ---- payment finished: confirm the booking (only if the hold is still valid) ---- */
    public function mark_paid(string $ref, string $method, string $provider, string $providerRef): ?array
    {
        db()->transaction();
        $b = q_one('SELECT * FROM bookings WHERE booking_ref = ?' . lock_suffix(), [$ref]);
        if (!$b || $b['status'] !== 'pending_payment' || $b['hold_expires_at'] < now_str()) { db()->roll_back(); return null; }
        insert_row('payments', ['booking_id' => $b['id'], 'txn_ref' => 'TXN-' . strtoupper(bin2hex(random_bytes(5))), 'method' => $method, 'provider' => $provider, 'provider_ref' => $providerRef,
            'amount' => $b['total'], 'status' => 'paid', 'created_at' => now_str(), 'paid_at' => now_str()]);
        q("UPDATE bookings SET status = 'confirmed', hold_expires_at = NULL WHERE id = ?", [$b['id']]);
        db()->commit();
        return $this->find($ref);
    }

    /* ---- cancel (guests: only before the free-cancellation deadline; staff: any time) ---- */
    public function can_cancel(array $b, bool $staff = false): bool
    {
        if (!in_array($b['status'], ['confirmed', 'pending_payment'], true)) return false;
        if ($staff || $b['status'] === 'pending_payment') return true;
        return strtotime($b['start_at']) - time() >= (int) setting('cancel_hours', '2') * 3600;
    }
    public function cancel(array $b): void
    {
        db()->transaction();
        q("UPDATE bookings SET status = 'cancelled', cancelled_at = ? WHERE id = ?", [now_str(), $b['id']]);
        q("UPDATE payments SET status = 'refunded', refunded_at = ? WHERE booking_id = ? AND status = 'paid'", [now_str(), $b['id']]);
        db()->commit();
    }

    /* ---- guest changes the date / time (same room type, same length, so the price stays the same) ---- */
    public function reschedule(array $b, array $w): array
    {
        $type = q_one('SELECT cleaning_buffer_min FROM room_types WHERE id = ?', [(int) $b['room_type_id']]);
        db()->transaction();
        $free = $this->free_rooms((int) $b['room_type_id'], $w['start'], $w['end'], (int) $type['cleaning_buffer_min'], (int) $b['id'], true);
        if (!$free) { db()->roll_back(); return ['ok' => false, 'error' => 'That time is not available for this room type. Please try another time.']; }
        $room = (int) $free[0]['id'];
        foreach ($free as $r) if ((int) $r['id'] === (int) $b['room_id']) $room = (int) $r['id'];      // keep the same room if it is free
        q('UPDATE bookings SET start_at = ?, end_at = ?, room_id = ? WHERE id = ?', [$w['start'], $w['end'], $room, $b['id']]);
        db()->commit();
        return ['ok' => true];
    }

    /* ---- staff actions ---- */
    public function check_in(int $id): bool { return q("UPDATE bookings SET status = 'checked_in', checked_in_at = ? WHERE id = ? AND status = 'confirmed'", [now_str(), $id])->rowCount() > 0; }
    public function check_out(int $id): bool { return q("UPDATE bookings SET status = 'checked_out', checked_out_at = ? WHERE id = ? AND status = 'checked_in'", [now_str(), $id])->rowCount() > 0; }
    public function reassign(array $b, int $roomId): bool
    {
        $room = q_one("SELECT id, room_type_id FROM rooms WHERE id = ? AND status = 'active'", [$roomId]);
        if (!$room || (int) $room['room_type_id'] !== (int) $b['room_type_id']) return false;
        $type = q_one('SELECT cleaning_buffer_min FROM room_types WHERE id = ?', [(int) $b['room_type_id']]);
        db()->transaction();
        $ok = false;
        foreach ($this->free_rooms((int) $b['room_type_id'], $b['start_at'], $b['end_at'], (int) $type['cleaning_buffer_min'], (int) $b['id'], true) as $r) {
            if ((int) $r['id'] === $roomId) { q('UPDATE bookings SET room_id = ? WHERE id = ?', [$roomId, $b['id']]); $ok = true; break; }
        }
        $ok ? db()->commit() : db()->roll_back();
        return $ok;
    }
}
