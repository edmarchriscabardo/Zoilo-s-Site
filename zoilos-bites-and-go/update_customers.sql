-- Remove the generic "Walk-in Customer" (only if no order uses it).
-- Customers are now added by name when you take an order.
-- Run in phpMyAdmin (SQL tab) with the database selected.
USE zoilos_bites_and_go;
DELETE FROM customer
 WHERE customer_name = 'Walk-in Customer'
   AND NOT EXISTS (SELECT 1 FROM customer_order o WHERE o.customer_id = customer.customer_id);
