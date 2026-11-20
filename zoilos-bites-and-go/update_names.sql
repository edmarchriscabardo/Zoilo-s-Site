-- Apply the new owner name and food names to a database you already imported
-- (keeps all your orders, sales and price changes). Run in phpMyAdmin > SQL tab.
USE zoilos_bites_and_go;
UPDATE owner   SET owner_name   = 'Mary Grace Limalliv' WHERE owner_id = 1;
UPDATE product SET product_name = 'Egg Fried Rice' WHERE product_name = 'Egg Friedrice';
UPDATE product SET product_name = 'Hot Silog'      WHERE product_name = 'HotSilog';
UPDATE product SET product_name = 'Long Silog'     WHERE product_name = 'LongSilog';
UPDATE product SET product_name = 'Siomai Silog'   WHERE product_name = 'SiomaiSilog';
UPDATE product SET product_name = 'Spam Silog'     WHERE product_name = 'SpamSilog';
UPDATE product SET product_name = 'Sunny Side'     WHERE product_name = 'Sunnyside';
-- Make sure Sisig Rice uses the sisig photo
UPDATE product SET image = 'sisig-rice.jpg' WHERE product_name = 'Sisig Rice';
