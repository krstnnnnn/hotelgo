-- HOTELGO extra data: more hotels in Puerto Galera, more rooms, and VIP rooms for every hotel.
-- HOW TO USE: run this file ONCE on your MySQL database (Aiven: Query tool, or:  mysql -h HOST -P PORT -u USER -p DBNAME < more_hotels.sql).
-- It is safe to run again: hotels are matched by their slug, room types by name, rooms by room number, so nothing is duplicated.
-- The hotel names are made up. Change names, phones, e-mails and prices in the staff console (Hotels / Room types) or here before running.
-- Every hotel gets the e-mail below: service requests of VIP guests are e-mailed to it. Replace it with the hotel's own e-mail.
-- Pictures are not included: upload them in the console (Hotels > Edit > Upload photo); until then a drawn picture is shown.

-- ===== Coral Nest Motel (already in your data): VIP rooms added =====
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'VIP Ocean Villa', 'Large villa-style room with a sofa, mini bar and a private balcony facing the sea. Fixed VIP price, services included.', 'Aircon, Smart TV, Mini bar, Balcony, Wi-Fi', 4, 3200, 4500, 5600, 6500, 20, 1, 1, 'Welcome drinks: two on arrival
Breakfast in bed: for two, once per stay
Late checkout: until 2 PM, subject to availability
Priority desk: fast check-in with a dedicated attendant'
FROM hotels h WHERE h.slug = 'coral-nest-motel' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'VIP Ocean Villa');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'VIP Honeymoon Suite', 'King bed, bathtub and candle-lit set-up for couples.', 'Aircon, King bed, Bathtub, Smart TV, Wi-Fi', 2, 3500, 4800, 6000, 7000, 20, 1, 1, 'Romantic set-up: flowers and candles
Bottle of wine or juice on arrival
Breakfast in bed: for two
Late checkout: until 2 PM, subject to availability'
FROM hotels h WHERE h.slug = 'coral-nest-motel' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'VIP Honeymoon Suite');

INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '501', t.id, 5, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Ocean Villa' WHERE h.slug = 'coral-nest-motel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '502', t.id, 5, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Ocean Villa' WHERE h.slug = 'coral-nest-motel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '503', t.id, 5, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Ocean Villa' WHERE h.slug = 'coral-nest-motel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '504', t.id, 5, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Honeymoon Suite' WHERE h.slug = 'coral-nest-motel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '505', t.id, 5, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Honeymoon Suite' WHERE h.slug = 'coral-nest-motel';

-- ===== Sabang Sunrise Inn (Sabang) =====
INSERT IGNORE INTO hotels (name, slug, area, address, description, lat, lng, phone, email, is_active, created_at) VALUES
 ('Sabang Sunrise Inn','sabang-sunrise-inn','Sabang','Sabang Beach Road, Sabang, Puerto Galera, Oriental Mindoro','Budget-friendly inn a few steps from the Sabang nightlife strip and dive shops.',13.5258,120.9513,'+639708655964','hotelgoph@gmail.com',1,'2026-11-03 00:00:00');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'Standard Double', 'Double bed, fan or aircon, private bathroom.', 'Aircon, Hot shower, Wi-Fi', 2, 500, 800, 1000, 1200, 15, 1, 0, NULL
FROM hotels h WHERE h.slug = 'sabang-sunrise-inn' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'Standard Double');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'Deluxe Queen', 'Queen bed, TV and a small veranda.', 'Aircon, TV, Veranda, Wi-Fi', 3, 750, 1150, 1450, 1750, 15, 1, 0, NULL
FROM hotels h WHERE h.slug = 'sabang-sunrise-inn' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'Deluxe Queen');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'Family Room', 'Two double beds for the whole family.', '2 beds, Aircon, TV', 5, 1100, 1650, 2100, 2500, 20, 1, 0, NULL
FROM hotels h WHERE h.slug = 'sabang-sunrise-inn' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'Family Room');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'VIP Ocean Villa', 'Corner room with a sofa, mini bar and a view of the bay. Fixed VIP price, services included.', 'Aircon, Smart TV, Mini bar, Balcony, Wi-Fi', 4, 2800, 4000, 5000, 5800, 20, 1, 1, 'Welcome drinks: two on arrival
Breakfast in bed: for two, once per stay
Late checkout: until 2 PM, subject to availability
Priority desk: fast check-in with a dedicated attendant'
FROM hotels h WHERE h.slug = 'sabang-sunrise-inn' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'VIP Ocean Villa');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'VIP Honeymoon Suite', 'King bed, bathtub and a romantic set-up.', 'Aircon, King bed, Bathtub, Smart TV, Wi-Fi', 2, 3000, 4200, 5200, 6200, 20, 1, 1, 'Romantic set-up: flowers and candles
Bottle of wine or juice on arrival
Breakfast in bed: for two
Late checkout: until 2 PM, subject to availability'
FROM hotels h WHERE h.slug = 'sabang-sunrise-inn' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'VIP Honeymoon Suite');

INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '101', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Standard Double' WHERE h.slug = 'sabang-sunrise-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '102', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Standard Double' WHERE h.slug = 'sabang-sunrise-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '103', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Standard Double' WHERE h.slug = 'sabang-sunrise-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '104', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Standard Double' WHERE h.slug = 'sabang-sunrise-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '105', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Standard Double' WHERE h.slug = 'sabang-sunrise-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '106', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Standard Double' WHERE h.slug = 'sabang-sunrise-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '107', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Standard Double' WHERE h.slug = 'sabang-sunrise-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '108', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Standard Double' WHERE h.slug = 'sabang-sunrise-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '201', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Deluxe Queen' WHERE h.slug = 'sabang-sunrise-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '202', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Deluxe Queen' WHERE h.slug = 'sabang-sunrise-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '203', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Deluxe Queen' WHERE h.slug = 'sabang-sunrise-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '204', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Deluxe Queen' WHERE h.slug = 'sabang-sunrise-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '205', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Deluxe Queen' WHERE h.slug = 'sabang-sunrise-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '206', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Deluxe Queen' WHERE h.slug = 'sabang-sunrise-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '207', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Family Room' WHERE h.slug = 'sabang-sunrise-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '208', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Family Room' WHERE h.slug = 'sabang-sunrise-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '209', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Family Room' WHERE h.slug = 'sabang-sunrise-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '210', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Family Room' WHERE h.slug = 'sabang-sunrise-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '301', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Ocean Villa' WHERE h.slug = 'sabang-sunrise-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '302', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Ocean Villa' WHERE h.slug = 'sabang-sunrise-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '303', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Ocean Villa' WHERE h.slug = 'sabang-sunrise-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '304', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Honeymoon Suite' WHERE h.slug = 'sabang-sunrise-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '305', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Honeymoon Suite' WHERE h.slug = 'sabang-sunrise-inn';

-- ===== Aninuan Breeze Lodge (Aninuan) =====
INSERT IGNORE INTO hotels (name, slug, area, address, description, lat, lng, phone, email, is_active, created_at) VALUES
 ('Aninuan Breeze Lodge','aninuan-breeze-lodge','Aninuan','Aninuan Beach, Puerto Galera, Oriental Mindoro','Quiet beachfront lodge in Aninuan with cottages under the coconut trees.',13.5088,120.9381,'+639708655964','hotelgoph@gmail.com',1,'2026-11-03 00:00:00');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'Garden Cottage', 'Cosy cottage with a queen bed and a small garden.', 'Aircon, Hot shower, Garden, Wi-Fi', 2, 600, 950, 1200, 1400, 15, 1, 0, NULL
FROM hotels h WHERE h.slug = 'aninuan-breeze-lodge' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'Garden Cottage');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'Beachfront Cottage', 'Steps from the sand with a hammock on the porch.', 'Aircon, Porch, Hammock, Wi-Fi', 3, 950, 1450, 1850, 2200, 15, 1, 0, NULL
FROM hotels h WHERE h.slug = 'aninuan-breeze-lodge' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'Beachfront Cottage');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'Family Villa', 'Two bedrooms and a kitchenette.', '2 bedrooms, Aircon, Kitchenette, TV', 6, 1700, 2500, 3200, 3800, 20, 1, 0, NULL
FROM hotels h WHERE h.slug = 'aninuan-breeze-lodge' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'Family Villa');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'VIP Pool Suite', 'Private plunge pool, outdoor shower and sea view. Fixed VIP price, services included.', 'Aircon, Plunge pool, Outdoor shower, Smart TV, Wi-Fi', 3, 3600, 5000, 6300, 7400, 20, 1, 1, 'Private plunge pool: yours for the whole stay
Welcome drinks: two on arrival
Breakfast in bed: for two
Beach towels and snorkel gear'
FROM hotels h WHERE h.slug = 'aninuan-breeze-lodge' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'VIP Pool Suite');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'VIP Honeymoon Suite', 'King bed, bathtub and flowers on arrival.', 'Aircon, King bed, Bathtub, Smart TV, Wi-Fi', 2, 3300, 4600, 5700, 6700, 20, 1, 1, 'Romantic set-up: flowers and candles
Bottle of wine or juice on arrival
Breakfast in bed: for two
Late checkout: until 2 PM, subject to availability'
FROM hotels h WHERE h.slug = 'aninuan-breeze-lodge' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'VIP Honeymoon Suite');

INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '101', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Garden Cottage' WHERE h.slug = 'aninuan-breeze-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '102', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Garden Cottage' WHERE h.slug = 'aninuan-breeze-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '103', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Garden Cottage' WHERE h.slug = 'aninuan-breeze-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '104', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Garden Cottage' WHERE h.slug = 'aninuan-breeze-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '105', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Garden Cottage' WHERE h.slug = 'aninuan-breeze-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '106', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Garden Cottage' WHERE h.slug = 'aninuan-breeze-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '107', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Beachfront Cottage' WHERE h.slug = 'aninuan-breeze-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '108', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Beachfront Cottage' WHERE h.slug = 'aninuan-breeze-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '109', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Beachfront Cottage' WHERE h.slug = 'aninuan-breeze-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '110', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Beachfront Cottage' WHERE h.slug = 'aninuan-breeze-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '111', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Beachfront Cottage' WHERE h.slug = 'aninuan-breeze-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '201', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Family Villa' WHERE h.slug = 'aninuan-breeze-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '202', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Family Villa' WHERE h.slug = 'aninuan-breeze-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '203', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Family Villa' WHERE h.slug = 'aninuan-breeze-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '204', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Pool Suite' WHERE h.slug = 'aninuan-breeze-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '205', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Pool Suite' WHERE h.slug = 'aninuan-breeze-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '206', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Pool Suite' WHERE h.slug = 'aninuan-breeze-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '207', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Honeymoon Suite' WHERE h.slug = 'aninuan-breeze-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '208', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Honeymoon Suite' WHERE h.slug = 'aninuan-breeze-lodge';

-- ===== Talipanan Cove Resort (Talipanan) =====
INSERT IGNORE INTO hotels (name, slug, area, address, description, lat, lng, phone, email, is_active, created_at) VALUES
 ('Talipanan Cove Resort','talipanan-cove-resort','Talipanan','Talipanan Beach, Puerto Galera, Oriental Mindoro','Secluded cove resort with calm water, ideal for families and quiet getaways.',13.4871,120.9054,'+639708655964','hotelgoph@gmail.com',1,'2026-11-03 00:00:00');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'Cove Standard', 'Queen bed with aircon and hot shower.', 'Aircon, Hot shower, Wi-Fi', 2, 700, 1100, 1400, 1650, 15, 1, 0, NULL
FROM hotels h WHERE h.slug = 'talipanan-cove-resort' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'Cove Standard');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'Cove Deluxe', 'Larger room with a balcony facing the cove.', 'Aircon, Balcony, TV, Wi-Fi', 3, 1050, 1550, 1950, 2350, 15, 1, 0, NULL
FROM hotels h WHERE h.slug = 'talipanan-cove-resort' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'Cove Deluxe');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'Family Suite', 'Two beds, living area and extra space.', '2 beds, Aircon, TV, Mini fridge', 5, 1600, 2300, 2900, 3400, 20, 1, 0, NULL
FROM hotels h WHERE h.slug = 'talipanan-cove-resort' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'Family Suite');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'Executive Suite', 'Living area, mini fridge and parking.', 'Aircon, TV, Mini fridge, Parking', 4, 1900, 2700, 3400, 3950, 20, 1, 0, NULL
FROM hotels h WHERE h.slug = 'talipanan-cove-resort' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'Executive Suite');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'VIP Ocean Villa', 'Villa with sofa, mini bar and a wide sea view. Fixed VIP price, services included.', 'Aircon, Smart TV, Mini bar, Balcony, Wi-Fi', 4, 3400, 4700, 5900, 6800, 20, 1, 1, 'Welcome drinks: two on arrival
Breakfast in bed: for two, once per stay
Late checkout: until 2 PM, subject to availability
Priority desk: fast check-in with a dedicated attendant'
FROM hotels h WHERE h.slug = 'talipanan-cove-resort' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'VIP Ocean Villa');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'VIP Pool Suite', 'Private plunge pool and outdoor shower. Fixed VIP price, services included.', 'Aircon, Plunge pool, Smart TV, Wi-Fi', 3, 3800, 5200, 6500, 7600, 20, 1, 1, 'Private plunge pool: yours for the whole stay
Welcome drinks: two on arrival
Breakfast in bed: for two
Beach towels and snorkel gear'
FROM hotels h WHERE h.slug = 'talipanan-cove-resort' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'VIP Pool Suite');

INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '101', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Cove Standard' WHERE h.slug = 'talipanan-cove-resort';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '102', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Cove Standard' WHERE h.slug = 'talipanan-cove-resort';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '103', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Cove Standard' WHERE h.slug = 'talipanan-cove-resort';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '104', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Cove Standard' WHERE h.slug = 'talipanan-cove-resort';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '105', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Cove Standard' WHERE h.slug = 'talipanan-cove-resort';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '106', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Cove Standard' WHERE h.slug = 'talipanan-cove-resort';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '201', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Cove Deluxe' WHERE h.slug = 'talipanan-cove-resort';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '202', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Cove Deluxe' WHERE h.slug = 'talipanan-cove-resort';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '203', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Cove Deluxe' WHERE h.slug = 'talipanan-cove-resort';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '204', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Cove Deluxe' WHERE h.slug = 'talipanan-cove-resort';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '205', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Cove Deluxe' WHERE h.slug = 'talipanan-cove-resort';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '206', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Cove Deluxe' WHERE h.slug = 'talipanan-cove-resort';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '207', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Family Suite' WHERE h.slug = 'talipanan-cove-resort';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '208', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Family Suite' WHERE h.slug = 'talipanan-cove-resort';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '209', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Family Suite' WHERE h.slug = 'talipanan-cove-resort';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '210', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Family Suite' WHERE h.slug = 'talipanan-cove-resort';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '301', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Executive Suite' WHERE h.slug = 'talipanan-cove-resort';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '302', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Executive Suite' WHERE h.slug = 'talipanan-cove-resort';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '303', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Executive Suite' WHERE h.slug = 'talipanan-cove-resort';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '401', t.id, 4, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Ocean Villa' WHERE h.slug = 'talipanan-cove-resort';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '402', t.id, 4, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Ocean Villa' WHERE h.slug = 'talipanan-cove-resort';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '403', t.id, 4, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Ocean Villa' WHERE h.slug = 'talipanan-cove-resort';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '404', t.id, 4, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Pool Suite' WHERE h.slug = 'talipanan-cove-resort';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '405', t.id, 4, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Pool Suite' WHERE h.slug = 'talipanan-cove-resort';

-- ===== Poblacion Harbor Hotel (Poblacion) =====
INSERT IGNORE INTO hotels (name, slug, area, address, description, lat, lng, phone, email, is_active, created_at) VALUES
 ('Poblacion Harbor Hotel','poblacion-harbor-hotel','Poblacion','Poblacion, Puerto Galera, Oriental Mindoro','Town hotel near the Muelle port, the market and the ferry terminal.',13.5001,120.9546,'+639708655964','hotelgoph@gmail.com',1,'2026-11-03 00:00:00');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'Standard Twin', 'Two single beds, aircon and hot shower.', 'Aircon, Hot shower, Wi-Fi', 2, 550, 850, 1100, 1300, 15, 1, 0, NULL
FROM hotels h WHERE h.slug = 'poblacion-harbor-hotel' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'Standard Twin');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'Standard Queen', 'Queen bed, TV and hot shower.', 'Aircon, TV, Hot shower, Wi-Fi', 2, 650, 1000, 1250, 1500, 15, 1, 0, NULL
FROM hotels h WHERE h.slug = 'poblacion-harbor-hotel' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'Standard Queen');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'Deluxe Room', 'Spacious room with a work desk and a city view.', 'Aircon, TV, Work desk, Wi-Fi', 3, 900, 1350, 1700, 2000, 15, 1, 0, NULL
FROM hotels h WHERE h.slug = 'poblacion-harbor-hotel' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'Deluxe Room');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'Family Suite', 'Two beds and a sofa.', '2 beds, Aircon, TV', 5, 1400, 2050, 2600, 3100, 20, 1, 0, NULL
FROM hotels h WHERE h.slug = 'poblacion-harbor-hotel' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'Family Suite');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'VIP Ocean Villa', 'Top-floor suite with a harbour view, mini bar and lounge. Fixed VIP price, services included.', 'Aircon, Smart TV, Mini bar, Lounge, Wi-Fi', 4, 2900, 4100, 5100, 5900, 20, 1, 1, 'Welcome drinks: two on arrival
Breakfast in bed: for two, once per stay
Late checkout: until 2 PM, subject to availability
Priority desk: fast check-in with a dedicated attendant'
FROM hotels h WHERE h.slug = 'poblacion-harbor-hotel' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'VIP Ocean Villa');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'VIP Honeymoon Suite', 'King bed, bathtub and romantic set-up.', 'Aircon, King bed, Bathtub, Smart TV, Wi-Fi', 2, 3100, 4300, 5300, 6300, 20, 1, 1, 'Romantic set-up: flowers and candles
Bottle of wine or juice on arrival
Breakfast in bed: for two
Late checkout: until 2 PM, subject to availability'
FROM hotels h WHERE h.slug = 'poblacion-harbor-hotel' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'VIP Honeymoon Suite');

INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '101', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Standard Twin' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '102', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Standard Twin' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '103', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Standard Twin' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '104', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Standard Twin' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '105', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Standard Twin' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '106', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Standard Twin' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '107', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Standard Twin' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '108', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Standard Twin' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '109', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Standard Twin' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '110', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Standard Twin' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '201', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Standard Queen' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '202', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Standard Queen' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '203', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Standard Queen' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '204', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Standard Queen' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '205', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Standard Queen' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '206', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Standard Queen' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '207', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Standard Queen' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '208', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Standard Queen' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '209', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Standard Queen' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '210', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Standard Queen' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '301', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Deluxe Room' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '302', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Deluxe Room' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '303', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Deluxe Room' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '304', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Deluxe Room' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '305', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Deluxe Room' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '306', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Deluxe Room' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '307', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Deluxe Room' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '308', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Deluxe Room' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '309', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Family Suite' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '310', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Family Suite' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '311', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Family Suite' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '312', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Family Suite' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '313', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Family Suite' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '401', t.id, 4, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Ocean Villa' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '402', t.id, 4, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Ocean Villa' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '403', t.id, 4, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Ocean Villa' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '404', t.id, 4, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Ocean Villa' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '405', t.id, 4, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Honeymoon Suite' WHERE h.slug = 'poblacion-harbor-hotel';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '406', t.id, 4, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Honeymoon Suite' WHERE h.slug = 'poblacion-harbor-hotel';

-- ===== La Laguna Bayview Inn (Big La Laguna) =====
INSERT IGNORE INTO hotels (name, slug, area, address, description, lat, lng, phone, email, is_active, created_at) VALUES
 ('La Laguna Bayview Inn','la-laguna-bayview-inn','Big La Laguna','Big La Laguna Beach, Puerto Galera, Oriental Mindoro','Relaxed inn on Big La Laguna beach, close to the restaurants and the snorkelling spots.',13.5246,120.9692,'+639708655964','hotelgoph@gmail.com',1,'2026-11-03 00:00:00');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'Bay Standard', 'Queen bed, aircon and a terrace.', 'Aircon, Terrace, Hot shower, Wi-Fi', 2, 650, 1000, 1250, 1500, 15, 1, 0, NULL
FROM hotels h WHERE h.slug = 'la-laguna-bayview-inn' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'Bay Standard');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'Bay Deluxe', 'Bigger room facing the bay.', 'Aircon, Balcony, TV, Wi-Fi', 3, 1000, 1500, 1900, 2250, 15, 1, 0, NULL
FROM hotels h WHERE h.slug = 'la-laguna-bayview-inn' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'Bay Deluxe');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'Family Room', 'Two beds for four to five guests.', '2 beds, Aircon, TV', 5, 1350, 2000, 2500, 3000, 20, 1, 0, NULL
FROM hotels h WHERE h.slug = 'la-laguna-bayview-inn' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'Family Room');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'VIP Pool Suite', 'Private plunge pool, lounge and sea view. Fixed VIP price, services included.', 'Aircon, Plunge pool, Smart TV, Wi-Fi', 3, 3500, 4900, 6100, 7200, 20, 1, 1, 'Private plunge pool: yours for the whole stay
Welcome drinks: two on arrival
Breakfast in bed: for two
Beach towels and snorkel gear'
FROM hotels h WHERE h.slug = 'la-laguna-bayview-inn' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'VIP Pool Suite');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'VIP Honeymoon Suite', 'King bed, bathtub and flowers on arrival.', 'Aircon, King bed, Bathtub, Smart TV, Wi-Fi', 2, 3200, 4400, 5500, 6500, 20, 1, 1, 'Romantic set-up: flowers and candles
Bottle of wine or juice on arrival
Breakfast in bed: for two
Late checkout: until 2 PM, subject to availability'
FROM hotels h WHERE h.slug = 'la-laguna-bayview-inn' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'VIP Honeymoon Suite');

INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '101', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Bay Standard' WHERE h.slug = 'la-laguna-bayview-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '102', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Bay Standard' WHERE h.slug = 'la-laguna-bayview-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '103', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Bay Standard' WHERE h.slug = 'la-laguna-bayview-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '104', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Bay Standard' WHERE h.slug = 'la-laguna-bayview-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '105', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Bay Standard' WHERE h.slug = 'la-laguna-bayview-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '106', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Bay Standard' WHERE h.slug = 'la-laguna-bayview-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '107', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Bay Standard' WHERE h.slug = 'la-laguna-bayview-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '201', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Bay Deluxe' WHERE h.slug = 'la-laguna-bayview-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '202', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Bay Deluxe' WHERE h.slug = 'la-laguna-bayview-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '203', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Bay Deluxe' WHERE h.slug = 'la-laguna-bayview-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '204', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Bay Deluxe' WHERE h.slug = 'la-laguna-bayview-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '205', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Bay Deluxe' WHERE h.slug = 'la-laguna-bayview-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '206', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Bay Deluxe' WHERE h.slug = 'la-laguna-bayview-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '207', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Family Room' WHERE h.slug = 'la-laguna-bayview-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '208', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Family Room' WHERE h.slug = 'la-laguna-bayview-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '209', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Family Room' WHERE h.slug = 'la-laguna-bayview-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '210', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Family Room' WHERE h.slug = 'la-laguna-bayview-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '301', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Pool Suite' WHERE h.slug = 'la-laguna-bayview-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '302', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Pool Suite' WHERE h.slug = 'la-laguna-bayview-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '303', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Pool Suite' WHERE h.slug = 'la-laguna-bayview-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '304', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Honeymoon Suite' WHERE h.slug = 'la-laguna-bayview-inn';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '305', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Honeymoon Suite' WHERE h.slug = 'la-laguna-bayview-inn';

