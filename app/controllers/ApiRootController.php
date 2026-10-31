<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| The address of the back end itself (GET / and GET /api). This server has NO website: it only answers JSON.
| The website and the staff console are the separate React project (hotelgo-web, config: FRONTEND_URL).
*/
class ApiRootController extends Controller
{
    public function index()
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode([
            'name'     => 'HOTELGO API',
            'status'   => 'ok',
            'message'  => 'This is the HOTELGO back end (JSON only). The website is the React app.',
            'website'  => (string) config_item('hotelgo_frontend_url') ?: null,
            'endpoints' => ['public' => ['/api/hotels', '/api/room-types', '/api/availability', '/api/photos'], 'guest' => '/api/guest/*', 'staff' => '/api/auth/login, /api/bookings, ...'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
