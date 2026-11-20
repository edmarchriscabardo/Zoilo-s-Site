-- ============================================================
-- Keep admin logins only: removes staff1 and any accounts created
-- through the old Register page. Your orders, sales and prices stay.
-- Run in phpMyAdmin (SQL tab) with the database selected.
-- ============================================================
USE zoilos_bites_and_go;
DELETE FROM users WHERE role <> 'admin';

-- Make sure the default admin exists (password: Admin@123 - change it with
-- `php scripts/create_admin.php admin admin@zoilosbitesandgo.test`)
INSERT IGNORE INTO users (username, email, password_hash, role) VALUES
('admin', 'admin@zoilosbitesandgo.test', '$2y$12$aPE4Nju2KpeXB1EPSSlMre40v7qwRju/msEo6ooIncR2KHLnxj7Du', 'admin');