-- ===== Balatero Palm Stay (Balatero) =====
INSERT IGNORE INTO hotels (name, slug, area, address, description, lat, lng, phone, email, is_active, created_at) VALUES
 ('Balatero Palm Stay','balatero-palm-stay','Balatero','Balatero, Puerto Galera, Oriental Mindoro','Quiet palm-lined stay in Balatero, near the diving and the fishing village.',13.5002,120.9763,'+639708655964','hotelgoph@gmail.com',1,'2026-11-03 00:00:00');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'Palm Standard', 'Double bed, aircon and hot shower.', 'Aircon, Hot shower, Wi-Fi', 2, 500, 780, 980, 1180, 15, 1, 0, NULL
FROM hotels h WHERE h.slug = 'balatero-palm-stay' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'Palm Standard');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'Palm Deluxe', 'Queen bed, TV and a small veranda.', 'Aircon, TV, Veranda, Wi-Fi', 3, 780, 1180, 1480, 1780, 15, 1, 0, NULL
FROM hotels h WHERE h.slug = 'balatero-palm-stay' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'Palm Deluxe');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'Barkada Room', 'Three beds for groups of friends.', '3 beds, Aircon, TV', 6, 1450, 2100, 2650, 3100, 20, 1, 0, NULL
FROM hotels h WHERE h.slug = 'balatero-palm-stay' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'Barkada Room');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'VIP Ocean Villa', 'Corner villa with sofa, mini bar and balcony. Fixed VIP price, services included.', 'Aircon, Smart TV, Mini bar, Balcony, Wi-Fi', 4, 2700, 3900, 4900, 5700, 20, 1, 1, 'Welcome drinks: two on arrival
Breakfast in bed: for two, once per stay
Late checkout: until 2 PM, subject to availability
Priority desk: fast check-in with a dedicated attendant'
FROM hotels h WHERE h.slug = 'balatero-palm-stay' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'VIP Ocean Villa');

INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '101', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Palm Standard' WHERE h.slug = 'balatero-palm-stay';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '102', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Palm Standard' WHERE h.slug = 'balatero-palm-stay';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '103', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Palm Standard' WHERE h.slug = 'balatero-palm-stay';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '104', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Palm Standard' WHERE h.slug = 'balatero-palm-stay';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '105', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Palm Standard' WHERE h.slug = 'balatero-palm-stay';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '106', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Palm Standard' WHERE h.slug = 'balatero-palm-stay';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '201', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Palm Deluxe' WHERE h.slug = 'balatero-palm-stay';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '202', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Palm Deluxe' WHERE h.slug = 'balatero-palm-stay';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '203', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Palm Deluxe' WHERE h.slug = 'balatero-palm-stay';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '204', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Palm Deluxe' WHERE h.slug = 'balatero-palm-stay';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '205', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Palm Deluxe' WHERE h.slug = 'balatero-palm-stay';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '206', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Barkada Room' WHERE h.slug = 'balatero-palm-stay';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '207', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Barkada Room' WHERE h.slug = 'balatero-palm-stay';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '208', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Barkada Room' WHERE h.slug = 'balatero-palm-stay';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '301', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Ocean Villa' WHERE h.slug = 'balatero-palm-stay';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '302', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Ocean Villa' WHERE h.slug = 'balatero-palm-stay';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '303', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Ocean Villa' WHERE h.slug = 'balatero-palm-stay';

-- ===== White Beach Casa Azul (White Beach) =====
INSERT IGNORE INTO hotels (name, slug, area, address, description, lat, lng, phone, email, is_active, created_at) VALUES
 ('White Beach Casa Azul','white-beach-casa-azul','White Beach','White Beach, Puerto Galera, Oriental Mindoro','Boutique stay one minute from the White Beach shoreline, bars and restaurants.',13.5173,120.9617,'+639708655964','hotelgoph@gmail.com',1,'2026-11-03 00:00:00');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'Azul Standard', 'Queen bed, aircon and hot shower.', 'Aircon, Hot shower, Wi-Fi', 2, 800, 1250, 1550, 1850, 15, 1, 0, NULL
