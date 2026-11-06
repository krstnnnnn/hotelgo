# CRUD map (for the project checking)

Every table that holds data a person manages has Create, Read, Update and Delete through the API
(`app/config/routes.php`). The tables the system fills by itself are read-only on purpose.

| Table | Create | Read | Update | Delete | Model |
|---|---|---|---|---|---|
| hotels | `POST /manage/hotels` | `GET /manage/hotels`, `GET /hotels` | `PUT /manage/hotels/{id}` | `DELETE /manage/hotels/{id}` | HotelModel |
| room_types | `POST /types` | `GET /types` | `PUT /types/{id}` | `DELETE /types/{id}` | RoomTypeModel |
| rooms | `POST /rooms` | `GET /rooms` | `PUT /rooms/{id}` | `DELETE /rooms/{id}` | RoomModel |
| bookings | `POST /bookings` (front desk), `POST /guest/bookings` | `GET /bookings`, `GET /bookings/{ref}` | `PUT /bookings/{ref}`, `POST /bookings/{ref}/action` | `DELETE /bookings/{ref}` (cancelled / expired only) | BookingModel |
| users (guests) | `POST /guest/register`, Google sign-in | `GET /guests` | `PUT /guests/{id}` | `DELETE /guests/{id}` | UserModel |
| users (staff) | `POST /staff` | `GET /staff` | `PUT /staff/{id}` | `DELETE /staff/{id}` | UserModel |
| payments | created by a payment (`POST /bookings/{ref}/pay`) | `GET /payments` | `POST /payments/{id}/refund` | `DELETE /payments/{id}` (refunded records only) | PaymentModel |
| service_requests | `POST /bookings/{ref}/requests` | `GET /requests` | `PUT /requests/{id}` | `DELETE /requests/{id}` (not a paid one) | ServiceRequestModel |
| notifications | created by booking events | `GET /notifications` | `POST /notifications/{id}/retry` | `DELETE /notifications/{id}` | NotificationModel |
| settings | seeded | `GET /settings` | `PUT /settings` | n/a (fixed keys) | SettingModel |
| images | `POST /manage/photos` | `GET /photos`, `GET /photo/{id}` | replace by upload | `POST /manage/photos` (remove) | PhotoModel |
| audit_logs | written on every action | `GET /audit` | never (tamper-proof) | `DELETE /audit?days=90` (platform owner, entries older than 30+ days) | AuditModel |
| refresh_tokens, otp_codes, login_attempts | system (sign-in) | system | system | cleaned automatically | UserModel, OtpModel, LoginAttemptModel |

Rules that protect the records: a paid payment and a paid extra-service charge are never deleted (accounting evidence),
a room or room type that already has bookings is kept, and the audit log can only be cleared of old entries by the platform owner (and that clean-up is logged).
