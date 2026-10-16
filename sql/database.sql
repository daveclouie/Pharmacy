-- CK Pharmacy database
-- Import this file in phpMyAdmin (http://localhost/phpmyadmin) > Import

CREATE DATABASE IF NOT EXISTS ck_pharmacy CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE ck_pharmacy;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    contact_person VARCHAR(100),
    phone VARCHAR(30),
    address VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(80) DEFAULT 'Medicine',
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    quantity INT NOT NULL DEFAULT 0,
    min_stock INT NOT NULL DEFAULT 10,
    expiry_date DATE NULL,
    supplier_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS stock_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    change_qty INT NOT NULL,
    type ENUM('add','sale','remove','adjust') NOT NULL,
    note VARCHAR(255),
    user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    total DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    qty INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Default accounts
-- admin / admin123     staff / staff123
INSERT INTO users (username, password, full_name, role) VALUES
('admin', '$2y$10$OF1z/oVEreMOKHDW3QcaTuYIqsA11LwCtez2jzHAUkWjKgjwzQ2sS', 'System Administrator', 'admin'),
('staff', '$2y$10$Eh.pa3ii74eUfueh1Y.nyOBx301Gl0ecNnL8ceVdHQEKBjkKjo6JG', 'Pharmacy Staff', 'staff');

INSERT INTO suppliers (name, contact_person, phone, address) VALUES
('Sample Pharma Distributor', 'Juan Dela Cruz', '09123456789', 'Tarlac City');

INSERT INTO products (name, category, price, quantity, min_stock, expiry_date, supplier_id) VALUES
('Paracetamol 500mg', 'Medicine', 5.00, 100, 20, DATE_ADD(CURDATE(), INTERVAL 18 MONTH), 1),
('Amoxicillin 500mg', 'Medicine', 12.00, 8, 15, DATE_ADD(CURDATE(), INTERVAL 20 DAY), 1),
('Alcohol 70% 500ml', 'Medical Supply', 85.00, 40, 10, DATE_ADD(CURDATE(), INTERVAL 24 MONTH), 1);
