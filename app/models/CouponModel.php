<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| Coupons (promo codes). Table: coupons.  Create / read / update / delete, plus the rules a guest has to pass to use one.
|   kind percent : value = % off the room rate (max_discount caps it, 0 = no cap)      kind fixed : value = pesos off the room rate
|   first_booking_only : only a guest who has no paid booking yet (the "new guest" voucher)
|   starts_on / ends_on : the days the code can be used (an occasion: Christmas sale, summer promo)
|   stay_from / stay_to : the stay must start inside these days (optional)
|   is_public : shown on the website as a current offer (off = a secret code you hand out yourself)
|   max_uses : total uses (0 = unlimited)    per_user_limit : uses per guest (0 = unlimited)
| The discount is taken off the ROOM RATE only (never the booking fee) and never goes below 0.
| Uses are counted from the bookings table (held, paid or finished), so a cancelled or expired booking gives the use back.
*/
class CouponModel extends Model
{
    protected $table = 'coupons';

    const SELECT = 'c.id, c.hotel_id, c.code, c.title, c.kind, c.value, c.max_discount, c.min_spend, c.first_booking_only, c.starts_on, c.ends_on, c.stay_from, c.stay_to, c.max_uses, c.per_user_limit, c.is_public, c.is_active, c.created_at';

    const COLUMNS = ['hotel_id', 'code', 'title', 'kind', 'value', 'max_discount', 'min_spend', 'first_booking_only', 'starts_on', 'ends_on', 'stay_from', 'stay_to', 'max_uses', 'per_user_limit', 'is_public', 'is_active'];

    /* ---------------- read ---------------- */
    /* The coupons this user manages (a hotel user: own hotel's and the platform-wide ones, read only is not needed: they only see their own) with how many times each was used. */
    public function all(): array
    {
        $this->db->table('coupons c')->select(self::SELECT . ', h.name AS hotel_name')->left_join('hotels h', 'h.id = c.hotel_id');
        \Hotelgo\Support\Scope::limit($this->db, 'c.hotel_id');
        $rows = $this->db->order_by('c.is_active', 'DESC')->order_by('c.id', 'DESC')->get_all();
        foreach ($rows as &$r) $r['used'] = $this->used((int) $r['id']);
        return $rows;
    }
    public function one(int $id): ?array
    {
        $c = $this->db->table('coupons')->where('id', $id)->get() ?: null;
        return ($c && \Hotelgo\Support\Scope::allows($c['hotel_id'])) ? $c : null;
    }
    /* The offers shown on the website: public, switched on, inside their dates and not fully used. */
    public function offers(): array
    {
        $today = date('Y-m-d'); $out = [];
        $this->db->table('coupons c')->select(self::SELECT . ', h.name AS hotel_name')->left_join('hotels h', 'h.id = c.hotel_id')->where('c.is_public', 1)->where('c.is_active', 1);
        foreach ($this->db->order_by('c.id', 'DESC')->get_all() as $c) {
            if (($c['starts_on'] && $today < $c['starts_on']) || ($c['ends_on'] && $today > $c['ends_on'])) continue;
            if ((int) $c['max_uses'] > 0 && $this->usage((int) $c['id']) >= (int) $c['max_uses']) continue;
            $out[] = ['code' => $c['code'], 'title' => $c['title'], 'kind' => $c['kind'], 'value' => (float) $c['value'], 'max_discount' => (float) $c['max_discount'], 'min_spend' => (float) $c['min_spend'],
                'first_booking_only' => (int) $c['first_booking_only'], 'ends_on' => $c['ends_on'], 'stay_from' => $c['stay_from'], 'stay_to' => $c['stay_to'], 'hotel_name' => $c['hotel_name']];
        }
        return $out;
    }
    /* Answers remembered for the rest of this request. The catalog asks "best voucher?" once per room type,
       so without this the same coupon lookups ran hundreds of times (slow on a remote database). */
    private array $memo = [];
    private function remember(string $key, callable $fn)
    {
        if (!array_key_exists($key, $this->memo)) $this->memo[$key] = $fn();
        return $this->memo[$key];
    }

    public function by_code(string $code): ?array
    {
        $code = strtoupper(trim($code));
        return $this->remember('code:' . $code, fn() => $this->db->table('coupons')->where('code', $code)->get() ?: null);
    }

