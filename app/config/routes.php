<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| HOTELGO routes. This server is the BACK END only: it answers JSON (and one picture route). No HTML pages.
| The website and the staff console are the separate React project (hotelgo-web).
|   public   = anybody:   /api/hotels, /api/room-types, /api/availability, /api/photos, guest sign-up / sign-in
|   guest    = a signed-in guest (bearer token):   /api/guest/...
|   staff    = front desk + admin (bearer token):  bookings, guests, requests ...
|   admin    = owner only:                        room types, rooms, staff, settings, payments ...
| The rules live in the Api*Controller files and app/support/ApiBase.php. No cookies and no CSRF: the bearer token proves who is calling.
*/

/* ---------- the back end itself: JSON status, and the uploaded pictures ---------- */
$router->get('/', 'ApiRootController::index');
$router->get('/photo/{id}', 'PhotoController::show');
$router->where_number('id');

/* ---------- database migrations (see MigrationController: terminal, or MIGRATION_WEB=1 on your own computer) ---------- */
$router->get('create-migration/{migration_class}', 'MigrationController::create_migration');
$router->get('migrate', 'MigrationController::migrate');
$router->get('rollback', 'MigrationController::rollback');
$router->get('rollback-all', 'MigrationController::rollback_all');
$router->get('refresh', 'MigrationController::refresh');
$router->get('status', 'MigrationController::status');


/* ---------- JSON API (JWT bearer token) ---------- */
$router->group(['prefix' => '/api'], function ($router) {
    $router->get('/', 'ApiRootController::index');

    /* the guest account and booking (the website) */
    $router->post('/guest/register', 'ApiGuestAuthController::register');
    $router->post('/guest/verify', 'ApiGuestAuthController::verify');
    $router->post('/guest/verify/resend', 'ApiGuestAuthController::resend');
    $router->post('/guest/login', 'ApiGuestAuthController::login');
    $router->post('/guest/forgot', 'ApiGuestAuthController::forgot');
    $router->post('/guest/reset', 'ApiGuestAuthController::reset');
    $router->get('/guest/me', 'ApiGuestAuthController::me');
    $router->post('/guest/password', 'ApiGuestAuthController::password');
    $router->get('/guest/quote', 'ApiGuestBookingController::quote');
    $router->post('/guest/bookings', 'ApiGuestBookingController::create');
    $router->get('/guest/bookings', 'ApiGuestBookingController::bookings');
    $router->get('/guest/bookings/{ref}', 'ApiGuestBookingController::booking');
    $router->post('/guest/bookings/{ref}/cancel', 'ApiGuestBookingController::cancel');
    $router->post('/guest/bookings/{ref}/modify', 'ApiGuestBookingController::modify');
    $router->get('/guest/bookings/{ref}/pay', 'ApiGuestBookingController::pay_info_get');
    $router->post('/guest/bookings/{ref}/pay', 'ApiGuestBookingController::pay');
    $router->post('/guest/bookings/{ref}/requests', 'ApiGuestBookingController::request_add');
    $router->post('/guest/bookings/{ref}/requests/{id}/pay', 'ApiGuestBookingController::request_pay');
    $router->get('/guest/bookings/{ref}/receipt', 'ApiGuestBookingController::receipt');
    $router->get('/guest/bookings/{ref}/calendar', 'ApiGuestBookingController::calendar');

    /* staff and owner (the console) */
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
    $router->post('/bookings/{ref}/pay', 'ApiBookingController::booking_pay');

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

    $router->get('/requests', 'ApiRequestController::requests');
    $router->post('/bookings/{ref}/requests', 'ApiRequestController::add');
    $router->put('/requests/{id}', 'ApiRequestController::decide');
    $router->post('/requests/{id}/pay', 'ApiRequestController::pay');

    $router->get('/calendar', 'ApiCalendarController::status');
    $router->post('/calendar/test', 'ApiCalendarController::test');
    $router->post('/calendar/sync', 'ApiCalendarController::sync');

    $router->get('/settings', 'ApiOwnerController::settings');
    $router->put('/settings', 'ApiOwnerController::settings_save');
    $router->get('/reports', 'ApiOwnerController::reports');
    $router->get('/audit', 'ApiOwnerController::audit');
});
