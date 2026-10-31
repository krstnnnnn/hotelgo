# HOTELGO API (back end)

> This repository is the **back end only** (a LavaLust JSON API + database migrations). It has **no web pages of its own**: opening its address shows only a JSON status. The whole website (guest booking pages *and* the staff console) is the **separate React repository** `hotelgo-web`. They talk only through HTTP (the API), and each one is deployed on its own.

Motel booking and room management for Puerto Galera, built on **LavaLust 4.7** (PHP 8). Guests search and book by the hour or overnight, pay online, and get a ticket with a QR code. The front desk and the owner run the motel from a staff area. Team 3F1: Bunquin, Castro, Evora, Gayuso (ITE311).

## How the pieces fit

```
hotelgo-web (React, its own website) ── HTTP + JWT ─► /api ─► Api*Controller ─► Model ─► MySQL
This repository answers JSON only; it never sends a page.
```

| Layer | Where | What it does |
|---|---|---|
| Routes | `app/config/routes.php` | One line per URL. The role check (guest / staff / admin) is made in `ApiBase` from the bearer token. |
| Controllers | `app/controllers/` | Read the request, call models, send JSON. No SQL. |
| Models | `app/models/` | All database work through the query builder (`$this->db->table(...)`). One model per table. Raw SQL only for two `SELECT … FOR UPDATE` locks in `BookingModel`. |
| Libraries | `app/libraries/` | Reusable services loaded with `$this->call->library('name')`: `Paymongo`, `Messenger`, `Http_client`, `Qr`, `Integrations`. Settings come from `app/config/integrations.php` (`config_item()`), which reads `.env`. |
| Support classes | `app/support/` | Small pure helpers (`Money`, `Phone`, `Csv`, `Ics`) autoloaded by **Composer** (`composer.json`, `composer_autoload = TRUE`). |
| Views | `app/views/errors/` | Only the framework's own error pages. There are no website pages here. |
| API | `app/controllers/Api*Controller.php` (Auth, Booking, Guest, Catalog, Owner) + `app/support/ApiBase.php` | JSON endpoints built on LavaLust's `Api` library (JWT access + refresh tokens). One controller per area; shared token check and replies live in `ApiBase`. |
| Migrations | `app/migrations/`, `app/commands/Migration.php`, `MigrationController` | The database structure as code: `php lava migration run`. |
| CORS | `app/config/config.php` (`security_headers.cors`), `FRONTEND_URL` in `.env` | Lets the React website (and only it) call `/api` from a browser. |

### Models and what they own

`UserModel` (guests, staff, owner) · `RoomTypeModel` · `RoomModel` · `BookingModel` (availability, booking rules, staff actions) · `PaymentModel` · `NotificationModel` · `SettingModel` · `AuditModel` · `LoginAttemptModel` · `OtpModel`.

Every table has create, read, update and delete. Delete rules protect history: a room type with rooms or bookings, a room with bookings, a person with bookings, and a booking that has a payment cannot be deleted (the API answers `409` with the reason). Payments and audit-log rows are never deleted by design (they are the records for the books); payments can be refunded.

## The JSON API

Base path `/api` (`GET /` answers `{"name":"HOTELGO API","status":"ok"}` so you can see the server is alive). Staff tokens are refused on guest endpoints and guest tokens on staff endpoints. Send `Authorization: Bearer <access token>` and JSON bodies.

