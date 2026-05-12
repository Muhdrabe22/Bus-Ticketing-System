-- ====================================================
-- KTSTA Database Additions v3.0
-- Run this AFTER the original database.sql
-- ====================================================

USE ktsta_db;

-- Password Reset Tokens
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(100) UNIQUE NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Promo Codes / Discounts
CREATE TABLE IF NOT EXISTS promo_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) UNIQUE NOT NULL,
    description VARCHAR(200),
    discount_type ENUM('percent','fixed') DEFAULT 'percent',
    discount_value DECIMAL(10,2) NOT NULL,
    min_fare DECIMAL(10,2) DEFAULT 0,
    max_discount DECIMAL(10,2),
    usage_limit INT DEFAULT 100,
    used_count INT DEFAULT 0,
    valid_from DATE,
    valid_until DATE,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Promo Code Usage
CREATE TABLE IF NOT EXISTS promo_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    promo_id INT NOT NULL,
    user_id INT NOT NULL,
    booking_id INT,
    discount_applied DECIMAL(10,2),
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (promo_id) REFERENCES promo_codes(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
);

-- Bus Tracking / Live Location
CREATE TABLE IF NOT EXISTS bus_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trip_id INT NOT NULL,
    bus_id INT NOT NULL,
    latitude DECIMAL(10,7),
    longitude DECIMAL(10,7),
    speed_kmh DECIMAL(5,1) DEFAULT 0,
    heading DECIMAL(5,1) DEFAULT 0,
    last_stop VARCHAR(100),
    next_stop VARCHAR(100),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE
);

-- Bus Maintenance Records
CREATE TABLE IF NOT EXISTS maintenance_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT NOT NULL,
    maintenance_type ENUM('routine','repair','inspection','tyre','engine','other') DEFAULT 'routine',
    description TEXT,
    cost DECIMAL(10,2) DEFAULT 0,
    performed_by VARCHAR(100),
    service_date DATE NOT NULL,
    next_service_date DATE,
    status ENUM('scheduled','in_progress','completed') DEFAULT 'scheduled',
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Loyalty Points
CREATE TABLE IF NOT EXISTS loyalty_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    total_points INT DEFAULT 0,
    redeemed_points INT DEFAULT 0,
    tier ENUM('bronze','silver','gold','platinum') DEFAULT 'bronze',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS loyalty_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    points INT NOT NULL,
    type ENUM('earn','redeem','expire','bonus') DEFAULT 'earn',
    description VARCHAR(200),
    reference VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Lost & Found
CREATE TABLE IF NOT EXISTS lost_found (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporter_id INT,
    trip_id INT,
    bus_id INT,
    item_type ENUM('luggage','electronics','documents','clothing','other') DEFAULT 'other',
    item_description TEXT NOT NULL,
    color VARCHAR(50),
    found_location VARCHAR(200),
    report_type ENUM('lost','found') DEFAULT 'lost',
    status ENUM('open','matched','returned','closed') DEFAULT 'open',
    contact_phone VARCHAR(20),
    image_path VARCHAR(255),
    matched_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE SET NULL,
    FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE SET NULL
);

-- Charter Bookings
CREATE TABLE IF NOT EXISTS charter_bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ref_number VARCHAR(30) UNIQUE NOT NULL,
    customer_name VARCHAR(150) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    customer_email VARCHAR(150),
    event_type VARCHAR(100),
    pickup_location TEXT NOT NULL,
    destination TEXT NOT NULL,
    charter_date DATE NOT NULL,
    return_date DATE,
    departure_time TIME,
    num_passengers INT NOT NULL,
    bus_type ENUM('minibus','coaster','luxury') DEFAULT 'minibus',
    num_buses INT DEFAULT 1,
    special_requirements TEXT,
    total_amount DECIMAL(10,2),
    deposit_paid DECIMAL(10,2) DEFAULT 0,
    status ENUM('pending','approved','assigned','completed','cancelled') DEFAULT 'pending',
    assigned_buses TEXT,
    admin_notes TEXT,
    created_by INT,
    processed_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Fare Rules (dynamic pricing)
