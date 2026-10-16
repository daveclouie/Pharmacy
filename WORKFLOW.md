# CK Pharmacy — System Workflow

## 1. Setup (one time)

1. Install **XAMPP**, open the Control Panel, and start **Apache** and **MySQL**.
2. Copy the `Pharmacy` folder to `C:\xampp\htdocs\Pharmacy`.
3. Open `http://localhost/phpmyadmin` → **Import** → choose `sql/database.sql` → **Go**.
4. Open `http://localhost/Pharmacy/` (landing page with the login box).
5. Log in with `admin / admin123` (or `staff / staff123`).

> PHP pages will NOT work if opened with `file:///C:/Users/...`. They must be served by Apache (`http://localhost/...`).

## 2. File structure

```
Pharmacy/
├── index.php          Landing page + login box
├── css/style.css      All styling (external)
├── includes/          config.php, auth.php, header.php, footer.php
├── dashboard.php      Stats + low-stock alerts
├── products.php  inventory.php  sales.php  expiry.php  suppliers.php
├── reports.php  users.php (admin)  backup.php (admin)
├── logout.php
└── sql/database.sql
```

## 3. Overall flow

```
                  index.php (Landing + Login)
                            |
                       Dashboard
                  (stats + low-stock alerts)
                            |
   +----------+----------+--------+-----------+-----------+---------+
   |          |          |        |           |           |         |
Products  Inventory    Sales  Expiration  Suppliers    Reports   Admin only:
                                                                 Users, Backup
```

## 4. Daily workflows

**A. Add a product** — Products → fill the form (name, category, price, quantity, minimum stock, expiry, supplier) → Add Product.

**B. Receive stock** — Inventory → Update Stock → product → *Add stock* → quantity (+ new expiry date for a new batch).
Remove damaged/returned stock the same way with *Remove stock*. Every movement is logged.

**C. Record a sale** — Sales → pick products and quantities → Complete Sale.
For each item the system checks that it exists, is **not expired**, and has **enough stock**. If all pass, the sale is saved and the quantity is **deducted automatically**. If any fails, nothing is saved.

**D. Low stock** — when `quantity <= minimum stock`, the product appears in Dashboard alerts, is highlighted in Inventory, and is listed in Reports → Low-Stock. Restock using B.

**E. Expiration** — the Expiration page lists expired products and those expiring within 60 days (`EXPIRY_WARNING_DAYS` in `includes/config.php`). Expired items cannot be sold; remove them via Inventory → Remove stock.

**F. Suppliers** — Suppliers → add/edit supplier details; the table shows how many products and units each supplier provides.

**G. Reports** — Reports → Sales (date range + top sellers), Inventory (with stock value), Low-Stock, Expired/Near-Expiry.

**H. Backup and restore (admin)** — Backup → Download Backup Now. To restore, upload the `.sql` file made by this system (replaces all current data).

## 5. Roles

| Feature                              | Admin | Staff |
|--------------------------------------|:-----:|:-----:|
| Dashboard, Reports                   |  ✔   |  ✔   |
| Products add / edit / search         |  ✔   |  ✔   |
| Delete products and suppliers        |  ✔   |  ✖   |
| Inventory, Sales, Expiration         |  ✔   |  ✔   |
| Suppliers add / edit                 |  ✔   |  ✔   |
| User management                      |  ✔   |  ✖   |
| Backup & Restore                     |  ✔   |  ✖   |

## 6. Data model

```
suppliers 1──* products 1──* sale_items *──1 sales
                  │
                  └──* stock_log        users 1──* sales / stock_log
```
