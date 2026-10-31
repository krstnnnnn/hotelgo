-- HOTELGO database. Run this whole file once (HeidiSQL -> File -> Run SQL file).
-- Every table uses utf8mb4. Passwords are stored as hashes (never plain text).

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  mobile VARCHAR(20) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(10) NOT NULL DEFAULT 'guest',
  is_active TINYINT NOT NULL DEFAULT 1,
  must_change_password TINYINT NOT NULL DEFAULT 0,
  mobile_verified_at DATETIME NULL,
  staff_note VARCHAR(500) NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  hotel_id INT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hotels (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(80) NOT NULL UNIQUE,
  area VARCHAR(80) NULL,
  address VARCHAR(255) NULL,
  description VARCHAR(500) NULL,
  lat DECIMAL(10,6) NOT NULL DEFAULT 13.5188,
  lng DECIMAL(10,6) NOT NULL DEFAULT 120.9647,
  phone VARCHAR(30) NULL,
  email VARCHAR(150) NULL,
  is_active TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  image_id INT NULL,
  calendar_id VARCHAR(190) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  mime VARCHAR(30) NOT NULL,
  bytes INT NOT NULL,
  data LONGTEXT NOT NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS room_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  description VARCHAR(500) NULL,
  amenities VARCHAR(250) NULL,
  max_guests INT NOT NULL DEFAULT 2,
  price_3h DECIMAL(10,2) NOT NULL,
  price_6h DECIMAL(10,2) NOT NULL,
  price_12h DECIMAL(10,2) NOT NULL,
  price_overnight DECIMAL(10,2) NOT NULL,
  cleaning_buffer_min INT NOT NULL DEFAULT 15,
  is_active TINYINT NOT NULL DEFAULT 1,
  hotel_id INT NULL,
  image_id INT NULL,
  is_vip TINYINT NOT NULL DEFAULT 0,
  included_services TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rooms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  room_no VARCHAR(10) NOT NULL,
  room_type_id INT NOT NULL,
  floor INT NOT NULL DEFAULT 1,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  hotel_id INT NULL,
  FOREIGN KEY (room_type_id) REFERENCES room_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE UNIQUE INDEX hotel_room_no_unique ON rooms (hotel_id, room_no);

CREATE TABLE IF NOT EXISTS bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_ref VARCHAR(12) NOT NULL UNIQUE,
  user_id INT NULL,
  room_type_id INT NOT NULL,
  room_id INT NULL,
  stay_type VARCHAR(10) NOT NULL,
  hours INT NOT NULL DEFAULT 0,
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  guests INT NOT NULL DEFAULT 1,
  guest_name VARCHAR(120) NOT NULL,
  guest_mobile VARCHAR(20) NOT NULL,
  guest_email VARCHAR(150) NOT NULL DEFAULT '',
  id_type VARCHAR(30) NOT NULL DEFAULT '',
  id_last4 VARCHAR(4) NOT NULL DEFAULT '',
  room_rate DECIMAL(10,2) NOT NULL,
  booking_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL,
  status VARCHAR(20) NOT NULL,
  pay_method VARCHAR(20) NOT NULL DEFAULT '',
  pay_ref VARCHAR(100) NULL,
  source VARCHAR(10) NOT NULL DEFAULT 'online',
  hold_expires_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  cancelled_at DATETIME NULL,
  checked_in_at DATETIME NULL,
  checked_out_at DATETIME NULL,
  hotel_id INT NULL,
  gcal_event_id VARCHAR(64) NULL,
  gcal_synced_at DATETIME NULL,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (room_type_id) REFERENCES room_types(id),
  FOREIGN KEY (room_id) REFERENCES rooms(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX idx_bookings_room_time ON bookings (room_id, start_at, end_at);
CREATE INDEX idx_bookings_user ON bookings (user_id);
CREATE INDEX idx_bookings_status ON bookings (status);
CREATE INDEX bookings_hotel ON bookings (hotel_id);

CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NOT NULL,
  txn_ref VARCHAR(40) NOT NULL UNIQUE,
  method VARCHAR(20) NOT NULL,
  provider VARCHAR(20) NOT NULL,
  provider_ref VARCHAR(100) NULL,
  amount DECIMAL(10,2) NOT NULL,
  status VARCHAR(20) NOT NULL,
  created_at DATETIME NOT NULL,
  paid_at DATETIME NULL,
  refunded_at DATETIME NULL,
  FOREIGN KEY (booking_id) REFERENCES bookings(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS service_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NOT NULL,
  hotel_id INT NULL,
  service VARCHAR(120) NOT NULL,
  note VARCHAR(500) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'new',
  price DECIMAL(10,2) NULL,
  pay_ref VARCHAR(80) NULL,
  paid_at DATETIME NULL,
  source VARCHAR(10) NOT NULL DEFAULT 'guest',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  handled_by INT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX idx_requests_hotel_status ON service_requests (hotel_id, status);
CREATE INDEX idx_requests_booking ON service_requests (booking_id);

CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NULL,
  user_id INT NULL,
  channel VARCHAR(10) NOT NULL,
  recipient VARCHAR(150) NOT NULL,
  subject VARCHAR(150) NULL,
  message TEXT NOT NULL,
  status VARCHAR(20) NOT NULL,
  provider_ref VARCHAR(100) NULL,
  error VARCHAR(250) NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(60) NOT NULL,
  detail VARCHAR(250) NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(150) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  success TINYINT NOT NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX idx_attempts_email ON login_attempts (email, created_at);

CREATE TABLE IF NOT EXISTS otp_codes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  purpose VARCHAR(10) NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS refresh_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  jti VARCHAR(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX idx_refresh_token ON refresh_tokens (token);
CREATE INDEX idx_refresh_user ON refresh_tokens (user_id);

CREATE TABLE IF NOT EXISTS settings (
  skey VARCHAR(60) NOT NULL PRIMARY KEY,
  svalue VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------- starter data ----------------
INSERT INTO settings (skey, svalue) VALUES
 ('motel_name','Coral Nest Motel'), ('motel_address','White Beach, Puerto Galera, Oriental Mindoro'),
 ('motel_lat','13.5188'), ('motel_lng','120.9647'), ('booking_fee','30'), ('hold_minutes','10'),
 ('cancel_hours','2'), ('overnight_start','20:00'), ('overnight_end','12:00');

INSERT INTO hotels (name, slug, area, address, description, lat, lng, phone, email, is_active, created_at) VALUES
 ('Coral Nest Motel','coral-nest-motel','White Beach','White Beach, Puerto Galera, Oriental Mindoro','Simple, clean rooms a short walk from the beach.',13.5188,120.9647,'+639708655964','hotelgoph@gmail.com',1,'2026-01-01 00:00:00');

INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min) VALUES
 (1,'Standard Queen','Queen bed, aircon and hot shower.','Aircon, Hot shower, Wi-Fi',2,650,1000,1250,1500,15),
 (1,'Deluxe Sea View','Queen bed, private balcony and a window facing the bay.','Aircon, Balcony, Parking, Wi-Fi',3,950,1400,1800,2100,15),
 (1,'Family Suite','Two beds, TV and extra space for a family.','2 beds, Aircon, TV',5,1450,2100,2700,3200,20),
 (1,'Executive Suite','Large suite with a living area and mini fridge.','Aircon, TV, Mini fridge, Parking',4,1850,2600,3300,3800,20);

INSERT INTO rooms (hotel_id, room_no, room_type_id, floor) VALUES
 (1,'101',1,1),(1,'102',1,1),(1,'103',1,1),(1,'104',1,1),(1,'105',1,1),(1,'106',1,1),
 (1,'201',2,2),(1,'202',2,2),(1,'203',2,2),(1,'204',2,2),(1,'205',2,2),(1,'206',2,2),
 (1,'301',3,3),(1,'302',3,3),(1,'303',3,3),(1,'304',3,3),(1,'305',3,3),(1,'306',3,3),
 (1,'401',4,4),(1,'402',4,4),(1,'403',4,4),(1,'404',4,4),(1,'405',4,4),(1,'406',4,4);

-- The first owner account (platform admin: sees every hotel). Temporary password: ChangeMe!2026  (it MUST be changed at first login)
INSERT INTO users (full_name, email, mobile, password_hash, role, is_active, must_change_password, mobile_verified_at, created_at, hotel_id) VALUES
 ('HOTELGO Admin','admin@hotelgo.ph','+639170000001','$2y$10$vxyXlANjBTeIOTm7ij4wVO8GHECwFLqgg5tVKkhpt800.Rn8TH8Gi','admin',1,1,'2026-01-01 00:00:00','2026-01-01 00:00:00',NULL);
