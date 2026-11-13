-- =====================================================================
--  database/sample_users.sql — Owner: Myat
--  Test users for login (admin page / customer page).
--  Run AFTER create-tables.sql. OK to run again (same email → updated).
--
--  admin@chilli.test / admin1234  (admin)
--  user1@chilli.test / user1234   (customer)
--
--  password_hash = PHP password_hash(..., PASSWORD_DEFAULT) (bcrypt).
--  Never save plain passwords in the DB.
-- =====================================================================
SET NAMES utf8mb4;

INSERT INTO users (email, password_hash, name, phone, role) VALUES
('admin@chilli.test', '$2y$10$W4wRs1hHNk35aJgKXN0RSOLYetgwURrShQLDuLgo6uWcd0uI3qDF2', '店長',       '090-0000-0000', 'admin'),
('user1@chilli.test', '$2y$10$YabNuk2Vn.MHA7uTWh6eouyo3Klth.9szEEUS1Uup64A.SM0fdUAi', 'テスト太郎', '090-1111-1111', 'customer')
ON DUPLICATE KEY UPDATE
    password_hash = VALUES(password_hash),
    name          = VALUES(name),
    phone         = VALUES(phone),
    role          = VALUES(role);
