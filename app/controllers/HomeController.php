<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class HomeController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->model('RoomTypeModel');
        $this->call->model('HotelModel');
        $this->call->model('BookingModel');
    }

    public function index()
    {
        render('home/index', ['hotels' => $this->HotelModel->listing(), 'types' => $this->RoomTypeModel->types(), 'title' => 'Book a room by the hour or overnight', 'landing' => true]);
    }

    /* Terms, privacy and policies (plain pages, text lives in views/home/legal.php) */
    public function terms()        { $this->legal('terms', 'Terms of Service'); }
    public function privacy()      { $this->legal('privacy', 'Privacy Policy'); }
    public function cancellation() { $this->legal('cancellation', 'Cancellation & Refund Policy'); }
    public function house_rules()  { $this->legal('rules', 'House Rules'); }
    private function legal(string $page, string $title): void
    {
        render('home/legal', ['page' => $page, 'title' => $title]);
    }

    /* Search results: GET /rooms?stay=hourly&date=2026-10-17&time=19:00&hours=3&guests=2 */
    public function rooms()
    {
        $p = [
            'stay'   => in_array(get_str('stay', 'hourly'), ['hourly', 'overnight'], true) ? get_str('stay', 'hourly') : 'hourly',
            'date'   => get_str('date', date('Y-m-d')),
            'time'   => get_str('time', '19:00'),
            'hours'  => get_int('hours', 3),
            'guests' => max(1, min(10, get_int('guests', 2))),
            'area'   => get_str('area', ''),
        ];
        if (get_int('hotel', 0)) $p['hotel'] = get_int('hotel', 0);
        $error = null; $rows = [];
        $w = $this->BookingModel->window($p['stay'], $p['date'], $p['time'], $p['hours']);
        if (!$w) {
            $error = 'Please choose a valid date and start time (not in the past, within 60 days).';
        } else {
            $hotel = get_int('hotel', 0); $area = get_str('area', '');
            $avail = $this->BookingModel->availability($w['start'], $w['end'], $hotel, $area);
            foreach ($this->RoomTypeModel->types(true, $hotel, $area) as $t) {
                if ((int) $t['max_guests'] < $p['guests']) continue;
                $t['free'] = $avail[(int) $t['id']] ?? 0;
                $t['price'] = $this->BookingModel->price($t, $p['stay'], (int) $w['hours']);
                $t['link'] = site_url('book') . '?' . http_build_query(array_diff_key($p, ['area' => 1, 'hotel' => 1]) + ['type' => $t['id']]);
                $rows[] = $t;
            }
        }
        render('home/rooms', ['areas' => $this->HotelModel->areas(), 'area' => get_str('area', ''), 'p' => $p, 'w' => $w, 'rows' => $rows, 'error' => $error, 'title' => 'Available rooms']);
    }
}
