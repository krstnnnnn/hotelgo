# HOTELGO

Motel booking and room management for Puerto Galera, built on **LavaLust 4.7** (PHP 8). Guests search and book by the hour or overnight, pay online, and get a ticket with a QR code. The front desk and the owner run the motel from a staff area. Team 3F1: Bunquin, Castro, Evora, Gayuso (ITE311).

## How the pieces fit

```
Browser ── pretty pages (PHP views) ──► Routes ─► Middleware ─► Controller ─► Model ─► MySQL
Browser ── React console  /panel     ─► /api (JWT) ─► ApiController ─────────┘
```

| Layer | Where | What it does |
|---|---|---|
| Routes | `app/config/routes.php` | One line per URL; middleware (`csrf`, `guest`, `auth`, `staff`, `admin`) guards groups. |
| Controllers | `app/controllers/` | Read the request, call models, send a view or JSON. No SQL. |
| Models | `app/models/` | All database work through the query builder (`$this->db->table(...)`). One model per table. Raw SQL only for two `SELECT … FOR UPDATE` locks in `BookingModel`. |
| Libraries | `app/libraries/` | Reusable services loaded with `$this->call->library('name')`: `Paymongo`, `Messenger`, `Http_client`, `Qr`, `Integrations`. Settings come from `app/config/integrations.php` (`config_item()`), which reads `.env`. |
| Support classes | `app/support/` | Small pure helpers (`Money`, `Phone`, `Csv`, `Ics`) autoloaded by **Composer** (`composer.json`, `composer_autoload = TRUE`). |
| Views | `app/views/` | Server-rendered pages for guests and the classic staff area. |
| API | `app/controllers/ApiController.php` | JSON endpoints built on LavaLust's `Api` library (JWT access + refresh tokens). |
| React console | `frontend/` → built into `public/panel/` | Staff and owner console that only talks to `/api`. |

### Models and what they own

`UserModel` (guests, staff, owner) · `RoomTypeModel` · `RoomModel` · `BookingModel` (availability, booking rules, staff actions) · `PaymentModel` · `NotificationModel` · `SettingModel` · `AuditModel` · `LoginAttemptModel` · `OtpModel`.

Every table has create, read, update and delete. Delete rules protect history: a room type with rooms or bookings, a room with bookings, a person with bookings, and a booking that has a payment cannot be deleted (the API answers `409` with the reason). Payments and audit-log rows are never deleted by design (they are the records for the books); payments can be refunded.

## The JSON API

Base path `/api`. Send `Authorization: Bearer <access token>` and JSON bodies.

| Area | Endpoints | Who |
|---|---|---|
| Sign in | `POST /auth/login`, `POST /auth/refresh`, `POST /auth/logout`, `GET /me`, `POST /me/password` | staff, owner |
| Public | `GET /room-types`, `GET /availability?stay=&date=&time=&hours=&guests=` | anyone |
| Dashboard | `GET /dashboard` | staff |
| Bookings | `GET /bookings`, `GET /bookings/{ref}`, `POST /bookings` (walk-in), `PUT /bookings/{ref}`, `POST /bookings/{ref}/action` (`checkin`, `checkout`, `cancel`, `resend`, `reassign`), `DELETE /bookings/{ref}` | staff (delete: owner) |
| Guests | `GET /guests`, `GET /guests/{id}`, `PUT /guests/{id}`, `DELETE /guests/{id}` | staff (delete: owner) |
| Room types | `GET /types` (staff); `POST`, `PUT /types/{id}`, `DELETE /types/{id}` | owner |
| Rooms | `GET /rooms` (staff); `POST`, `PUT /rooms/{id}`, `DELETE /rooms/{id}` | owner |
| Payments | `GET /payments`, `POST /payments/{id}/refund` | owner |
| Notifications | `GET /notifications`, `POST /notifications/{id}/retry`, `DELETE /notifications/{id}` | owner |
| Staff | `GET /staff`, `POST /staff`, `PUT /staff/{id}`, `DELETE /staff/{id}` | owner |
| Settings, reports, audit | `GET/PUT /settings`, `GET /reports`, `GET /audit` | owner |

Access tokens last 15 minutes, refresh tokens 7 days (stored hashed in `refresh_tokens`, rotated on every refresh). The role is read from the database on every request, so disabling a staff account cuts it off immediately. A staff account with a temporary password can only change its password until it does.

## Landing page

`app/views/home/index.php` (sections: hero + search, about, quote, rooms from the database, what we offer, how it works, map, gallery with viewer, guest voices, FAQ, contact) with styles in `public/assets/css/app.css` and behaviour in `public/assets/js/app.js` (no external scripts). Pictures are SVG scenes in `public/assets/img/`: replace them with real photos of the same name or edit the `<img>` paths. Contact details and map places are in `app/config/hotelgo.php` (`hotelgo_contact_*`, `hotelgo_places`, `hotelgo_areas`). Legal pages: `/terms`, `/privacy`, `/cancellation-policy`, `/house-rules` (text in `app/views/home/legal.php`). The guest-voices comments are labelled samples until real reviews exist.

## Run it on your computer (Laragon)

1. Copy `.env.example` to `.env` and fill it in (`APP_KEY`, database, and the two API secrets `JWT_SECRET` and `REFRESH_TOKEN_KEY`). **Never commit `.env`.**
2. Create the database and run `database/hotelgo.sql` (HeidiSQL → File → Run SQL file). If you already have a database from an older version, run `database/update_api.sql` once instead.
3. In the project folder run `composer install` (creates `vendor/`).
4. `php -S localhost:8000 -t public`, then open `http://localhost:8000`.
5. Staff area: the hidden address you set in `STAFF_LOGIN_PATH` (on your own computer, with it empty, `/admin/login` still works; on the real site no address = staff page off) (first owner account `admin@hotelgo.ph`, temporary password `ChangeMe!2026`, you must change it at first sign-in). React console: `/panel/`.

## React console (frontend/)

The built files are committed in `public/panel/`, so the server needs no Node. To change the console:

```
cd frontend
npm install
npm run build      # writes ../public/panel/  (npm run watch while editing)
```

Source is in `frontend/src/` (React 19, bundled with esbuild). It keeps the access token in memory and the refresh token in `sessionStorage`.

## Deploy on Render

The `Dockerfile` installs PHP 8 + Apache and runs `composer install`. Add these environment variables in Render: everything in `.env.example` that applies, plus `JWT_SECRET` and `REFRESH_TOKEN_KEY` (two different random texts of 32+ characters). Without them only `/api` refuses to start; the rest of the site keeps working.

## Security notes

Sessions use strict, HTTP-only cookies. All forms carry a CSRF token (the API uses bearer tokens instead). Passwords are hashed. Sign-in and one-time codes are rate limited. Only the last 4 characters of an ID number are stored. Guest records are audited on every view. Card and e-wallet details never touch this server (PayMongo hosted checkout).
