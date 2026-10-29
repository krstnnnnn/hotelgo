<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| HOTELGO JSON API: Google Calendar for the hotel owner (and the platform admin).
|   GET  /api/calendar        is it connected, which service account, which hotels use which calendar
|   POST /api/calendar/test   can HOTELGO open this hotel's Google Calendar?  { hotel_id, calendar_id? }
|   POST /api/calendar/sync   put every paid booking that is not over yet into the calendar  { hotel_id? }
| The sync itself happens by itself after every payment / change (BookingModel -> CalendarModel). These are the buttons to check and repair it.
*/
class ApiCalendarController extends \Hotelgo\Support\ApiBase
{
    public function __construct()
    {
        parent::__construct();
        $this->call->library('google_calendar');
        $this->call->model(['HotelModel', 'CalendarModel']);
    }

    private function hotel(): array
    {
        $in = $this->body();
        $id = (int) ($in['hotel_id'] ?? 0) ?: (int) (\Hotelgo\Support\Scope::hotel() ?? 0);
        return $this->HotelModel->hotel($id) ?: $this->fail('Choose one of your hotels.', 404);
    }

    public function status()                         // GET
    {
        $this->need('GET'); $this->who('admin');
        $g = $this->google_calendar;
        $hotels = [];
        foreach ($this->HotelModel->all() as $h) {
            $own = trim((string) ($h['calendar_id'] ?? ''));
            $hotels[] = ['id' => (int) $h['id'], 'name' => $h['name'], 'calendar_id' => $own, 'uses' => $own !== '' ? 'own' : ($g->default_calendar() !== '' ? 'default' : 'none'),
                'events' => $this->CalendarModel->count_for_hotel((int) $h['id'])];
        }
        $this->ok(['connected' => $g->configured(), 'service_account' => $g->email(), 'has_default' => $g->default_calendar() !== '', 'hotels' => $hotels]);
    }

    public function test()                           // POST
    {
        $this->need('POST'); $this->who('admin');
        $h = $this->hotel();
        $in = $this->body();
        $cal = array_key_exists('calendar_id', $in) ? trim((string) $in['calendar_id']) : $this->google_calendar->calendar_for($h);
        if ($cal === '') $cal = $this->google_calendar->default_calendar();
        [$ok, $msg] = $this->CalendarModel->test($cal);
        if (!$ok) $this->fail($msg, 422);
        $this->ok(['message' => 'Connected to the calendar "' . $msg . '".', 'calendar' => $msg]);
    }

    public function sync()                           // POST
    {
        $this->need('POST'); $this->who('admin');
        $h = $this->hotel();
        if (!$this->google_calendar->configured()) $this->fail('Google Calendar is not connected on the server.', 422);
        if ($this->google_calendar->calendar_for($h) === '') $this->fail('This hotel has no Google Calendar ID yet. Add it on the Hotels page.', 422);
        [$sent, $failed, $why] = $this->CalendarModel->sync_upcoming((int) $h['id']);
        $this->log('calendar_synced', $h['name'] . ': ' . $sent . ' sent, ' . $failed . ' failed');
        if ($failed > 0 && $sent === 0) $this->fail($why ?: 'Google did not accept the bookings.', 422);
        $this->ok(['sent' => $sent, 'failed' => $failed, 'message' => $sent . ' booking' . ($sent === 1 ? ' is' : 's are') . ' in the calendar now.' . ($failed ? ' ' . $failed . ' could not be sent: ' . $why : '')]);
    }
}
