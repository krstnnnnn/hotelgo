<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| Guest ratings. Table: reviews.
|   A guest may rate a booking once, after the stay has ended (end time passed, or checked out).
|   Only the guest who made the booking can rate it. A cancelled or unpaid booking cannot be rated.
|   Public pages show the average, a word for it (Agoda style) and the latest comments, with the guest shown as "Juan D.".
*/
class ReviewModel extends Model
{
    protected $table = 'reviews';

    const EMPTY = ['avg' => null, 'count' => 0, 'label' => 'New'];

    /* the word for an average of stars (1 to 5) */
    public static function label(float $avg): string
    {
        if ($avg >= 4.5) return 'Exceptional';
        if ($avg >= 4.0) return 'Excellent';
        if ($avg >= 3.5) return 'Very good';
        if ($avg >= 3.0) return 'Good';
        return 'Fair';
    }

    /* [hotel_id => ['avg' => 4.6, 'count' => 12, 'label' => 'Excellent']] for many hotels at once */
    public function summaries(array $hotelIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $hotelIds))));
        if (!$ids) return [];
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->db->raw("SELECT hotel_id, AVG(rating) AS avg_rating, COUNT(*) AS cnt FROM reviews WHERE is_visible = 1 AND hotel_id IN ($marks) GROUP BY hotel_id", $ids)->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $avg = round((float) $r['avg_rating'], 1);
            $out[(int) $r['hotel_id']] = ['avg' => $avg, 'count' => (int) $r['cnt'], 'label' => self::label($avg)];
        }
        return $out;
    }

    /* the rating of one hotel (or the EMPTY summary when it has no reviews yet) */
    public function summary(int $hotelId): array
    {
        return $this->summaries([$hotelId])[$hotelId] ?? self::EMPTY;
    }

    /* the latest comments for one hotel, newest first (the guest's name is shortened) */
    public function recent(int $hotelId, int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        $rows = $this->db->raw("SELECT r.rating, r.comment, r.created_at, u.full_name FROM reviews r LEFT JOIN users u ON u.id = r.user_id WHERE r.hotel_id = ? AND r.is_visible = 1 ORDER BY r.id DESC LIMIT $limit", [$hotelId])->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($r) => [
            'rating' => (int) $r['rating'], 'comment' => (string) ($r['comment'] ?? ''), 'created_at' => $r['created_at'],
            'guest' => self::guest_name((string) ($r['full_name'] ?? '')),
        ], $rows);
    }

    /* "Juan Dela Cruz" -> "Juan D." (a public page never shows the full name) */
    public static function guest_name(string $full): string
    {
        $p = preg_split('/\s+/', trim($full), -1, PREG_SPLIT_NO_EMPTY);
        if (!$p) return 'Guest';
        $last = count($p) > 1 ? mb_strtoupper(mb_substr(end($p), 0, 1)) . '.' : '';
        return trim($p[0] . ' ' . $last);
    }

    /* the review this booking already has (or null) */
    public function mine(int $bookingId): ?array
    {
        return $this->db->table($this->table)->where('booking_id', $bookingId)->get() ?: null;
    }

    /* can this guest rate this booking now? Returns ['ok' => bool, 'why' => the reason when not] */
    public function eligible(array $b, int $userId): array
    {
        if ((int) ($b['user_id'] ?? $userId) !== $userId) return ['ok' => false, 'why' => 'Only the guest who booked can rate this stay.'];
        if (!in_array($b['status'], ['confirmed', 'checked_in', 'checked_out'], true)) return ['ok' => false, 'why' => 'Only a stay that took place can be rated.'];
        if ($b['status'] !== 'checked_out' && $b['end_at'] > now_str()) return ['ok' => false, 'why' => 'You can rate your stay after it ends.'];
        if ($this->mine((int) $b['id'])) return ['ok' => false, 'why' => 'You already rated this stay. Thank you!'];
        return ['ok' => true, 'why' => ''];
    }

    /* saves the rating. Returns ['ok' => true, 'id' => n] or ['ok' => false, 'error' => why] */
    public function submit(int $userId, array $b, int $rating, string $comment): array
    {
        $e = $this->eligible($b, $userId);
        if (!$e['ok']) return ['ok' => false, 'error' => $e['why']];
        if ($rating < 1 || $rating > 5) return ['ok' => false, 'error' => 'Please choose 1 to 5 stars.'];
        $comment = trim((string) preg_replace('/\s+/', ' ', $comment));
        if (mb_strlen($comment) > 600) return ['ok' => false, 'error' => 'Please keep the comment under 600 characters.'];
        $id = (int) $this->insert([
            'booking_id' => (int) $b['id'], 'user_id' => $userId, 'hotel_id' => (int) $b['hotel_id'], 'rating' => $rating,
            'comment' => $comment !== '' ? $comment : null, 'is_visible' => 1, 'created_at' => now_str(),
        ]);
        return ['ok' => true, 'id' => $id];
    }
}