FROM hotels h WHERE h.slug = 'white-beach-casa-azul' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'Azul Standard');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'Azul Deluxe', 'Balcony, TV and a partial sea view.', 'Aircon, Balcony, TV, Wi-Fi', 3, 1150, 1700, 2150, 2550, 15, 1, 0, NULL
FROM hotels h WHERE h.slug = 'white-beach-casa-azul' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'Azul Deluxe');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'Azul Family Suite', 'Two beds and a living area.', '2 beds, Aircon, TV, Mini fridge', 5, 1750, 2500, 3100, 3700, 20, 1, 0, NULL
FROM hotels h WHERE h.slug = 'white-beach-casa-azul' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'Azul Family Suite');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'VIP Ocean Villa', 'Premium sea-view suite with lounge and mini bar. Fixed VIP price, services included.', 'Aircon, Smart TV, Mini bar, Balcony, Wi-Fi', 4, 3600, 5000, 6200, 7200, 20, 1, 1, 'Welcome drinks: two on arrival
Breakfast in bed: for two, once per stay
Late checkout: until 2 PM, subject to availability
Priority desk: fast check-in with a dedicated attendant'
FROM hotels h WHERE h.slug = 'white-beach-casa-azul' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'VIP Ocean Villa');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'VIP Pool Suite', 'Private plunge pool and terrace. Fixed VIP price, services included.', 'Aircon, Plunge pool, Smart TV, Wi-Fi', 3, 4000, 5500, 6900, 8000, 20, 1, 1, 'Private plunge pool: yours for the whole stay
Welcome drinks: two on arrival
Breakfast in bed: for two
Beach towels and snorkel gear'
FROM hotels h WHERE h.slug = 'white-beach-casa-azul' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'VIP Pool Suite');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'VIP Honeymoon Suite', 'King bed, bathtub, flowers and candles.', 'Aircon, King bed, Bathtub, Smart TV, Wi-Fi', 2, 3800, 5200, 6500, 7600, 20, 1, 1, 'Romantic set-up: flowers and candles
Bottle of wine or juice on arrival
Breakfast in bed: for two
Late checkout: until 2 PM, subject to availability'
FROM hotels h WHERE h.slug = 'white-beach-casa-azul' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'VIP Honeymoon Suite');

INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '101', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Azul Standard' WHERE h.slug = 'white-beach-casa-azul';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '102', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Azul Standard' WHERE h.slug = 'white-beach-casa-azul';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '103', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Azul Standard' WHERE h.slug = 'white-beach-casa-azul';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '104', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Azul Standard' WHERE h.slug = 'white-beach-casa-azul';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '105', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Azul Standard' WHERE h.slug = 'white-beach-casa-azul';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '106', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Azul Standard' WHERE h.slug = 'white-beach-casa-azul';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '107', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Azul Standard' WHERE h.slug = 'white-beach-casa-azul';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '108', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Azul Standard' WHERE h.slug = 'white-beach-casa-azul';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '201', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Azul Deluxe' WHERE h.slug = 'white-beach-casa-azul';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '202', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Azul Deluxe' WHERE h.slug = 'white-beach-casa-azul';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '203', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Azul Deluxe' WHERE h.slug = 'white-beach-casa-azul';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '204', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Azul Deluxe' WHERE h.slug = 'white-beach-casa-azul';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '205', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Azul Deluxe' WHERE h.slug = 'white-beach-casa-azul';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '206', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Azul Deluxe' WHERE h.slug = 'white-beach-casa-azul';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '207', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Azul Deluxe' WHERE h.slug = 'white-beach-casa-azul';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '208', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Azul Deluxe' WHERE h.slug = 'white-beach-casa-azul';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '301', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Azul Family Suite' WHERE h.slug = 'white-beach-casa-azul';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '302', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Azul Family Suite' WHERE h.slug = 'white-beach-casa-azul';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '303', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Azul Family Suite' WHERE h.slug = 'white-beach-casa-azul';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '304', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Azul Family Suite' WHERE h.slug = 'white-beach-casa-azul';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '401', t.id, 4, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Ocean Villa' WHERE h.slug = 'white-beach-casa-azul';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '402', t.id, 4, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Ocean Villa' WHERE h.slug = 'white-beach-casa-azul';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '403', t.id, 4, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Ocean Villa' WHERE h.slug = 'white-beach-casa-azul';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '404', t.id, 4, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Ocean Villa' WHERE h.slug = 'white-beach-casa-azul';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '405', t.id, 4, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Pool Suite' WHERE h.slug = 'white-beach-casa-azul';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '406', t.id, 4, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Pool Suite' WHERE h.slug = 'white-beach-casa-azul';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '407', t.id, 4, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Pool Suite' WHERE h.slug = 'white-beach-casa-azul';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '501', t.id, 5, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Honeymoon Suite' WHERE h.slug = 'white-beach-casa-azul';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '502', t.id, 5, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Honeymoon Suite' WHERE h.slug = 'white-beach-casa-azul';