    /* bookings that hold or used this coupon (cancelled / expired ones do not count) */
    private function usage(int $couponId, ?int $userId = null): int
    {
        return $this->remember('usage:' . $couponId . ':' . (int) $userId, function () use ($couponId, $userId) {
            $c = $this->find($couponId);
            if (!$c) return 0;
            $base = function () use ($c, $userId) {
                $this->db->table('bookings')->where('coupon_code', $c['code']);
                if ($userId) $this->db->where('user_id', $userId);
                return $this->db;
            };
            $paid = $base()->in('status', ['confirmed', 'checked_in', 'checked_out'])->count();
            $held = $base()->where('status', 'pending_payment')->where('hold_expires_at', '>', now_str())->count();
            return $paid + $held;
        });
    }
    public function used(int $couponId): int { return $this->usage($couponId); }

    /* Has this guest paid for a booking before? */
    private function has_paid_booking(int $userId): bool
    {
        return $this->remember('paid:' . $userId, fn() => $this->db->table('bookings')->where('user_id', $userId)->in('status', ['confirmed', 'checked_in', 'checked_out'])->count() > 0);
    }

    /* ---------------- the rules ---------------- */
    /* Can this code be used for this booking? $userId 0 = a walk-in (no guest account).
       Returns ['ok' => true, 'coupon' => row, 'discount' => pesos] or ['ok' => false, 'error' => why]. */
    public function check(string $code, int $userId, int $hotelId, float $rate, string $stayStart): array
    {
        $no = fn(string $why) => ['ok' => false, 'error' => $why];
        $code = strtoupper(trim($code));
        $c = $code === '' ? null : $this->by_code($code);
        if (!$c || !(int) $c['is_active']) return $no('This coupon code is not valid.');
        if ($c['hotel_id'] !== null && (int) $c['hotel_id'] !== $hotelId) return $no('This coupon is not for this hotel.');
        $today = date('Y-m-d'); $stayDay = substr($stayStart, 0, 10);
        if ($c['starts_on'] && $today < $c['starts_on']) return $no('This coupon starts on ' . date('M j, Y', strtotime($c['starts_on'])) . '.');
        if ($c['ends_on'] && $today > $c['ends_on']) return $no('This coupon has expired.');
        if (($c['stay_from'] && $stayDay < $c['stay_from']) || ($c['stay_to'] && $stayDay > $c['stay_to'])) {
            return $no('This coupon is for stays from ' . ($c['stay_from'] ? date('M j', strtotime($c['stay_from'])) : 'any day') . ' to ' . ($c['stay_to'] ? date('M j, Y', strtotime($c['stay_to'])) : 'any day') . '.');
        }
        if ((float) $c['min_spend'] > 0 && $rate < (float) $c['min_spend']) return $no('This coupon needs a room rate of at least ₱' . number_format((float) $c['min_spend']) . '.');
        if ((int) $c['first_booking_only']) {
            if ($userId < 1) return $no('This coupon is for new guests who book online with an account.');
            if ($this->has_paid_booking($userId)) return $no('This coupon is only for your first booking.');
        }
        if ((int) $c['max_uses'] > 0 && $this->usage((int) $c['id']) >= (int) $c['max_uses']) return $no('Sorry, this coupon has been fully used.');
        if ((int) $c['per_user_limit'] > 0 && $userId > 0 && $this->usage((int) $c['id'], $userId) >= (int) $c['per_user_limit']) return $no('You already used this coupon.');
        $off = $c['kind'] === 'percent' ? round($rate * (float) $c['value'] / 100, 2) : (float) $c['value'];
        if ($c['kind'] === 'percent' && (float) $c['max_discount'] > 0) $off = min($off, (float) $c['max_discount']);
        $off = round(min($off, $rate), 2);
        if ($off <= 0) return $no('This coupon gives no discount for this booking.');
        return ['ok' => true, 'coupon' => $c, 'discount' => $off];
    }

    /* The best PUBLIC code this guest can use for this booking (the biggest saving), or null.
       $userId 0 = not signed in (so a first-booking-only code is not offered yet). */
    public function best(int $userId, int $hotelId, float $rate, string $stayStart): ?array
    {
        $best = null;
        $public = $this->remember('public', fn() => $this->db->table('coupons')->where('is_public', 1)->where('is_active', 1)->get_all());
        foreach ($public as $c) {
            if ($c['hotel_id'] !== null && (int) $c['hotel_id'] !== $hotelId) continue;
            $r = $this->check($c['code'], $userId, $hotelId, $rate, $stayStart);
            if ($r['ok'] && (!$best || $r['discount'] > $best['discount'])) $best = $r;
        }
        return $best;
    }

