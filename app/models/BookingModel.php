<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| Bookings and automatic room assignment. Table: bookings.
| Create / read / update / delete, plus the rules: price, free rooms, holds, check-in, cancel, reschedule.
| Money lives in PaymentModel, rooms in RoomModel, prices in RoomTypeModel.
*/
class BookingModel extends Model
{
    protected $table = 'bookings';

    /* Columns shown on every booking page (plus the room type name and the room number) */
    const COLUMNS = 'b.id, b.booking_ref, b.user_id, b.room_type_id, b.room_id, b.stay_type, b.hours, b.start_at, b.end_at, b.guests, '
        . 'b.guest_name, b.guest_mobile, b.guest_email, b.id_type, b.id_last4, b.room_rate, b.booking_fee, b.total, b.status, '
        . 'b.pay_method, b.pay_ref, b.source, b.hold_expires_at, b.created_at, b.cancelled_at, b.checked_in_at, b.checked_out_at, '
        . 'b.hotel_id, b.gcal_event_id, t.name AS type_name, r.room_no, h.name AS hotel_name, h.address AS hotel_address, h.lat AS hotel_lat, h.lng AS hotel_lng, h.phone AS hotel_phone';

    const STATUSES = ['pending_payment', 'confirmed', 'checked_in', 'checked_out', 'cancelled', 'expired'];

    private function models(string $name)
    {
        return $this->call->model($name);
    }

    /* Keep the hotel's Google Calendar right after a booking changed (does nothing when Google Calendar is not connected; never stops the booking) */
    private function calendar(int $id): void
    {
        $this->models('CalendarModel')->sync($id);
    }

    /* The base query: a booking with its room type and room number. Add ->where(...) and ->get() / ->get_all(). */
    private function detailed()
    {
        $this->db->table('bookings b')
            ->select(self::COLUMNS)
            ->join('room_types t', 't.id = b.room_type_id')
            ->left_join('rooms r', 'r.id = b.room_id')
            ->left_join('hotels h', 'h.id = b.hotel_id');
        return \Hotelgo\Support\Scope::limit($this->db, 'b.hotel_id');            // hotel staff only see their own hotel's bookings
    }