-- ===== Muelle Port Lodge (Muelle) =====
INSERT IGNORE INTO hotels (name, slug, area, address, description, lat, lng, phone, email, is_active, created_at) VALUES
 ('Muelle Port Lodge','muelle-port-lodge','Muelle','Muelle Pier Road, Puerto Galera, Oriental Mindoro','Simple, clean lodge a short walk from the Muelle pier, handy for early boats.',13.5066,120.9532,'+639708655964','hotelgoph@gmail.com',1,'2026-11-03 00:00:00');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'Port Standard', 'Double bed, aircon and hot shower.', 'Aircon, Hot shower, Wi-Fi', 2, 480, 760, 950, 1150, 15, 1, 0, NULL
FROM hotels h WHERE h.slug = 'muelle-port-lodge' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'Port Standard');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'Port Deluxe', 'Queen bed, TV and a window with a harbour view.', 'Aircon, TV, Wi-Fi', 3, 720, 1100, 1380, 1650, 15, 1, 0, NULL
FROM hotels h WHERE h.slug = 'muelle-port-lodge' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'Port Deluxe');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'Port Family Room', 'Two beds for the family.', '2 beds, Aircon, TV', 5, 1250, 1850, 2350, 2800, 20, 1, 0, NULL
FROM hotels h WHERE h.slug = 'muelle-port-lodge' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'Port Family Room');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'VIP Honeymoon Suite', 'King bed, bathtub and romantic set-up. Fixed VIP price, services included.', 'Aircon, King bed, Bathtub, Smart TV, Wi-Fi', 2, 2900, 4000, 5000, 5900, 20, 1, 1, 'Romantic set-up: flowers and candles
Bottle of wine or juice on arrival
Breakfast in bed: for two
Late checkout: until 2 PM, subject to availability'
FROM hotels h WHERE h.slug = 'muelle-port-lodge' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'VIP Honeymoon Suite');
INSERT INTO room_types (hotel_id, name, description, amenities, max_guests, price_3h, price_6h, price_12h, price_overnight, cleaning_buffer_min, is_active, is_vip, included_services)
SELECT h.id, 'VIP Ocean Villa', 'Top-floor suite with a view of the pier and bay. Fixed VIP price, services included.', 'Aircon, Smart TV, Mini bar, Lounge, Wi-Fi', 4, 2600, 3700, 4700, 5500, 20, 1, 1, 'Welcome drinks: two on arrival
Breakfast in bed: for two, once per stay
Late checkout: until 2 PM, subject to availability
Priority desk: fast check-in with a dedicated attendant'
FROM hotels h WHERE h.slug = 'muelle-port-lodge' AND NOT EXISTS (SELECT 1 FROM room_types t WHERE t.hotel_id = h.id AND t.name = 'VIP Ocean Villa');

INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '101', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Port Standard' WHERE h.slug = 'muelle-port-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '102', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Port Standard' WHERE h.slug = 'muelle-port-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '103', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Port Standard' WHERE h.slug = 'muelle-port-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '104', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Port Standard' WHERE h.slug = 'muelle-port-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '105', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Port Standard' WHERE h.slug = 'muelle-port-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '106', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Port Standard' WHERE h.slug = 'muelle-port-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '107', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Port Standard' WHERE h.slug = 'muelle-port-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '108', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Port Standard' WHERE h.slug = 'muelle-port-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '109', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Port Standard' WHERE h.slug = 'muelle-port-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '110', t.id, 1, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Port Standard' WHERE h.slug = 'muelle-port-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '201', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Port Deluxe' WHERE h.slug = 'muelle-port-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '202', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Port Deluxe' WHERE h.slug = 'muelle-port-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '203', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Port Deluxe' WHERE h.slug = 'muelle-port-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '204', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Port Deluxe' WHERE h.slug = 'muelle-port-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '205', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Port Deluxe' WHERE h.slug = 'muelle-port-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '206', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Port Deluxe' WHERE h.slug = 'muelle-port-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '207', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Port Deluxe' WHERE h.slug = 'muelle-port-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '208', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Port Deluxe' WHERE h.slug = 'muelle-port-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '209', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Port Family Room' WHERE h.slug = 'muelle-port-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '210', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Port Family Room' WHERE h.slug = 'muelle-port-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '211', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Port Family Room' WHERE h.slug = 'muelle-port-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '212', t.id, 2, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'Port Family Room' WHERE h.slug = 'muelle-port-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '301', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Honeymoon Suite' WHERE h.slug = 'muelle-port-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '302', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Honeymoon Suite' WHERE h.slug = 'muelle-port-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '303', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Ocean Villa' WHERE h.slug = 'muelle-port-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '304', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Ocean Villa' WHERE h.slug = 'muelle-port-lodge';
INSERT IGNORE INTO rooms (hotel_id, room_no, room_type_id, floor, status) SELECT h.id, '305', t.id, 3, 'active' FROM hotels h JOIN room_types t ON t.hotel_id = h.id AND t.name = 'VIP Ocean Villa' WHERE h.slug = 'muelle-port-lodge';