CREATE TABLE IF NOT EXISTS fare_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rule_name VARCHAR(100) NOT NULL,
    route_id INT,
    applies_to ENUM('all','specific_route','bus_type') DEFAULT 'all',
    bus_type ENUM('minibus','coaster','luxury'),
    day_of_week VARCHAR(20),
    time_from TIME,
    time_to TIME,
    multiplier DECIMAL(4,2) DEFAULT 1.00,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE SET NULL
);

-- Staff/Employee Records (for officers & drivers)
CREATE TABLE IF NOT EXISTS staff_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    staff_id VARCHAR(20) UNIQUE,
    department VARCHAR(100),
    designation VARCHAR(100),
    hire_date DATE,
    license_number VARCHAR(50),
    license_expiry DATE,
    emergency_contact VARCHAR(100),
    emergency_phone VARCHAR(20),
    salary DECIMAL(10,2),
    status ENUM('active','on_leave','suspended','terminated') DEFAULT 'active',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Trip Incidents
CREATE TABLE IF NOT EXISTS trip_incidents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trip_id INT NOT NULL,
    reported_by INT,
    incident_type ENUM('accident','breakdown','delay','passenger_issue','medical','other') DEFAULT 'other',
    description TEXT NOT NULL,
    location VARCHAR(200),
    severity ENUM('low','medium','high','critical') DEFAULT 'low',
    status ENUM('reported','investigating','resolved','closed') DEFAULT 'reported',
    resolution TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trip_id) REFERENCES trips(id),
    FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Subscriptions / Season Passes
CREATE TABLE IF NOT EXISTS subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_name VARCHAR(100) NOT NULL,
    route_id INT,
    trips_per_month INT DEFAULT 20,
    trips_used INT DEFAULT 0,
    price_paid DECIMAL(10,2),
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active','expired','cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE SET NULL
);

-- ===================== SEED ADDITIONAL DATA =====================

-- Promo codes
INSERT IGNORE INTO promo_codes (code, description, discount_type, discount_value, min_fare, max_discount, valid_from, valid_until, created_by) VALUES
('WELCOME10', 'Welcome discount - 10% off first booking', 'percent', 10, 800, 500, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 1),
('KTSTA50', 'Flat ₦50 off any booking', 'fixed', 50, 500, 50, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 60 DAY), 1),
('RAMADAN25', '25% Ramadan special discount', 'percent', 25, 1000, 1000, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY), 1);

-- Loyalty points for demo passenger
INSERT IGNORE INTO loyalty_points (user_id, total_points, tier) VALUES (4, 250, 'silver');
INSERT IGNORE INTO loyalty_transactions (user_id, points, type, description, reference) VALUES
(4, 100, 'earn', 'Points from booking BKG-KTS-00001', 'BKG-KTS-00001'),
(4, 150, 'earn', 'Welcome bonus points', 'WELCOME');

-- Fare rules
INSERT IGNORE INTO fare_rules (rule_name, applies_to, day_of_week, multiplier, is_active) VALUES
('Weekend Premium', 'all', 'Saturday,Sunday', 1.15, 1),
('Peak Hour Surcharge', 'all', NULL, 1.10, 0),
('Early Bird Discount', 'all', NULL, 0.90, 1);

-- Maintenance record
INSERT IGNORE INTO maintenance_records (bus_id, maintenance_type, description, cost, service_date, next_service_date, status, created_by) VALUES
(1, 'routine', 'Oil change, tyre rotation, brake check', 25000, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 MONTH), 'completed', 1),
(2, 'inspection', 'Annual roadworthiness inspection', 15000, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'completed', 1);

-- Add password reset columns if not exists  
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login DATETIME;
ALTER TABLE users ADD COLUMN IF NOT EXISTS login_attempts INT DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS locked_until DATETIME;

-- Update settings with new keys
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('points_per_naira', '1'),
('points_redeem_rate', '100'),
('loyalty_enabled', '1'),
('promo_enabled', '1'),
('max_booking_per_user', '5'),
('smtp_host', 'smtp.gmail.com'),
('smtp_port', '587'),
('smtp_user', 'noreply@ktsta.gov.ng'),
('sms_api_key', 'demo_key'),
('charter_min_passengers', '10'),
('charter_deposit_percent', '30');
