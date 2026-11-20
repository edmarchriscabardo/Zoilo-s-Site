# Zoilo's Bites & Go Management System

Vanilla PHP 8.2+ and MySQL/MariaDB (PDO) app for a chicken sisig eatery. It follows the ERD in `database.sql`
(owner, staff, product, customer, orders, sales, daily closing, expenses, stock checks) and adds a `users` table for logins.

## Run it on XAMPP or Laragon

1. Copy this folder into `htdocs` (XAMPP) or `www` (Laragon), e.g. `htdocs/zoilos-bites-and-go`.
2. Start Apache and MySQL.
3. Import `database.sql` in phpMyAdmin (Import tab) or run `mysql -u root < database.sql`.
   This script DROPS and recreates the `zoilos_bites_and_go` database.
4. Open `http://localhost/zoilos-bites-and-go/public/`.

The default connection (`127.0.0.1`, user `root`, empty password) works as-is on XAMPP and Laragon.
If yours differs, copy `.env.example` to `.env` and edit it.

Without Apache you can also run `php -S localhost:8000` from the project root and open `http://localhost:8000/public/`.

## Run it in Visual Studio Code

You need PHP 8.2+ (with the `pdo_mysql` extension enabled) and a running MySQL or MariaDB server
(the MySQL from XAMPP/Laragon works; you do not need Apache).

1. Install PHP and make sure `php -v` works in a terminal. On Windows, in `php.ini` uncomment `extension=pdo_mysql`.
2. In VS Code: File > Open Folder > this project folder. Accept the recommended extensions.
3. Start MySQL.
4. Run Terminal > Run Task > **1. Install database** (or `php scripts/install.php`). It imports `database.sql`.
5. Run Terminal > Run Task > **2. Start server** (or `php -S localhost:8000 router.php`).
6. Open http://localhost:8000/public/ and log in with `admin` / `Admin@123`.

`router.php` blocks `config/`, `includes/`, `.env` and `.sql` files, because the built-in server ignores `.htaccess`.
Always start the server with `router.php` as shown. For step-through debugging, install Xdebug and use the
"Run site with Xdebug" launch configuration (F5).

## Logo and product photos

- Logo: `public/assets/img/logo.png` (shown in the header, the browser tab and the public menu).
- Product photos live in `public/assets/img/products/`. To add one, copy the image file there, then open
  Manage > Products > Edit and choose it in the **Photo** list. Products without a photo show a placeholder.
- The public menu (`public/index.php`) shows each product as a photo card, grouped by type.
- After pulling this update, re-import `database.sql` (it adds the `product.image` column and the new menu).
  To keep existing data instead, run: `ALTER TABLE product ADD COLUMN image VARCHAR(255) NULL AFTER price;`

## Dashboard menu and prices

After logging in, the dashboard shows the whole menu with photos. Type a new price in any card and press **Save**.
The public menu and new orders use the new price immediately. Note: `order_item` stores no price, so changing a price
also changes the displayed total of **old** orders; sales already recorded keep the amount they were saved with.

## Taking an order (order and sale in one step)

**Orders > New order** is the only form you need. Type the customer's name (pick a returning customer from the list or
type a new one and they are added automatically), add the items, set the payment status and save. The sale is
created with the order: the amount fills in from the items (change it for a discount), and it is counted on that
order date's daily closing. Notes:

- Default for a new order is **Completed / Paid**; change them for orders still being prepared or not yet paid.
- To record a payment later, open the order and press **Edit payment** (or **Edit** in Orders), and set Paid.
- A **Cancelled** order has no sale. Cancelling an order removes its sale; deleting an order (admin) deletes its sale too.
- The **Sales** page is now the list of all sales (with filters); customers are tracked by name under Manage > Customers.
- Two customers with exactly the same name are treated as the same customer.

## Taking an order from the dashboard menu

Every dish in the dashboard's **Menu & new order** section has a **+ Add** button. Tap it to put the dish in the
**Current order** panel (tap again for more, or use the + / - buttons there), type the customer's name, choose the payment
and press **Place order**. The order and its sale are saved together with the current date and time. On a phone the
panel appears above the menu and a bar at the bottom shows the running total. The cart is kept if you reload the page. If something is wrong (for example a missing customer name), the panel shows the
message, keeps the dishes and what you typed, and you can fix it and press **Place order** again; the cart is only emptied
after the order is saved.

## Changing order and payment status from the dashboard

The dashboard's **Orders & sales** table lists today's orders plus any earlier order that is still pending or not fully
paid (tabs: *Needs attention*, *Today*, *Latest 40*). Pick a new **Order status** or **Payment** in the dropdowns and it
saves immediately; the stats and the day's closing update too. Cancelling asks for confirmation and removes that
order's sale from the totals; moving a cancelled order back to Pending/Completed brings its sale back as Unpaid.

## Starting with clean data

`database.sql` no longer contains sample orders, sales, closings, expenses or stock checks (only the menu, the
the stock item list, staff, owner and the admin login). To wipe records you already entered,
run `clear_data.sql` (this cannot be undone).

## Logins: admin and staff

There is no registration page. Accounts are created by the owner/admin.

| Role  | Username | Password    | Can do |
|-------|----------|-------------|--------|
| Admin | `admin`  | `Admin@123` | Everything: dashboard stats, menu prices and photos, orders, sales, closings, expenses, stock checks, manage records, deletes |
| Staff | `staff`  | `Staff@123` | **Orders and sales only**: take orders from the dashboard menu, change order status and payment, view and edit orders and sales |

Staff cannot open Closings, Stock checks or any Manage page, cannot change prices, and cannot delete anything.
These limits are enforced on the server (a staff login that types such an address is sent back to the dashboard with a message), not only hidden in the menu.

Change the demo passwords, or create your own accounts (the script asks for the password):

    php scripts/create_user.php YOUR_USERNAME your@email.com admin
    php scripts/create_user.php cashier1 cashier1@email.com staff

Running it with an existing username resets that account's password and role. (`scripts/create_admin.php` still works and always makes an admin.)
The "Staff" page under Manage is only the list of employee names, not logins.

## What is where

| Path | Purpose |
|------|---------|
| `config/database.php` | PDO connection (exceptions on, real prepared statements) |
| `config/entities.php` | Field definitions for Products, Customers, Stock items, Staff, Owners |
| `includes/auth.php` | Session handling, CSRF tokens, login/role guards, security headers |
| `includes/functions.php` | Escaping, flash messages, validators, closing-total recalculation |
| `includes/crud.php` | Generic list/validate/save helpers for the simple records |
| `includes/header.php`, `footer.php` | Shared layout and responsive navigation |
| `public/` | Pages: menu, admin login, dashboard, orders, sales, closings, stock checks, manage |
| `actions/` | POST handlers (create, update, delete), each CSRF-checked, ending in a redirect (PRG) |

## Behavior worth knowing

- `order_item` stores no price, so order totals use **current** product prices. A sale stores its own amount when recorded.
- Daily closing totals are recalculated automatically; **total sales counts only sales marked Paid**.
- A sale needs a closing day. Recording a sale creates that day's closing if it does not exist.
- Deleting a record that other records still use (for example an owner with products) is refused with a message.
- Login locks for 5 minutes after 5 failed attempts in a session. Sessions expire after 30 minutes idle.

## Before going live

- Serve over HTTPS, and set `display_errors=Off` in `php.ini` (errors are logged, not shown).
- Change the demo admin and staff passwords (see **Logins** above).
- Add a per-IP login throttle at the web server if you expect hostile traffic (the built-in one is per session).