    /* ---------------- create / update ---------------- */
    /* Checks and cleans input. Returns [cleanColumns, null] or [null, whyNot]. */
    public function clean(array $in, ?int $id = null): array
    {
        $date = function ($v) { $v = trim((string) ($v ?? '')); return $v === '' ? [true, null] : [(bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) !== false, $v]; };
        $d = [
            'code' => strtoupper(trim((string) ($in['code'] ?? ''))), 'title' => trim((string) ($in['title'] ?? '')),
            'kind' => (string) ($in['kind'] ?? 'percent'), 'value' => filter_var((string) ($in['value'] ?? ''), FILTER_VALIDATE_FLOAT),
            'max_discount' => (float) ($in['max_discount'] ?? 0), 'min_spend' => (float) ($in['min_spend'] ?? 0),
            'first_booking_only' => in_array($in['first_booking_only'] ?? 0, [1, '1', true, 'on'], true) ? 1 : 0,
            'max_uses' => (int) ($in['max_uses'] ?? 0), 'per_user_limit' => (int) ($in['per_user_limit'] ?? 1),
            'is_public' => in_array($in['is_public'] ?? 1, [1, '1', true, 'on'], true) ? 1 : 0,
            'is_active' => in_array($in['is_active'] ?? 1, [1, '1', true, 'on'], true) ? 1 : 0, 'hotel_id' => null,
        ];
        foreach (['starts_on', 'ends_on', 'stay_from', 'stay_to'] as $k) {
            [$good, $v] = $date($in[$k] ?? null);
            if (!$good) return [null, 'Dates must look like 2026-12-25.'];
            $d[$k] = $v;
        }
        if (!preg_match('/^[A-Z0-9]{3,20}$/', $d['code'])) return [null, 'The code must be 3 to 20 letters or numbers (for example WELCOME10).'];
        if (!len_ok($d['title'], 2, 80)) return [null, 'Please give the coupon a short title (for example "New guest welcome").'];
        if (!in_array($d['kind'], ['percent', 'fixed'], true)) return [null, 'Choose percent or fixed amount.'];
        if ($d['value'] === false || $d['value'] <= 0 || ($d['kind'] === 'percent' && $d['value'] > 100) || $d['value'] > 100000) return [null, $d['kind'] === 'percent' ? 'Percent off must be between 1 and 100.' : 'The discount must be more than 0.'];
        if ($d['max_discount'] < 0 || $d['min_spend'] < 0 || $d['max_discount'] > 100000 || $d['min_spend'] > 100000 || $d['max_uses'] < 0 || $d['max_uses'] > 1000000 || $d['per_user_limit'] < 0 || $d['per_user_limit'] > 1000) return [null, 'Please check the limits (numbers, 0 or more).'];
        if ($d['starts_on'] && $d['ends_on'] && $d['ends_on'] < $d['starts_on']) return [null, 'The coupon cannot end before it starts.'];
        if ($d['stay_from'] && $d['stay_to'] && $d['stay_to'] < $d['stay_from']) return [null, 'The stay dates are the wrong way round.'];
        // which hotel: a hotel user is always their own hotel; the platform admin picks one, or leaves it empty for every hotel
        $scope = \Hotelgo\Support\Scope::hotel();
        $hid = $scope ?: (int) ($in['hotel_id'] ?? 0);
        if ($hid > 0) {
            if ($this->db->table('hotels')->where('id', $hid)->count() < 1) return [null, 'Hotel not found.'];
            $d['hotel_id'] = $hid;
        }
        $this->db->table('coupons')->where('code', $d['code']);
        if ($id) $this->db->where('id', '<>', $id);
        if ($this->db->count() > 0) return [null, 'That code is already used by another coupon.'];
        return [$d, null];
    }
    public function save(?int $id, array $data): int
    {
        $row = array_intersect_key($data, array_flip(self::COLUMNS));
        if ($id) { $this->update($id, $row); return $id; }
        $row['created_at'] = now_str();
        return (int) $this->insert($row);
    }

    /* ---------------- delete ---------------- */
    /* A coupon already used on a booking is kept (switch it off instead). Returns [ok, message]. */
    public function remove(int $id): array
    {
        $c = $this->one($id);
        if (!$c) return [false, 'Coupon not found.'];
        if ($this->db->table('bookings')->where('coupon_code', $c['code'])->count() > 0) return [false, 'This coupon was used on bookings. Switch it off instead of deleting it.'];
        $this->delete($id);
        return [true, 'Coupon deleted.'];
    }
}
