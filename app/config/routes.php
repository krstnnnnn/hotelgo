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
$router->get('/photo/{id}', 'PhotoController::show');
$router->where_number('id');
$router->get('/rooms', 'HomeController::rooms');
$router->get('/terms', 'HomeController::terms');
$router->get('/privacy', 'HomeController::privacy');
$router->get('/cancellation-policy', 'HomeController::cancellation');
$router->get('/house-rules', 'HomeController::house_rules');

/* ---------- database migrations (see MigrationController: terminal, or MIGRATION_WEB=1 on your own computer) ---------- */
$router->get('create-migration/{migration_class}', 'MigrationController::create_migration');
$router->get('migrate', 'MigrationController::migrate');
$router->get('rollback', 'MigrationController::rollback');
$router->get('rollback-all', 'MigrationController::rollback_all');
$router->get('refresh', 'MigrationController::refresh');
$router->get('status', 'MigrationController::status');

/* ---------- PayMongo calls this by itself (no browser). Protected by PayMongo's signature, see WebhookController ---------- */
$router->post('/webhooks/paymongo', 'WebhookController::paymongo');

/* ---------- hidden staff sign-in: address comes from .env (STAFF_LOGIN_PATH), never printed on any page ---------- */
if (config_item('hotelgo_staff_path') !== '') {
    $router->group(['middleware' => ['guest']], function ($router) {
        $router->get('/' . config_item('hotelgo_staff_path'), 'AuthController::staff_login_form');
        $router->post('/' . config_item('hotelgo_staff_path'), 'AuthController::staff_login');
    });
}

/* ---------- sign in / register / reset (only when NOT signed in) ---------- */
$router->group(['middleware' => ['guest']], function ($router) {
    $router->get('/login', 'AuthController::login_form');
    $router->post('/login', 'AuthController::login');
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
    $router->get('/booking/{ref}/receipt', 'BookingController::receipt');
    $router->get('/booking/{ref}/modify', 'BookingController::modify_form');
    $router->post('/booking/{ref}/modify', 'BookingController::modify');
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
    $router->post('/admin/settings/test-sms', 'ManageController::test_sms');
    $router->post('/admin/settings/test-email', 'ManageController::test_email');
});

/* ---------- JSON API for the React console (JWT bearer token, no cookies, no CSRF). Rules live in the Api*Controller files + app/support/ApiBase.php ---------- */
$router->group(['prefix' => '/api'], function ($router) {
    $router->post('/auth/login', 'ApiAuthController::login');
    $router->post('/auth/refresh', 'ApiAuthController::refresh');
    $router->post('/auth/logout', 'ApiAuthController::logout');
    $router->get('/me', 'ApiAuthController::me');
    $router->post('/me/password', 'ApiAuthController::change_password');

    $router->get('/room-types', 'ApiCatalogController::room_types');          // public
    $router->get('/availability', 'ApiCatalogController::availability');      // public
    $router->get('/hotels', 'ApiHotelController::hotels');                    // public: the hotels on the platform
    $router->get('/photos', 'ApiPhotoController::site');                      // public: the home-page pictures
    $router->post('/manage/photos', 'ApiPhotoController::save');             // upload / remove a picture

    $router->get('/manage/hotels', 'ApiHotelController::manage');
    $router->post('/manage/hotels', 'ApiHotelController::create');
    $router->put('/manage/hotels/{id}', 'ApiHotelController::update');
    $router->delete('/manage/hotels/{id}', 'ApiHotelController::delete');

    $router->get('/dashboard', 'ApiBookingController::dashboard');

    $router->get('/bookings', 'ApiBookingController::bookings');
    $router->post('/bookings', 'ApiBookingController::booking_walkin');
    $router->get('/bookings/{ref}', 'ApiBookingController::booking');
    $router->put('/bookings/{ref}', 'ApiBookingController::booking_update');
    $router->delete('/bookings/{ref}', 'ApiBookingController::booking_delete');
    $router->post('/bookings/{ref}/action', 'ApiBookingController::booking_action');

    $router->get('/guests', 'ApiGuestController::guests');
    $router->get('/guests/{id}', 'ApiGuestController::guest');
    $router->put('/guests/{id}', 'ApiGuestController::guest_update');
    $router->delete('/guests/{id}', 'ApiGuestController::guest_delete');

    $router->get('/types', 'ApiCatalogController::types');
    $router->post('/types', 'ApiCatalogController::type_create');
    $router->put('/types/{id}', 'ApiCatalogController::type_update');
    $router->delete('/types/{id}', 'ApiCatalogController::type_delete');

    $router->get('/rooms', 'ApiCatalogController::rooms');
    $router->post('/rooms', 'ApiCatalogController::room_create');
    $router->put('/rooms/{id}', 'ApiCatalogController::room_update');
    $router->delete('/rooms/{id}', 'ApiCatalogController::room_delete');

    $router->get('/payments', 'ApiOwnerController::payments');
    $router->post('/payments/{id}/refund', 'ApiOwnerController::payment_refund');

    $router->get('/notifications', 'ApiOwnerController::notifications');
    $router->post('/notifications/{id}/retry', 'ApiOwnerController::notification_retry');
    $router->delete('/notifications/{id}', 'ApiOwnerController::notification_delete');

    $router->get('/staff', 'ApiOwnerController::staff');
    $router->post('/staff', 'ApiOwnerController::staff_create');
    $router->put('/staff/{id}', 'ApiOwnerController::staff_update');
    $router->delete('/staff/{id}', 'ApiOwnerController::staff_delete');

    $router->get('/settings', 'ApiOwnerController::settings');
    $router->put('/settings', 'ApiOwnerController::settings_save');
    $router->get('/reports', 'ApiOwnerController::reports');
    $router->get('/audit', 'ApiOwnerController::audit');
});