| Area | Endpoints | Who |
|---|---|---|
| Sign in (staff) | `POST /auth/login`, `POST /auth/refresh`, `POST /auth/logout`, `GET /me`, `POST /me/password` | staff, owner (guests are refused here and use `/guest/login`) |
| Guest account | `POST /guest/register`, `/guest/verify`, `/guest/verify/resend`, `/guest/login`, `/guest/forgot`, `/guest/reset`; `GET /guest/me`, `POST /guest/password` | anyone / guest |
| Guest booking | `GET /guest/quote`, `POST /guest/bookings`, `GET /guest/bookings`, `GET /guest/bookings/{ref}`, `POST .../cancel`, `POST .../modify`, `GET/POST .../pay`, `POST .../requests`, `POST .../requests/{id}/pay`, `GET .../receipt`, `GET .../calendar` | signed-in guest |
| Public | `GET /room-types`, `GET /availability?stay=&date=&time=&hours=&guests=` | anyone |
| Dashboard | `GET /dashboard` | staff |
| Bookings | `GET /bookings`, `GET /bookings/{ref}`, `POST /bookings` (walk-in), `PUT /bookings/{ref}`, `POST /bookings/{ref}/action` (`checkin`, `checkout`, `cancel`, `resend`, `reassign`), `DELETE /bookings/{ref}` | staff (delete: owner) |
| Requests | `GET /requests?status=open`, `POST /bookings/{ref}/requests`, `PUT /requests/{id}` (`accept`, `decline`, `done`, `price`), `POST /requests/{id}/pay` (`cash`, `link`, `check`) | staff |
| Guests | `GET /guests`, `GET /guests/{id}`, `PUT /guests/{id}`, `DELETE /guests/{id}` | staff (delete: owner) |
| Room types | `GET /types` (staff); `POST`, `PUT /types/{id}`, `DELETE /types/{id}` | owner |
| Rooms | `GET /rooms` (staff); `POST`, `PUT /rooms/{id}`, `DELETE /rooms/{id}` | owner |
| Payments | `GET /payments`, `POST /payments/{id}/refund` | owner |
| Notifications | `GET /notifications`, `POST /notifications/{id}/retry`, `DELETE /notifications/{id}` | owner |
| Staff | `GET /staff`, `POST /staff`, `PUT /staff/{id}`, `DELETE /staff/{id}` | owner |
| Settings, reports, audit | `GET/PUT /settings`, `GET /reports`, `GET /audit` | owner |

Access tokens last 15 minutes, refresh tokens 7 days (stored hashed in `refresh_tokens`, rotated on every refresh). The role is read from the database on every request, so disabling a staff account cuts it off immediately. A staff account with a temporary password can only change its password until it does.

## Where the website is

The landing page, room search, booking, payment, "My bookings", receipts, the legal pages and the staff console are all in the React project `hotelgo-web`. This API only sends data. Links inside emails and SMS, and the PayMongo return address, are built from `FRONTEND_URL` (`front_url()` in `app/helpers/hotelgo_helper.php`), so set `FRONTEND_URL` to the React site's address.

## Run it on your computer (Laragon)

1. Copy `.env.example` to `.env` and fill it in (`APP_KEY`, database, `PAYMONGO_SECRET_KEY`, the email settings, `JWT_SECRET`, `REFRESH_TOKEN_KEY`, and `FRONTEND_URL=http://localhost:5173` for the React app). **Never commit `.env`.**
2. Create an empty database (for example `hotelgo`).
3. In the project folder: `composer install` (creates `vendor/`).
4. Build the tables with the migration command (see below): `php lava migration run`.
5. `php lava serve` (or `php -S localhost:8000 -t public`), then open `http://localhost:8000` (you will see the JSON status, not a page). The API is at `http://localhost:8000/api`. Run the React project (`hotelgo-web`) to see the website.
6. Staff sign in: on the React site, at the hidden address you chose when you built it (`STAFF_PATH`, e.g. `/#/secret1234567890`). First owner account `admin@hotelgo.ph`, temporary password `ChangeMe!2026`; you must change it at first sign-in.

## Database migrations

The tables are created by migration files in `app/migrations/` (000 to 019: the tables, the first hotel and owner account, and a clean-up of the old sample data).

```
php lava migration status                              # what is applied / pending
php lava migration run                                 # run pending migrations
php lava migration create-migration add_something      # new empty migration file
php lava migration rollback                            # undo the latest migration
php lava migration rollback-all                        # undo everything  (development database only!)
php lava migration refresh                             # rollback-all + run
```

