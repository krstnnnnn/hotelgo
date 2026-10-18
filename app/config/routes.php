<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| HOTELGO routes.  Read top to bottom:  who may open which page?
|   (no middleware)  = anybody
|   guest            = only people who are NOT signed in
|   auth             = any signed-in user
|   staff            = front desk + admin
|   admin            = admin (owner) only
| 'csrf' runs on every route and blocks forged POST requests.
*/
$router->add_global_middleware('csrf');

/* ---------- public pages ---------- */
$router->get('/', 'HomeController::index');
$router->get('/rooms', 'HomeController::rooms');

/* ---------- sign in / register / reset (only when NOT signed in) ---------- */
$router->group(['middleware' => ['guest']], function ($router) {
    $router->get('/login', 'AuthController::login_form');
    $router->post('/login', 'AuthController::login');
    $router->get('/admin/login', 'AuthController::staff_login_form');
    $router->post('/admin/login', 'AuthController::staff_login');
    $router->get('/register', 'AuthController::register_form');
    $router->post('/register', 'AuthController::register');
    $router->get('/forgot', 'AuthController::forgot_form');
    $router->post('/forgot', 'AuthController::forgot');
    $router->get('/reset', 'AuthController::reset_form');
    $router->post('/reset', 'AuthController::reset');
});
$router->get('/verify', 'AuthController::verify_form');
$router->post('/verify', 'AuthController::verify');
$router->post('/verify/resend', 'AuthController::verify_resend');

/* ---------- any signed-in user ---------- */
$router->group(['middleware' => ['auth']], function ($router) {
    $router->post('/logout', 'AuthController::logout');
    $router->get('/account/password', 'AuthController::password_form');
    $router->post('/account/password', 'AuthController::password');

    $router->get('/book', 'BookingController::checkout_form');
    $router->post('/book', 'BookingController::checkout');
    $router->get('/pay/{ref}', 'BookingController::pay');
    $router->post('/pay/{ref}/confirm', 'BookingController::pay_confirm');
    $router->get('/pay/{ref}/return', 'BookingController::pay_return');
    $router->get('/my-bookings', 'BookingController::mine_list');
    $router->get('/booking/{ref}', 'BookingController::show');
    $router->post('/booking/{ref}/cancel', 'BookingController::cancel');
    $router->get('/booking/{ref}/calendar.ics', 'BookingController::ics');
});

/* ---------- front desk (staff + admin) ---------- */
$router->group(['middleware' => ['staff']], function ($router) {
    $router->get('/admin', 'AdminController::dashboard');
    $router->get('/admin/reservations', 'AdminController::reservations');
    $router->get('/admin/reservations/{ref}', 'AdminController::reservation');
    $router->post('/admin/reservations/{ref}/action', 'AdminController::reservation_action');
    $router->get('/admin/walkin', 'AdminController::walkin_form');
    $router->post('/admin/walkin', 'AdminController::walkin');
    $router->get('/admin/guests', 'AdminController::guests');
    $router->get('/admin/guests/{id}', 'AdminController::guest');
    $router->where_number('id');
    $router->post('/admin/guests/{id}/note', 'AdminController::guest_note');
    $router->where_number('id');
});

/* ---------- owner only (admin) ---------- */
$router->group(['middleware' => ['admin']], function ($router) {
    $router->get('/admin/rooms', 'ManageController::rooms');
    $router->post('/admin/rooms/type', 'ManageController::save_type');
    $router->post('/admin/rooms/add', 'ManageController::add_room');
    $router->post('/admin/rooms/{id}/status', 'ManageController::room_status');
    $router->where_number('id');
    $router->get('/admin/payments', 'ManageController::payments');
    $router->post('/admin/payments/{id}/refund', 'ManageController::refund');
    $router->where_number('id');
    $router->get('/admin/notifications', 'ManageController::notifications');
    $router->post('/admin/notifications/{id}/retry', 'ManageController::notification_retry');
    $router->where_number('id');
    $router->get('/admin/reports', 'ManageController::reports');
    $router->get('/admin/reports/export', 'ManageController::export');
    $router->get('/admin/staff', 'ManageController::staff');
    $router->post('/admin/staff', 'ManageController::staff_create');
    $router->post('/admin/staff/{id}/toggle', 'ManageController::staff_toggle');
    $router->where_number('id');
    $router->get('/admin/audit', 'ManageController::audit_log');
    $router->get('/admin/settings', 'ManageController::settings');
    $router->post('/admin/settings', 'ManageController::settings_save');
});
