-- KTSTA Bus Ticketing System Database
-- Katsina State Transport Authority

CREATE DATABASE IF NOT EXISTS ktsta_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ktsta_db;

-- Users Table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    phone VARCHAR(20) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('passenger','admin','officer','driver') DEFAULT 'passenger',
    nin VARCHAR(20),
    profile_photo VARCHAR(255),
    wallet_balance DECIMAL(10,2) DEFAULT 0.00,
    is_verified TINYINT(1) DEFAULT 0,
    otp_code VARCHAR(10),
    otp_expires DATETIME,
    status ENUM('active','suspended','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Routes Table
CREATE TABLE routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_code VARCHAR(20) UNIQUE NOT NULL,
    origin VARCHAR(100) NOT NULL,
    destination VARCHAR(100) NOT NULL,
    distance_km DECIMAL(8,2),
    base_fare DECIMAL(10,2) NOT NULL,
    duration_minutes INT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Buses Table
CREATE TABLE buses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_number VARCHAR(30) UNIQUE NOT NULL,
    registration_plate VARCHAR(20) UNIQUE NOT NULL,
    capacity INT NOT NULL DEFAULT 14,
    bus_type ENUM('minibus','coaster','luxury') DEFAULT 'minibus',
    model VARCHAR(100),
    year INT,
    driver_id INT,
    status ENUM('active','maintenance','inactive') DEFAULT 'active',
    last_service DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Trips Table
CREATE TABLE trips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trip_code VARCHAR(30) UNIQUE NOT NULL,
    route_id INT NOT NULL,
    bus_id INT NOT NULL,
    driver_id INT,
    departure_datetime DATETIME NOT NULL,
    arrival_datetime DATETIME,
    fare DECIMAL(10,2) NOT NULL,
    available_seats INT NOT NULL,
    total_seats INT NOT NULL,
    status ENUM('scheduled','boarding','in_transit','completed','cancelled') DEFAULT 'scheduled',
    officer_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (route_id) REFERENCES routes(id),
    FOREIGN KEY (bus_id) REFERENCES buses(id),
    FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (officer_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Bookings Table
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_ref VARCHAR(20) UNIQUE NOT NULL,
    passenger_id INT NOT NULL,
    trip_id INT NOT NULL,
    seat_number INT NOT NULL,
    passenger_name VARCHAR(150) NOT NULL,
    passenger_phone VARCHAR(20),
    fare DECIMAL(10,2) NOT NULL,
    payment_method ENUM('wallet','card','cash','transfer') DEFAULT 'cash',
    payment_status ENUM('pending','paid','refunded','failed') DEFAULT 'pending',
    booking_status ENUM('confirmed','cancelled','used','no_show') DEFAULT 'confirmed',
    qr_code VARCHAR(500),
    booked_by INT,
    checked_in TINYINT(1) DEFAULT 0,
    checked_in_at DATETIME,
    checked_in_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (passenger_id) REFERENCES users(id),
    FOREIGN KEY (trip_id) REFERENCES trips(id),
    FOREIGN KEY (booked_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (checked_in_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Payments Table
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_ref VARCHAR(30) UNIQUE NOT NULL,
    booking_id INT,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('wallet','card','cash','transfer') NOT NULL,
    transaction_type ENUM('booking','topup','refund') DEFAULT 'booking',
    status ENUM('pending','success','failed') DEFAULT 'pending',
    gateway_ref VARCHAR(100),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Wallet Transactions
CREATE TABLE wallet_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    type ENUM('credit','debit') NOT NULL,
    description VARCHAR(255),
    balance_after DECIMAL(10,2),
    reference VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Notifications
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('booking','payment','trip','system','alert') DEFAULT 'system',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Announcements
CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    type ENUM('info','warning','success','danger') DEFAULT 'info',
    is_active TINYINT(1) DEFAULT 1,
    expires_at DATETIME,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- System Settings
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Feedback / Complaints
CREATE TABLE feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    booking_id INT,
    trip_id INT,
    type ENUM('complaint','suggestion','compliment') DEFAULT 'complaint',
    subject VARCHAR(200),
    message TEXT NOT NULL,
    status ENUM('open','in_review','resolved','closed') DEFAULT 'open',
    response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
);

-- ===================== SEED DATA =====================

-- Admin user (password: Admin@1234)
INSERT INTO users (full_name, email, phone, password, role, is_verified, status) VALUES
('System Administrator', 'admin@ktsta.gov.ng', '08012345678', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, 'active'),
('Ahmed Musa Ibrahim', 'officer@ktsta.gov.ng', '08023456789', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'officer', 1, 'active'),
('Sani Usman Katsina', 'driver1@ktsta.gov.ng', '08034567890', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'driver', 1, 'active'),
('Fatima Bello', 'passenger@test.com', '08045678901', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'passenger', 1, 'active');

-- Routes
INSERT INTO routes (route_code, origin, destination, distance_km, base_fare, duration_minutes) VALUES
('KTS-KNO', 'Katsina', 'Kano', 175, 1800, 180),
('KTS-ABJ', 'Katsina', 'Abuja', 520, 4500, 480),
('KTS-DUR', 'Katsina', 'Daura', 82, 900, 90),
('KTS-MAS', 'Katsina', 'Mashi', 120, 1200, 120),
('KTS-ZNG', 'Katsina', 'Zango', 95, 1000, 100),
('KTS-FUN', 'Katsina', 'Funtua', 130, 1300, 130),
('KTS-JIM', 'Katsina', 'Jibia', 75, 800, 80),
('KNO-KTS', 'Kano', 'Katsina', 175, 1800, 180),
('DUR-KTS', 'Daura', 'Katsina', 82, 900, 90),
('MAS-KTS', 'Mashi', 'Katsina', 120, 1200, 120);

-- Buses
INSERT INTO buses (bus_number, registration_plate, capacity, bus_type, model, year, driver_id, status) VALUES
('KTSTA-001', 'KT 100/23', 14, 'minibus', 'Toyota HiAce', 2023, 3, 'active'),
('KTSTA-002', 'KT 101/23', 14, 'minibus', 'Toyota HiAce', 2023, NULL, 'active'),
('KTSTA-003', 'KT 102/23', 14, 'minibus', 'Toyota HiAce', 2023, NULL, 'active'),
('KTSTA-004', 'KT 103/23', 14, 'minibus', 'Toyota HiAce', 2023, NULL, 'active'),
('KTSTA-005', 'KT 104/23', 30, 'coaster', 'Toyota Coaster', 2023, NULL, 'active'),
('KTSTA-006', 'KT 499/22', 14, 'minibus', 'Toyota HiAce', 2022, NULL, 'maintenance');

-- Trips
INSERT INTO trips (trip_code, route_id, bus_id, driver_id, departure_datetime, arrival_datetime, fare, available_seats, total_seats, status, officer_id) VALUES
('TRP-20260413-001', 1, 1, 3, '2026-04-13 07:00:00', '2026-04-13 10:00:00', 1800, 8, 14, 'scheduled', 2),
('TRP-20260413-002', 1, 2, NULL, '2026-04-13 10:00:00', '2026-04-13 13:00:00', 1800, 14, 14, 'scheduled', 2),
('TRP-20260413-003', 3, 3, NULL, '2026-04-13 08:00:00', '2026-04-13 09:30:00', 900, 10, 14, 'scheduled', NULL),
('TRP-20260413-004', 4, 4, NULL, '2026-04-13 09:00:00', '2026-04-13 11:00:00', 1200, 14, 14, 'scheduled', NULL),
('TRP-20260414-001', 1, 1, 3, '2026-04-14 07:00:00', '2026-04-14 10:00:00', 1800, 14, 14, 'scheduled', 2),
('TRP-20260414-002', 2, 5, NULL, '2026-04-14 06:00:00', '2026-04-14 14:00:00', 4500, 28, 30, 'scheduled', NULL);

-- Sample bookings
INSERT INTO bookings (booking_ref, passenger_id, trip_id, seat_number, passenger_name, passenger_phone, fare, payment_method, payment_status, booking_status, checked_in, booked_by) VALUES
('BKG-KTS-00001', 4, 1, 3, 'Fatima Bello', '08045678901', 1800, 'cash', 'paid', 'confirmed', 0, 4),
('BKG-KTS-00002', 4, 1, 5, 'Aliyu Hassan', '08056789012', 1800, 'cash', 'paid', 'confirmed', 1, 2);

-- Settings
INSERT INTO settings (setting_key, setting_value) VALUES
('site_name', 'KTSTA Bus Ticketing System'),
('site_tagline', 'Katsina State Transport Authority'),
('booking_fee', '50'),
('max_advance_days', '30'),
('cancellation_hours', '2'),
('wallet_topup_min', '500'),
('contact_email', 'info@ktsta.gov.ng'),
('contact_phone', '0800-KTSTA-01'),
('address', 'KTSTA Headquarters, Katsina'),
('currency', 'NGN'),
('currency_symbol', '₦');