CLI → `MigrationController` → routes (`/migrate`, `/status`, …) → `Migration` library → database. The browser routes only answer in the terminal, or when `MIGRATION_WEB=1` is in your local `.env`; on the real site they show a 404 page, because they change the database. To migrate the Aiven database, put the Aiven values in your local `.env` and run `php lava migration run` from your computer. `database/hotelgo.sql` is kept as a manual alternative.

## Deploy on Render

The `Dockerfile` installs PHP 8 + Apache and runs `composer install`. Add these environment variables in Render: everything in `.env.example` that applies, plus `JWT_SECRET` and `REFRESH_TOKEN_KEY` (two different random texts of 32+ characters) and `FRONTEND_URL` (the address of your React website, e.g. `https://hotelgo-web.onrender.com`, no slash at the end). Without `FRONTEND_URL` the browser blocks the React app from calling the API (CORS).

## Security notes

The API uses no cookies and no sessions, so there is nothing for CSRF to attack: every call carries a short-lived bearer token. Passwords are hashed. Sign-in and one-time codes are rate limited. Only the last 4 characters of an ID number are stored. Guest records are audited on every view. Card and e-wallet details never touch this server (they are handled on PayMongo's own pages).


## Real photos

Hotels, room types and the home page use **real photos** that are uploaded in the React console (no extra service, no files on the server).

- **Hotels / Room types:** open Edit, then *Upload photo*. A hotel owner can do this for their own hotel; the platform admin for any.
- **Home page:** platform admin, menu *Home photos* (top picture, banners, six gallery pictures).
- Where there is no photo the drawn picture is shown. A room type without its own photo shows its hotel's photo.
- Photos are shrunk in the browser (at most 1600 px, JPEG) and stored in the database table `images` (migration `016`). The public address is `GET /photo/{id}`; the API has `GET /api/photos` and `POST /api/manage/photos`.
- Each picture is checked on the server: real JPG / PNG / WebP, between 200 and 6000 pixels, at most about 700 KB.

## Many hotels on one platform

HOTELGO is a central booking platform: **many hotels in different parts of Puerto Galera** share one system.

| Who | `users.role` | `users.hotel_id` | What they see |
|---|---|---|---|
| Guest | guest | - | Every active hotel (search by area / hotel) |
| Front desk | staff | one hotel | Only that hotel's rooms, bookings, guests |
| Hotel owner | admin | one hotel | Same, plus payments, reports, staff and the hotel's own profile |
| Platform admin | admin | empty | Every hotel, the Hotels page and the platform settings |

* Tables: `hotels` (name, area, address, map position, contact, visible) and a `hotel_id` column on `room_types`, `rooms`, `bookings` and `users` (migrations `013`-`015`).
* Room numbers only have to be unique **inside one hotel**.
* Every query for staff goes through `app/support/Scope.php`, which limits it to the signed-in person's hotel.
* Public API: `GET /api/hotels`, `GET /api/availability?...&hotel=ID&area=Sabang`, `GET /api/room-types?hotel=ID`.
* Staff API: `GET/POST /api/manage/hotels`, `PUT/DELETE /api/manage/hotels/{id}`.
* `php lava migration run` on an existing database keeps all data. Migration `019` removes the old sample hotels, sample staff accounts and the sample VIP suite, but only those that were never used (no bookings, never signed in). The hotel you really use is never touched.
* The only account that comes with the system is the owner `admin@hotelgo.ph` (temporary password `ChangeMe!2026`, you must change it at the first sign-in). Add your hotels and staff on the **Hotels** and **Staff** pages.

## Online payment (PayMongo)

Set `PAYMONGO_SECRET_KEY` (and optionally `PAYMONGO_PUBLIC_KEY`); use the test keys first, then the live keys. Online booking stays closed until the key is set; there is no fake payment page. There is **no webhook secret**.
Guests pay with an e-wallet (GCash, Maya, GrabPay, ShopeePay: a PayMongo *Payment Intent*) or with a *payment link* (card, online banking, QR).
Paid or not is never taken from the browser: the system asks PayMongo (`PaymentModel::settle()`) when the guest returns, while the pay page polls `POST /api/guest/bookings/{ref}/pay` with `{"do":"check"}`, when a room hold ends, and when staff press "Check payment".

## Google Calendar sync (real, optional)

Every **paid** booking is added to the hotel's Google Calendar by itself, moved when the time or room changes, coloured green at check-in and grey at check-out, and **removed when it is cancelled or refunded**. Guests still get the built-in `.ics` file.

How it works: `app/libraries/Google_calendar.php` talks to the Google Calendar API v3 with a **service account** (a robot account, nobody has to sign in with Google). `app/models/CalendarModel.php` decides what must be in the calendar (paid = yes, everything else = no) and `BookingModel` calls it after every change. A Google problem never stops a booking.

Set it up once:

1. Google Cloud Console > create a project > **APIs & Services > Library > Google Calendar API > Enable**.
2. **IAM & Admin > Service Accounts > Create**, then open it > **Keys > Add key > Create new key > JSON**. A file is downloaded.
3. Put the **whole JSON** on one line in `GOOGLE_SERVICE_ACCOUNT_JSON` (`.env` locally, Render > Environment online). Never commit the file.
4. In Google Calendar open the hotel's calendar > **Settings and sharing > Share with specific people** > add the service account's email (`...@...iam.gserviceaccount.com`) with the permission **Make changes to events**.
5. In the same settings copy the **Calendar ID** (Integrate calendar). In the console open **Hotels > Edit** and paste it, press **Test connection**, then **Save** and **Send upcoming bookings**.
6. Optional: `GOOGLE_CALENDAR_ID` is the calendar used by a hotel that has no calendar of its own.
7. Run `php lava migration run` once (adds `hotels.calendar_id`, `bookings.gcal_event_id`, `bookings.gcal_synced_at`).

Console: **Settings > Integrations** shows whether it is connected, and **Hotels > Edit > Google Calendar** has the Test and Send buttons (API: `GET /api/calendar`, `POST /api/calendar/test`, `POST /api/calendar/sync`; hotel owner or platform admin only).

## VIP rooms and service requests

A **VIP room** looks like a normal room on the website, but it has a fixed higher price and a **package of services already included** (welcome drinks, breakfast in bed, private dining ...). The owner ticks *VIP / luxury room* in **Room types** and writes the package, one line each, as `Title: details`. The guest sees the package on the room card and on the booking page. Booking and room assignment stay automatic, exactly like a normal room.

A VIP guest can also **ask for something extra** (balcony set-up, karaoke, a chef menu ...). The request does not need the booking to be redone:

1. The guest sends it from the booking page of the React site (`#/booking/{ref}`), choosing from the list in `app/config/hotelgo.php` (`hotelgo_request_services`).
2. The staff gets a **bell notification** and a badge on **Requests** in the console. They **Accept** it (and may set a **price**), **Decline** it, then **Mark done**.
3. If there is a price, it is paid **in cash** at the desk or by a **PayMongo payment link** (the guest has a *Pay online* button). The money is saved as one more payment of the booking, so receipts and reports include it. Nothing is charged before the staff has confirmed the price.
4. The front desk can also add a request or an extra charge to any booking (Reservations > booking > Requests & extras).

Tables and code: `service_requests` (migration `018`), `ServiceRequestModel`, `ApiRequestController`, `ApiGuestBookingController::request_add`, React `pages/Requests.jsx`. Run `php lava migration run` once.  (rooms VIP1 and VIP2) to the first hotel; edit or delete it in **Room types**.
