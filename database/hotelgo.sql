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
  is_active TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rooms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  room_no VARCHAR(10) NOT NULL UNIQUE,
  room_type_id INT NOT NULL,
  floor INT NOT NULL DEFAULT 1,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  FOREIGN KEY (room_type_id) REFERENCES room_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (room_type_id) REFERENCES room_types(id),
  FOREIGN KEY (room_id) REFERENCES rooms(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX idx_bookings_room_time ON bookings (room_id, start_at, end_at);
CREATE INDEX idx_bookings_user ON bookings (user_id);
CREATE INDEX idx_bookings_status ON bookings (status);

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

CREATE TABLE IF NOT EXISTS settings (
  skey VARCHAR(60) NOT NULL PRIMARY KEY,
  svalue VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------- starter data ----------------
INSERT INTO settings (skey, svalue) VALUES
 ('motel_name','Coral Nest Motel'), ('motel_address','White Beach, Puerto Galera, Oriental Mindoro'),
 ('motel_lat','13.5188'), ('motel_lng','120.9647'), ('booking_fee','30'), ('hold_minutes','10'),
 ('cancel_hours','2'), ('overnight_start','20:00'), ('overnight_end','12:00');

INSERT INTO room_types (name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min) VALUES
 ('Standard Queen','Queen bed, aircon and hot shower.','Aircon, Hot shower, Wi-Fi',2,650,1000,1250,1500,15),
 ('Deluxe Sea View','Queen bed, private balcony and a window facing the bay.','Aircon, Balcony, Parking, Wi-Fi',3,950,1400,1800,2100,15),
 ('Family Suite','Two beds, TV and extra space for a family.','2 beds, Aircon, TV',5,1450,2100,2700,3200,20),
 ('Executive Suite','Large suite with a living area and mini fridge.','Aircon, TV, Mini fridge, Parking',4,1850,2600,3300,3800,20);

INSERT INTO rooms (room_no, room_type_id, floor) VALUES
 ('101',1,1),('102',1,1),('103',1,1),('104',1,1),('105',1,1),('106',1,1),
 ('201',2,2),('202',2,2),('203',2,2),('204',2,2),('205',2,2),('206',2,2),
 ('301',3,3),('302',3,3),('303',3,3),('304',3,3),('305',3,3),('306',3,3),
 ('401',4,4),('402',4,4),('403',4,4),('404',4,4),('405',4,4),('406',4,4);

-- First admin + front-desk staff. Temporary password: ChangeMe!2026  (they MUST change it at first login)
INSERT INTO users (full_name, email, mobile, password_hash, role, is_active, must_change_password, mobile_verified_at, created_at) VALUES
 ('HOTELGO Admin','admin@hotelgo.ph','+639170000001','$2y$10$vxyXlANjBTeIOTm7ij4wVO8GHECwFLqgg5tVKkhpt800.Rn8TH8Gi','admin',1,1,'2026-01-01 00:00:00','2026-01-01 00:00:00'),
 ('Rico Cruz','rico@hotelgo.ph','+639170000002','$2y$10$vxyXlANjBTeIOTm7ij4wVO8GHECwFLqgg5tVKkhpt800.Rn8TH8Gi','staff',1,1,'2026-01-01 00:00:00','2026-01-01 00:00:00');
