CREATE DATABASE user_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE user_management;
-- جدول المستخدمين
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    user_type ENUM('user', 'manager') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- جدول مناطق المواقف
CREATE TABLE parking_zones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    zone_name VARCHAR(50) NOT NULL,
    floor INT DEFAULT 1,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS parking_spots (
id INT AUTO_INCREMENT PRIMARY KEY,
zone_id INT,
spot_number VARCHAR(20) NOT NULL,
spot_type ENUM('regular', 'vip', 'disabled', 'family', 'electric') DEFAULT 'regular',
status ENUM('available', 'occupied', 'maintenance', 'reserved') DEFAULT 'available',
hourly_rate DECIMAL(8,2) DEFAULT 5.00,
max_hours INT DEFAULT 24,
coordinates VARCHAR(100),
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
FOREIGN KEY (zone_id) REFERENCES parking_zones(id) ON DELETE CASCADE
        );
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    spot_id INT NOT NULL,
    vehicle_plate VARCHAR(20) NOT NULL,
    vehicle_type ENUM('car', 'suv', 'motorcycle', 'truck') DEFAULT 'car',
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    amount DECIMAL(8,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (spot_id) REFERENCES parking_spots(id) ON DELETE RESTRICT ON UPDATE CASCADE
);

-- جدول الإشعارات (يعتمد على users)
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- إنشاء جدول المولات
CREATE TABLE IF NOT EXISTS malls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mall_name VARCHAR(100) NOT NULL,
    location VARCHAR(255) NOT NULL,
    total_floors INT DEFAULT 1,
    total_spots INT DEFAULT 0,
    contact_email VARCHAR(100),
    contact_phone VARCHAR(20),
    description TEXT,
    lat DECIMAL(10, 8),
    lng DECIMAL(11, 8),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- تحديث جدول مناطق المواقف لإضافة mall_id
ALTER TABLE parking_zones 
ADD COLUMN  mall_id INT,
ADD COLUMN  total_spots INT DEFAULT 0,
ADD COLUMN  available_spots INT DEFAULT 0;

-- إضافة المفتاح الخارجي لربط المناطق بالمولات
ALTER TABLE parking_zones 
ADD CONSTRAINT fk_parking_zones_mall 
FOREIGN KEY (mall_id) REFERENCES malls(id) ON DELETE CASCADE;

-- تحديث جدول أماكن الانتظار إذا كان هناك أية أعمدة مفقودة
ALTER TABLE parking_spots 
ADD COLUMN  is_active BOOLEAN DEFAULT TRUE;

-- إدخال بيانات تجريبية للمولات
INSERT INTO malls (mall_name, location, total_floors, total_spots, contact_email, contact_phone, description, lat, lng) VALUES
('City Center Mall', 'Downtown, Main Street', 4, 500, 'info@citycenter.com', '+1234567890', 'Largest shopping mall in downtown area', 30.0444, 31.2357),
('Mega Mall', 'North District, Commercial Zone', 3, 300, 'contact@megamall.com', '+1234567891', 'Modern shopping center with premium brands', 30.0450, 31.2360),
('Plaza Mall', 'East Side, Business District', 2, 200, 'support@plazamall.com', '+1234567892', 'Convenient location near business centers', 30.0438, 31.2345);

-- إدخال بيانات تجريبية للمناطق
INSERT INTO parking_zones (mall_id, zone_name, floor, total_spots, available_spots, description) VALUES
(1, 'Zone A - Main Entrance', 1, 100, 75, 'Main parking zone near entrance'),
(1, 'Zone B - East Wing', 1, 80, 60, 'Parking near east wing stores'),
(1, 'Zone C - Upper Level', 2, 120, 90, 'Covered parking on upper level'),
(2, 'Zone A - North Parking', 1, 100, 80, 'Primary parking area'),
(2, 'Zone B - VIP Section', 1, 50, 30, 'Premium parking with extra space'),
(3, 'Zone A - Ground Floor', 1, 80, 65, 'Easy access ground floor parking');

-- إدخال بيانات تجريبية لأماكن الانتظار
INSERT INTO parking_spots (zone_id, spot_number, spot_type, status, hourly_rate) VALUES
(1, 'A1', 'regular', 'available', 5.00),
(1, 'A2', 'regular', 'occupied', 5.00),
(1, 'A3', 'vip', 'available', 10.00),
(1, 'A4', 'disabled', 'available', 3.00),
(1, 'A5', 'electric', 'available', 7.00),
(2, 'B1', 'regular', 'available', 5.00),
(2, 'B2', 'regular', 'maintenance', 5.00),
(2, 'B3', 'family', 'available', 6.00),
(3, 'C1', 'regular', 'available', 5.00),
(3, 'C2', 'vip', 'occupied', 10.00),
(4, 'D1', 'regular', 'available', 4.00),
(5, 'E1', 'vip', 'available', 12.00),
(6, 'F1', 'regular', 'available', 4.00);
-- جدول التقارير
CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    manager_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    report_type ENUM('financial', 'usage', 'maintenance', 'security', 'monthly', 'custom') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE CASCADE
);





