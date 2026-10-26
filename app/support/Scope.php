<?php
namespace Hotelgo\Support;

/*
| Which hotel the signed-in staff member works for.
|  - front desk / hotel owner : tied to ONE hotel (users.hotel_id). Every query is limited to that hotel.
|  - platform admin           : no hotel (users.hotel_id empty). Sees every hotel.
|  - guests and public pages  : no scope set, they can search all hotels.
| It is set once per request (ApiBase::who() and the staff middlewares) and the models read it.
*/
final class Scope
{
    private static ?int $hotel = null;
    private static bool $platform = false;

    public static function set(?array $user): void
    {
        self::$hotel = null; self::$platform = false;
        if ($user && in_array($user['role'] ?? '', ['staff', 'admin'], true)) {
            if (!empty($user['hotel_id'])) {
                self::$hotel = (int) $user['hotel_id'];
            } elseif (($user['role'] ?? '') === 'admin') {
                self::$platform = true;
            } else {
                self::$hotel = 0;                 // a staff account with no hotel sees nothing
            }
        }
    }
    public static function hotel(): ?int      { return self::$hotel; }
    public static function platform(): bool   { return self::$platform; }

    /* Add "WHERE column = my hotel" when a hotel scope is active */
    public static function limit($db, string $column)
    {
        if (self::$hotel !== null) {
            $db->where($column, self::$hotel);
        }
        return $db;
    }
    /* May the current user touch a row that belongs to this hotel? */
    public static function allows($hotelId): bool
    {
        return self::$hotel === null || (int) $hotelId === self::$hotel;
    }
}
