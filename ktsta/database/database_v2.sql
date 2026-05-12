-- =====================================================
-- KTSTA v3.0 — Additional Tables for New Features
-- Run this AFTER the original database.sql
-- =====================================================

USE ktsta_db;

-- Password Reset Tokens
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    token VARCHAR(100) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Loyalty Points
CREATE TABLE IF NOT EXISTS loyalty_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    points INT NOT NULL DEFAULT 0,
    action VARCHAR(100),
    description VARCHAR(255),
    reference VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- User loyalty totals (add column to users)
ALTER TABLE users ADD COLUMN IF NOT EXISTS loyalty_points INT DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS loyalty_tier ENUM('bronze','silver','gold','platinum') DEFAULT 'bronze';

-- Charter Requests
CREATE TABLE IF NOT EXISTS charter_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_ref VARCHAR(30) UNIQUE NOT NULL,
    user_id INT,
    contact_name VARCHAR(150) NOT NULL,
    contact_phone VARCHAR(20) NOT NULL,
    contact_email VARCHAR(150),
    event_type VARCHAR(100),
    pickup_location VARCHAR(255) NOT NULL,
    destination VARCHAR(255) NOT NULL,
    travel_date DATE NOT NULL,
    return_date DATE,
    num_passengers INT NOT NULL,
    bus_type ENUM('minibus','coaster','luxury') DEFAULT 'minibus',
    duration_days INT DEFAULT 1,
    special_requirements TEXT,
    quoted_price DECIMAL(10,2),
    status ENUM('pending','quoted','accepted','rejected','completed') DEFAULT 'pending',
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Bus Maintenance Log
CREATE TABLE IF NOT EXISTS maintenance_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT NOT NULL,
    maintenance_type ENUM('routine','repair','inspection','breakdown') DEFAULT 'routine',
    description TEXT NOT NULL,
    cost DECIMAL(10,2) DEFAULT 0,
    performed_by VARCHAR(150),
    maintenance_date DATE NOT NULL,
    next_service_date DATE,
    status ENUM('scheduled','in_progress','completed') DEFAULT 'completed',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Lost & Found
CREATE TABLE IF NOT EXISTS lost_found (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_ref VARCHAR(30) UNIQUE NOT NULL,
    type ENUM('lost','found') NOT NULL,
    user_id INT,
    reporter_name VARCHAR(150) NOT NULL,
    reporter_phone VARCHAR(20) NOT NULL,
    item_description TEXT NOT NULL,
    item_category ENUM('bag','phone','document','wallet','clothing','electronics','other') DEFAULT 'other',
    trip_id INT,
    bus_id INT,
    location_found VARCHAR(255),
    date_reported DATE NOT NULL,
    image_path VARCHAR(255),
    status ENUM('open','matched','claimed','closed') DEFAULT 'open',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE SET NULL,
    FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE SET NULL
);

-- Saved Routes (favourites)
CREATE TABLE IF NOT EXISTS saved_routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    route_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_fav (user_id, route_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE CASCADE
);

-- Promo Codes / Discounts
CREATE TABLE IF NOT EXISTS promo_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) UNIQUE NOT NULL,
    description VARCHAR(255),
    discount_type ENUM('percentage','fixed') DEFAULT 'percentage',
    discount_value DECIMAL(10,2) NOT NULL,
    min_fare DECIMAL(10,2) DEFAULT 0,
    max_uses INT DEFAULT 100,
    used_count INT DEFAULT 0,
    valid_from DATE NOT NULL,
    valid_until DATE NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Promo code usage log
CREATE TABLE IF NOT EXISTS promo_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    promo_id INT NOT NULL,
    user_id INT NOT NULL,
    booking_id INT,
    discount_amount DECIMAL(10,2),
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (promo_id) REFERENCES promo_codes(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Trip Reviews / Ratings
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    trip_id INT NOT NULL,
    booking_id INT,
    overall_rating TINYINT NOT NULL CHECK (overall_rating BETWEEN 1 AND 5),
    driver_rating TINYINT CHECK (driver_rating BETWEEN 1 AND 5),
    comfort_rating TINYINT CHECK (comfort_rating BETWEEN 1 AND 5),
    punctuality_rating TINYINT CHECK (punctuality_rating BETWEEN 1 AND 5),
    comment TEXT,
    is_approved TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (trip_id) REFERENCES trips(id),
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
);

-- Bus Live Tracking (GPS coordinates)
CREATE TABLE IF NOT EXISTS bus_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT NOT NULL,
    trip_id INT,
    latitude DECIMAL(10,7),
    longitude DECIMAL(10,7),
    speed_kmh DECIMAL(5,1) DEFAULT 0,
    heading VARCHAR(10),
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bus_id) REFERENCES buses(id),
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE SET NULL
);

-- Activity / Audit Log
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ===================== SEED NEW DATA =====================

-- Promo codes
INSERT IGNORE INTO promo_codes (code, description, discount_type, discount_value, min_fare, max_uses, valid_from, valid_until, created_by) VALUES
('KTSTA10', '10% off your first booking', 'percentage', 10, 500, 500, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 90 DAY), 1),
('WELCOME500', '₦500 off rides above ₦2000', 'fixed', 500, 2000, 200, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 60 DAY), 1),
('FESTIVE20', '20% festive discount', 'percentage', 20, 1000, 100, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 1);

-- Sample maintenance
INSERT IGNORE INTO maintenance_log (bus_id, maintenance_type, description, cost, performed_by, maintenance_date, next_service_date, status, created_by) VALUES
(1, 'routine', 'Oil change, tyre rotation, brake inspection', 25000, 'Ahmed Auto Workshop', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 90 DAY), 'completed', 1),
(2, 'inspection', 'Annual roadworthiness inspection passed', 5000, 'FRSC Katsina', DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_ADD(CURDATE(), INTERVAL 365 DAY), 'completed', 1);

-- Sample reviews
INSERT IGNORE INTO reviews (user_id, trip_id, booking_id, overall_rating, driver_rating, comfort_rating, punctuality_rating, comment) VALUES
(4, 1, 1, 5, 5, 4, 5, 'Excellent service! Driver was very professional and on time.'),
(4, 1, 1, 4, 4, 4, 3, 'Good trip overall, bus was clean and comfortable.');
