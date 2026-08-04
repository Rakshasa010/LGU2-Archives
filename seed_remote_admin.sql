CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    role VARCHAR(20) DEFAULT 'user',
    dark_mode TINYINT(1) NOT NULL DEFAULT 0
);

INSERT INTO users (username, password, email, full_name, role, dark_mode) VALUES
('admin', '$2y$10$jlanIVxx572tUeUcSh.9b.tKClxtyTnezRnl8sRKBL4wfKQPZtaB2', 'admin@lgu.gov.ph', 'Admin User', 'admin', 0)
ON DUPLICATE KEY UPDATE password=VALUES(password), full_name=VALUES(full_name), role=VALUES(role), dark_mode=VALUES(dark_mode);
