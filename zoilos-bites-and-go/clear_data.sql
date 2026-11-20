-- ============================================================
-- Clear all saved records (orders, sales, closings, expenses,
-- stock checks, customers) but KEEP the menu, prices, photos,
-- stock item list, staff, owner and logins.
-- Run in phpMyAdmin (SQL tab) with the database selected, or:
--   mysql -u root zoilos_bites_and_go < clear_data.sql
-- THIS CANNOT BE UNDONE.
-- ============================================================
USE zoilos_bites_and_go;
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE sale;
TRUNCATE TABLE expense;
TRUNCATE TABLE order_item;
TRUNCATE TABLE customer_order;
TRUNCATE TABLE daily_closing;
TRUNCATE TABLE stock_check_detail;
TRUNCATE TABLE stock_check;
TRUNCATE TABLE customer;
SET FOREIGN_KEY_CHECKS = 1;
