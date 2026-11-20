-- ============================================================
-- Add the default staff login (orders and sales only) to a database you
-- already imported. Your orders, sales and prices stay as they are.
-- Run in phpMyAdmin (SQL tab) with the database selected.
-- Staff login:  staff / Staff@123   (change it with
--   php scripts/create_user.php staff staff@zoilosbitesandgo.test staff )
-- ============================================================
USE zoilos_bites_and_go;
INSERT IGNORE INTO users (username, email, password_hash, role) VALUES
('staff', 'staff@zoilosbitesandgo.test', '$2y$12$lGXzfuLaYfv9WeFlOXRgsO7k2UxBbFNPQzxJcShe13h.T1S22hjRa', 'staff');