    /* The query builder has no "lock these rows" command, so these two small queries are written by hand. */
    private function for_update(): string
    {
        return ((database_config()['main']['driver'] ?? '') ?: 'mysql') === 'mysql' ? ' FOR UPDATE' : '';
    }
    private function lock_rooms(int $typeId): array
    {
        return $this->db->raw("SELECT id, room_no FROM rooms WHERE room_type_id = ? AND status = 'active' ORDER BY room_no" . $this->for_update(), [$typeId])->fetchAll(PDO::FETCH_ASSOC);
    }
    private function lock_booking(string $ref): ?array
    {
        return $this->db->raw('SELECT * FROM bookings WHERE booking_ref = ?' . $this->for_update(), [$ref])->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /* ======================================================== rules ======================================================== */

    /* Holds that were not paid in time stop blocking the room */
    public function expire_holds(): void
    {
        if (config_item('payment_provider') === 'paymongo') {            // a guest may have paid just before the hold ended: ask PayMongo first, do not expire a paid booking
            $late = $this->db->table($this->table)->where('status', 'pending_payment')->where('hold_expires_at', '<', now_str())->where('pay_ref', '<>', '')->limit(5)->get_all();
            foreach ($late as $b) {
                $this->models('PaymentModel')->settle($b);
            }
        }
        $this->db->table($this->table)->where('status', 'pending_payment')->where('hold_expires_at', '<', now_str())->update(['status' => 'expired']);
    }

    /* Turn the form values into a start / end time (returns null if the values are not valid) */
    public function window(string $stay, string $date, string $time, int $hours): ?array
    {
        $d = DateTime::createFromFormat('!Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) return null;
        if ($stay === 'hourly') {
            $t = DateTime::createFromFormat('!H:i', $time);
            if (!$t || $t->format('H:i') !== $time || !in_array($hours, [3, 6, 12], true)) return null;
            $start = new DateTime($date . ' ' . $time);
            $end   = (clone $start)->modify('+' . $hours . ' hours');
        } elseif ($stay === 'overnight') {
            $hours = 0;
            $start = new DateTime($date . ' ' . setting('overnight_start', '20:00'));
            $end   = (new DateTime($date . ' ' . setting('overnight_end', '12:00')))->modify('+1 day');
        } else return null;
        $now = new DateTime();
        if ($end <= $now || ($start < (clone $now)->modify('-15 minutes') && $stay === 'hourly')) return null;
        if ($start > (clone $now)->modify('+60 days')) return null;
        return ['start' => $start->format('Y-m-d H:i:s'), 'end' => $end->format('Y-m-d H:i:s'), 'hours' => $hours];
    }

    public function price(array $type, string $stay, int $hours): float
    {
        if ($stay === 'overnight') return (float) $type['price_overnight'];
        return (float) ($hours === 3 ? $type['price_3h'] : ($hours === 6 ? $type['price_6h'] : $type['price_12h']));
    }

    /* Which rooms of this type are free for the whole window (+ cleaning buffer)? */
    public function free_rooms(int $typeId, string $start, string $end, int $bufferMin, int $ignoreBookingId = 0, bool $lock = false): array
    {
        $rooms = $lock ? $this->lock_rooms($typeId) : $this->models('RoomModel')->active_in_type($typeId);
        if (!$rooms) return [];
        $ids        = array_column($rooms, 'id');
        $endPlus    = date('Y-m-d H:i:s', strtotime($end) + $bufferMin * 60);
        $startMinus = date('Y-m-d H:i:s', strtotime($start) - $bufferMin * 60);

        $busy = [];
        $overlapping = function () use ($ids, $endPlus, $startMinus, $ignoreBookingId) {
            $this->db->table($this->table)->select('room_id')->in('room_id', $ids)
                ->where('start_at', '<', $endPlus)->where('end_at', '>', $startMinus);
            if ($ignoreBookingId > 0) {
                $this->db->where('id', '<>', $ignoreBookingId);
            }
            return $this->db;
        };
        foreach ($overlapping()->in('status', ['confirmed', 'checked_in'])->get_all() as $b) {          // paid bookings
            $busy[(int) $b['room_id']] = true;
        }
        foreach ($overlapping()->where('status', 'pending_payment')->where('hold_expires_at', '>', now_str())->get_all() as $b) {   // rooms held while paying
            $busy[(int) $b['room_id']] = true;
        }
        return array_values(array_filter($rooms, fn($r) => !isset($busy[(int) $r['id']])));
    }

    /* How many rooms are free for each room type */
    public function availability(string $start, string $end, int $hotelId = 0, string $area = ''): array
    {
        $this->expire_holds();
        $out = [];
        foreach ($this->models('RoomTypeModel')->types(true, $hotelId, $area) as $t) {
            $out[(int) $t['id']] = count($this->free_rooms((int) $t['id'], $start, $end, (int) $t['cleaning_buffer_min']));
        }
        return $out;
    }

    /* ================================================= create / read / update / delete ================================================= */

    /* Create the booking and HOLD a room (this is the automatic room assignment) */
    public function create(?array $user, array $type, array $w, array $f, string $stay, string $source = 'online', string $status = 'pending_payment', string $method = ''): array
    {
        $this->db->transaction();
        $this->expire_holds();
        $free = $this->free_rooms((int) $type['id'], $w['start'], $w['end'], (int) $type['cleaning_buffer_min'], 0, true);   // locks the rooms of this type
        if (!$free) {
            $this->db->roll_back();
            return ['ok' => false, 'error' => 'Sorry, that room type was just taken for your time. Please choose another.'];
        }
        if ($user) {
            $pending = $this->db->table($this->table)->where('user_id', $user['id'])->where('status', 'pending_payment')->where('hold_expires_at', '>', now_str())->count();
            $max     = (int) config_item('hotelgo_max_unpaid_bookings');                  // 1000 = no real limit (demo). Use 3 on a real site.
            if ($pending >= $max) {
                $this->db->roll_back();
                return ['ok' => false, 'error' => 'You already have ' . $max . ' unpaid bookings. Please pay or wait for them to expire.'];
            }
        }
        $rate = $this->price($type, $stay, (int) $w['hours']);
        $fee  = $source === 'walkin' ? 0.0 : (float) setting('booking_fee', '30');
        do {
            $ref = booking_ref();
        } while ($this->db->table($this->table)->where('booking_ref', $ref)->count() > 0);
        $id = (int) $this->insert([
            'booking_ref' => $ref, 'user_id' => $user['id'] ?? null, 'hotel_id' => $type['hotel_id'] ?? null, 'room_type_id' => $type['id'], 'room_id' => $free[0]['id'],
            'stay_type' => $stay, 'hours' => $w['hours'], 'start_at' => $w['start'], 'end_at' => $w['end'], 'guests' => $f['guests'],
            'guest_name' => $f['name'], 'guest_mobile' => $f['mobile'], 'guest_email' => $f['email'], 'id_type' => $f['id_type'], 'id_last4' => $f['id_last4'],
            'room_rate' => $rate, 'booking_fee' => $fee, 'total' => $rate + $fee, 'status' => $status, 'pay_method' => $method, 'source' => $source,
            'hold_expires_at' => date('Y-m-d H:i:s', time() + (int) setting('hold_minutes', '10') * 60), 'created_at' => now_str(),
        ]);
        $this->db->commit();
        return ['ok' => true, 'ref' => $ref, 'id' => $id];
    }

    /* A guest standing at the front desk pays cash: the booking is confirmed straight away. */
    public function walkin(array $type, array $w, array $f, string $stay, bool $online = false): array
    {
        if ($online) {                                                   // pays on the phone (wallet / link): the room is held until PayMongo says paid
            $res = $this->create(null, $type, $w, $f, $stay, 'walkin', 'pending_payment', 'ewallet');
            return $res['ok'] ? $res + ['booking' => $this->by_ref($res['ref'])] : $res;
        }
        $res = $this->create(null, $type, $w, $f, $stay, 'walkin', 'confirmed', 'cash');
        if (!$res['ok']) return $res;
        $this->db->table($this->table)->where('id', $res['id'])->update(['hold_expires_at' => null]);
        $b = $this->by_ref($res['ref']);
        $this->models('PaymentModel')->add_cash($b);
        $this->calendar((int) $res['id']);
        return $res + ['booking' => $b];
    }

    public function by_ref(string $ref): ?array
    {
        return $this->detailed()->where('b.booking_ref', $ref)->get() ?: null;
    }
    public function by_pay_ref(string $payRef): ?array
    {
        return $this->detailed()->where('b.pay_ref', $payRef)->get() ?: null;
    }
    /* A guest can NEVER open someone else's booking: the owner is part of the query */
    public function find_mine(string $ref, int $userId): ?array
    {
        return $this->detailed()->where('b.booking_ref', $ref)->where('b.user_id', $userId)->get() ?: null;
    }

    public function for_user(int $userId, int $limit = 50): array
    {
        return $this->detailed()->where('b.user_id', $userId)->order_by('b.start_at', 'DESC')->limit($limit)->get_all();
    }

    /* A guest's own bookings: tab upcoming / past / cancelled */
    public function mine(int $userId, string $tab): array
    {
        $now = now_str();
        if ($tab === 'past') {
            $rows = array_merge(
                $this->detailed()->where('b.user_id', $userId)->in('b.status', ['checked_in', 'checked_out'])->get_all(),
                $this->detailed()->where('b.user_id', $userId)->where('b.status', 'confirmed')->where('b.end_at', '<=', $now)->get_all()
            );
            usort($rows, fn($a, $b) => strcmp($b['start_at'], $a['start_at']));
            return array_slice($rows, 0, 100);
        }
        $this->detailed()->where('b.user_id', $userId);
        if ($tab === 'cancelled') {
            $this->db->in('b.status', ['cancelled', 'expired']);
        } else {
            $this->db->in('b.status', ['confirmed', 'pending_payment'])->where('b.end_at', '>', $now);
        }
        return $this->db->order_by('b.start_at', 'DESC')->limit(100)->get_all();
    }

    /* Front desk list with filter and search */
    public function search(string $status = '', string $search = '', int $limit = 200, array $f = []): array
    {
        $this->detailed();
        if ($status !== '') {
            $this->db->where('b.status', $status);
        }
        if (in_array($f['stay'] ?? '', ['hourly', 'overnight'], true)) {
            $this->db->where('b.stay_type', $f['stay']);
        }
        if (!empty($f['hotel_id'])) {
            $this->db->where('b.hotel_id', (int) $f['hotel_id']);
        }
        if (in_array($f['day'] ?? '', ['today', 'tomorrow'], true) && $search === '') {      // bookings that overlap that calendar day
            $d = date('Y-m-d', strtotime($f['day'] === 'tomorrow' ? '+1 day' : 'today'));
            $this->db->where('b.start_at', '<', date('Y-m-d', strtotime($d . ' +1 day')) . ' 00:00:00')->where('b.end_at', '>', $d . ' 00:00:00');
        }
        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $ref  = strtoupper($search);
            $this->db->grouped(function ($db) use ($ref, $like) {
                $db->where('b.booking_ref', $ref)->or_like('b.guest_name', $like)->or_like('b.guest_mobile', $like)->or_like('r.room_no', $like);
            });
        }
        return $this->db->order_by('b.start_at', 'DESC')->limit($limit)->get_all();
    }
    /* Everything for a report between two dates */
    public function starting_between(string $from, string $to): array
    {
        return $this->detailed()->where('b.start_at', '>=', $from)->where('b.start_at', '<', $to)->order_by('b.start_at', 'ASC')->get_all();
    }

    /* Dashboard numbers */
    public function checkins_soon(): int
    {
        return (int) \Hotelgo\Support\Scope::limit($this->db->table($this->table), 'hotel_id')->where('status', 'confirmed')->between('start_at', now_str(), date('Y-m-d H:i:s', time() + 7200))->count();
    }
    public function upcoming(int $limit = 8): array
    {
        return $this->detailed()->where('b.status', 'confirmed')
            ->between('b.start_at', date('Y-m-d H:i:s', time() - 1800), date('Y-m-d H:i:s', time() + 6 * 3600))
            ->order_by('b.start_at', 'ASC')->limit($limit)->get_all();
    }

    /* Update the guest details of a booking (the staff can fix a typo) */
    public function update_guest(int $id, array $data): void
    {
        $row = array_intersect_key($data, array_flip(['guest_name', 'guest_mobile', 'guest_email', 'guests']));
        if ($row) {
            $this->update($id, $row);
            $this->calendar($id);
        }
    }
    public function set_pay_ref(int $id, string $payRef): void
    {
        $this->update($id, ['pay_ref' => $payRef]);
    }

    /* Delete: only a booking that never took money (cancelled or expired, no payment). Returns [ok, reason]. */
    public function remove(string $ref): array
    {
        $b = $this->by_ref($ref);
        if (!$b) return [false, 'Booking not found.'];
        if (!in_array($b['status'], ['cancelled', 'expired'], true)) {
            return [false, 'Only cancelled or expired bookings can be deleted. Cancel the booking first.'];
        }
        if ($this->models('PaymentModel')->count_for((int) $b['id']) > 0) {
            return [false, 'This booking has a payment record, so it must stay in the history.'];
        }
        $this->db->table('notifications')->where('booking_id', $b['id'])->delete();
        $this->delete((int) $b['id']);
        return [true, 'Booking deleted.'];
    }

    /* ============================================== payment finished ============================================== */
    /* Confirms the booking. Returns ['status' => confirmed | confirmed_late | already | duplicate | refund_needed | failed, 'booking' => ...] */
    public function mark_paid(string $ref, string $method, string $provider, string $providerRef, bool $allowLate = false): array
    {
        $pay = $this->models('PaymentModel');
        $this->db->transaction();
        $b = $this->lock_booking($ref);
        if (!$b) { $this->db->roll_back(); return ['status' => 'failed', 'booking' => null]; }
        if (in_array($b['status'], ['confirmed', 'checked_in', 'checked_out'], true)) {
            // paid before. A second, different payment is kept as an extra PAID payment so the admin can refund it.
            if ($providerRef !== '' && !$pay->provider_ref_exists($provider, $providerRef)) {
                $pay->add_paid($b, $method, $provider, $providerRef);
                $this->db->commit();
                return ['status' => 'duplicate', 'booking' => $this->by_ref($ref)];
            }
            $this->db->roll_back();
            return ['status' => 'already', 'booking' => $this->by_ref($ref)];
        }
        if ($b['status'] === 'pending_payment' && $b['hold_expires_at'] >= now_str()) {
            $pay->add_paid($b, $method, $provider, $providerRef);
            $this->db->table($this->table)->where('id', $b['id'])->update(['status' => 'confirmed', 'hold_expires_at' => null, 'pay_method' => $method]);
            $this->db->commit();
            $this->calendar((int) $b['id']);
            return ['status' => 'confirmed', 'booking' => $this->by_ref($ref)];
        }
        if (!$allowLate) { $this->db->roll_back(); return ['status' => 'failed', 'booking' => $this->by_ref($ref)]; }
        // LATE payment (guest paid after the 10-minute hold ended): try to still give the room, otherwise keep the money record for a refund
        if (in_array($b['status'], ['pending_payment', 'expired'], true) && $b['end_at'] > now_str()) {
            $type = $this->models('RoomTypeModel')->type((int) $b['room_type_id']);
            $free = $this->free_rooms((int) $b['room_type_id'], $b['start_at'], $b['end_at'], (int) $type['cleaning_buffer_min'], (int) $b['id'], true);
            if ($free) {
                $room = (int) $free[0]['id'];
                foreach ($free as $r) if ((int) $r['id'] === (int) $b['room_id']) $room = (int) $r['id'];
                $pay->add_paid($b, $method, $provider, $providerRef);
                $this->db->table($this->table)->where('id', $b['id'])->update(['status' => 'confirmed', 'room_id' => $room, 'hold_expires_at' => null, 'pay_method' => $method]);
                $this->db->commit();
                $this->calendar((int) $b['id']);
                return ['status' => 'confirmed_late', 'booking' => $this->by_ref($ref)];
            }
        }
        $pay->add_paid($b, $method, $provider, $providerRef);
        $this->db->table($this->table)->where('id', $b['id'])->update(['status' => 'cancelled', 'cancelled_at' => now_str(), 'hold_expires_at' => null]);
        $this->db->commit();
        return ['status' => 'refund_needed', 'booking' => $this->by_ref($ref)];
    }

    /* ================================================ cancel / change ================================================ */

    /* Guests may cancel only before the free-cancellation deadline; staff any time */
    public function can_cancel(array $b, bool $staff = false): bool
    {
        if (!in_array($b['status'], ['confirmed', 'pending_payment'], true)) return false;
        if ($staff || $b['status'] === 'pending_payment') return true;
        return strtotime($b['start_at']) - time() >= (int) setting('cancel_hours', '2') * 3600;
    }
    public function cancel(array $b): void
    {
        $this->db->transaction();
        $this->db->table($this->table)->where('id', $b['id'])->update(['status' => 'cancelled', 'cancelled_at' => now_str()]);
        $this->db->commit();          // the money side is handled by PaymentModel::refund_booking()
        $this->calendar((int) $b['id']);
    }

    /* The guest changes the date / time (same room type, same length, so the price stays the same) */
    public function reschedule(array $b, array $w): array
    {
        $type = $this->models('RoomTypeModel')->type((int) $b['room_type_id']);
        $this->db->transaction();
        $free = $this->free_rooms((int) $b['room_type_id'], $w['start'], $w['end'], (int) $type['cleaning_buffer_min'], (int) $b['id'], true);
        if (!$free) { $this->db->roll_back(); return ['ok' => false, 'error' => 'That time is not available for this room type. Please try another time.']; }
        $room = (int) $free[0]['id'];
        foreach ($free as $r) if ((int) $r['id'] === (int) $b['room_id']) $room = (int) $r['id'];   // keep the same room if it is free
        $this->db->table($this->table)->where('id', $b['id'])->update(['start_at' => $w['start'], 'end_at' => $w['end'], 'room_id' => $room]);
        $this->db->commit();
        $this->calendar((int) $b['id']);
        return ['ok' => true];
    }

    /* ================================================ staff actions ================================================ */
    /* One front-desk action on a booking (used by the staff pages AND the API, so both behave the same).
       $do: checkin | checkout | cancel | resend | reassign.  Returns [level ok|warn|bad, message]. */
    public function staff_action(array $b, string $do, int $roomId = 0): array
    {
        switch ($do) {
            case 'checkin':
                return $this->check_in((int) $b['id']) ? ['ok', 'Guest checked in.'] : ['bad', 'Only confirmed bookings can be checked in.'];
            case 'checkout':
                return $this->check_out((int) $b['id']) ? ['ok', 'Guest checked out. Room is now being cleaned.'] : ['bad', 'Only checked-in guests can be checked out.'];
            case 'cancel':
                if (!$this->can_cancel($b, true)) {
                    return ['bad', 'This booking cannot be cancelled.'];
                }
                $this->cancel($b);
                $refunded = $this->models('PaymentModel')->refund_booking($b);
                $this->models('NotificationModel')->booking_cancelled($b);
                return $refunded ? ['ok', 'Booking cancelled and payment refunded.'] : ['warn', 'Booking cancelled. The refund is still pending: open Payments to retry it.'];
            case 'resend':
                if (!in_array($b['status'], ['confirmed', 'checked_in'], true)) {
                    return ['bad', 'Nothing to resend for this status.'];
                }
                $this->models('NotificationModel')->booking_confirmed($b);
                return ['ok', 'Confirmation sent again.'];
            case 'reassign':
                $ok = in_array($b['status'], ['confirmed', 'pending_payment'], true) && $this->reassign($b, $roomId);
                return $ok ? ['ok', 'Room changed.'] : ['bad', 'That room is not free for this stay.'];
        }
        return ['bad', 'Unknown action.'];
    }

    public function check_in(int $id): bool
    {
        $ok = $this->db->table($this->table)->where('id', $id)->where('status', 'confirmed')->update(['status' => 'checked_in', 'checked_in_at' => now_str()]) > 0;
        if ($ok) $this->calendar($id);
        return $ok;
    }
    public function check_out(int $id): bool
    {
        $ok = $this->db->table($this->table)->where('id', $id)->where('status', 'checked_in')->update(['status' => 'checked_out', 'checked_out_at' => now_str()]) > 0;
        if ($ok) $this->calendar($id);
        return $ok;
    }
    public function reassign(array $b, int $roomId): bool
    {
        $room = $this->models('RoomModel')->room($roomId);
        if (!$room || $room['status'] !== 'active' || (int) $room['room_type_id'] !== (int) $b['room_type_id']) return false;
        $type = $this->models('RoomTypeModel')->type((int) $b['room_type_id']);
        $this->db->transaction();
        $ok = false;
        foreach ($this->free_rooms((int) $b['room_type_id'], $b['start_at'], $b['end_at'], (int) $type['cleaning_buffer_min'], (int) $b['id'], true) as $r) {
            if ((int) $r['id'] === $roomId) {
                $this->db->table($this->table)->where('id', $b['id'])->update(['room_id' => $roomId]);
                $ok = true;
                break;
            }
        }
        $ok ? $this->db->commit() : $this->db->roll_back();
        if ($ok) $this->calendar((int) $b['id']);
        return $ok;
    }
}
