<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| The motel's own details and rules (name, address, map position, fees, hours).
| Table: settings (key / value). Values are cached for the current request.
*/
class SettingModel extends Model
{
    protected $table       = 'settings';
    protected $primary_key = 'skey';

    private static $cache = null;

    /* Read: every setting as [key => value] */
    public function all_values(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach ($this->db->table($this->table)->get_all() as $row) {
                self::$cache[$row['skey']] = $row['svalue'];
            }
        }
        return self::$cache;
    }

    public function value(string $key, string $default = ''): string
    {
        return $this->all_values()[$key] ?? $default;
    }

    /* Create or update one setting */
    public function save_value(string $key, string $value): void
    {
        $exists = $this->db->table($this->table)->where('skey', $key)->count() > 0;
        if ($exists) {
            $this->db->table($this->table)->where('skey', $key)->update(['svalue' => $value]);
        } else {
            $this->db->table($this->table)->insert(['skey' => $key, 'svalue' => $value]);
        }
        self::$cache = null;
    }

    /* The settings the owner can change, with the pattern each value must match */
    const RULES = [
        'motel_name' => '/^.{2,80}$/u', 'motel_address' => '/^.{2,150}$/u',
        'motel_lat' => '/^-?\d{1,3}(\.\d{1,8})?$/', 'motel_lng' => '/^-?\d{1,3}(\.\d{1,8})?$/',
        'booking_fee' => '/^\d{1,4}(\.\d{1,2})?$/', 'hold_minutes' => '/^\d{1,2}$/', 'cancel_hours' => '/^\d{1,2}$/',
        'overnight_start' => '/^([01]\d|2[0-3]):[0-5]\d$/', 'overnight_end' => '/^([01]\d|2[0-3]):[0-5]\d$/',
    ];

    /* Checks every value, then saves all of them. Returns an error message, or null when saved. */
    public function save_all(array $input): ?string
    {
        $clean = [];
        foreach (self::RULES as $key => $pattern) {
            $value = trim((string) ($input[$key] ?? $this->value($key)));
            if (!preg_match($pattern, $value)) {
                return 'Invalid value for ' . str_replace('_', ' ', $key) . '.';
            }
            $clean[$key] = $value;
        }
        foreach ($clean as $key => $value) {
            $this->save_value($key, $value);
        }
        return null;
    }

    /* Delete one setting (the motel falls back to the built-in default) */
    public function remove(string $key): bool
    {
        $done = $this->db->table($this->table)->where('skey', $key)->delete() > 0;
        self::$cache = null;
        return $done;
    }
}
