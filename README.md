# [Baiku:](https://baiku.kesug.com/) Motorcycle Riding Gear E-commerce

Baiku is a motorcycle riding gear storefront created as my **5th semester e-commerce project**. It lets customers browse riding gear, manage a cart, create an account, and place orders. The project began as a PHP/MySQL application running locally with XAMPP and was later deployed to InfinityFree, whose PHP hosting model supports the same basic file based application structure.


> **Payment note:** eSewa is configured for its UAT (test) environment. This project demonstrates a payment integration; it is not configured to collect live eSewa payments.

## Project overview

The storefront focuses on four product categories—**Helmets, Jackets, Pants, and Boots**—with a minimal, riding focused shopping experience. Product information is served from MySQL through PHP endpoints rather than being maintained as a hard coded frontend catalog.

This project was built to practice full stack e-commerce concepts, including server rendered PHP endpoints, relational data, account sessions, inventory aware carts and orders, an admin dashboard, deployment, and payment provider integration.

## Features

### Customer storefront

- Homepage with featured products and category navigation
- Product catalog loaded from the database
- Category filtering, product details, and product search
- Shopping cart with quantity controls and stock limits
- Guest cart stored in the browser; signed in customers can synchronize their cart with their account
- Customer registration, login, and logout using PHP sessions
- Checkout with customer and shipping information
- Cash on delivery orders and eSewa UAT checkout
- Newsletter subscription endpoint

### eSewa payment flow

The checkout sends an authenticated request to the PHP backend. The server reads the cart and product prices from the database, creates a pending order, reserves the stock, and generates the eSewa signature. The browser then redirects to eSewa’s UAT form. When eSewa returns a result, the backend checks the response signature and makes a server to server status request before marking the order as paid. Failed or cancelled payments are recorded and the order’s stock/cart items are restored.

Use only eSewa’s published UAT test credentials while testing. Do not enter real wallet credentials into the UAT flow or describe this configuration as live payment processing.

### Admin dashboard

- Dashboard overview with product, customer, order, and revenue summaries
- Product creation, editing, featuring, stock management, and deactivation
- Order listing and status management
- Customer listing and purchase summaries
- Admin endpoints protected by the authenticated PHP session and administrator role

## Technology stack

- **Frontend:** HTML, CSS, vanilla JavaScript
- **Backend:** PHP
- **Database:** MySQL / MariaDB, accessed with PDO
- **Local development:** XAMPP (Apache and MySQL)
- **Hosting:** InfinityFree PHP hosting
- **Payment integration:** eSewa ePay V2 UAT

## Project structure

```text
.
├── index.html              # Storefront homepage
├── shop.html               # Product catalog
├── product.html            # Product details
├── checkout.html           # Checkout and payment selection
├── login.html              # Customer sign-in / registration
├── admin.php               # Protected admin dashboard
├── admin_api.php           # Admin-only API
├── admin_guard.php         # Admin access guard
├── api.php                 # Product, account, cart, order, newsletter APIs
├── esewa.php               # eSewa initiation and callback verification
├── esewa-success.php       # eSewa success callback
├── esewa-failure.php       # eSewa failure/cancellation callback
├── config.php              # Local/hosting and eSewa configuration (not tracked)
├── db.php                  # Database connection (not tracked)
├── css/
├── js/
├── image/
└── favicon/
```

## Requirements

- PHP with **PDO MySQL** and **mbstring** enabled
- MySQL or MariaDB
- Apache or another PHP capable web server
- An initialized Baiku database with the required schema and product data
- For eSewa checkout, an internet accessible callback URL and eSewa UAT access

## Run locally with XAMPP

1. Install and start **Apache** and **MySQL** from XAMPP.
2. Clone the repository into the XAMPP web root, for example:

   ```bash
   cd C:\xampp\htdocs
   git clone https://github.com/kissmoon17/Baiku.git baiku2
   ```

3. Prepare the Baiku MySQL database and load the schema and seed data. The repository does **not** include a database export (`.sql`), so obtain the database dump separately from the project owner or export it from the existing development database. The application expects tables for products, users, carts, orders, newsletter subscribers, and eSewa payment records.
4. Create/configure the ignored `db.php` file with the local MySQL host, database name, username, and password. The original local setup used a database named `baiku2_db`; adjust the connection settings to match your environment.
5. Create/configure the ignored `config.php` file. Set the base URL to your local project URL and configure the eSewa UAT settings. Keep the UAT secret key in PHP configuration only—never put it in JavaScript or commit it to Git.
6. Open [http://localhost/baiku2/](http://localhost/baiku2/) in your browser.
7. If setting up an administrator for the first time, use `admin-setup.php`, create the admin account, then **delete `admin-setup.php` from the web root**.

The database and configuration files are environment specific. A fresh clone alone is not sufficient to run the application until these are supplied and configured.

## Deploy to InfinityFree

1. Create a MySQL database in the hosting control panel and import the Baiku schema and seed data.
2. Upload the application files to the hosting account’s PHP web root, preserving the directory structure (`css/`, `js/`, `image/`, and `favicon/`).
3. Configure `db.php` with the database host, database name, username, and password supplied by the host. Hosting database values are different from XAMPP’s local defaults.
4. Configure `config.php` with the deployed HTTPS site URL and eSewa UAT callback URLs. The success and failure callbacks must point to the deployed site, not `localhost`.
5. Confirm PHP/PDO MySQL/mbstring support and test browsing, accounts, carts, orders, and UAT payments on the deployed domain.
6. Remove one time setup and diagnostic scripts from the public web root when they are no longer needed. Never upload production payment credentials into a public repository.

## Verification checklist

- Homepage and product catalog load products from the database.
- Category links show the selected category; product pages load the correct item.
- Cart quantities cannot exceed available stock; a signed in cart persists after refresh.
- Registration and login create a PHP session and passwords are stored as hashes.
- Checkout totals are recalculated from database values on the server.
- Cash on delivery orders appear in the admin dashboard.
- For eSewa UAT, verify both successful and cancelled payment paths, and confirm order/payment status and stock/cart behavior in the database.
- Admin pages and admin APIs reject non admin sessions.

The repository includes `test-api.php` and `test-auth.php` for project testing. Keep diagnostic scripts out of the public deployment unless they are specifically needed and access controlled.

## What I learned

- Connecting a browser based storefront to PHP APIs and a relational database
- Managing authentication with PHP sessions and password hashing
- Keeping checkout prices and inventory decisions on the server
- Synchronizing browser and database carts
- Creating orders transactionally and handling payment callbacks
- Verifying an eSewa response with signatures and a server side status check
- Moving a PHP application from a local XAMPP setup to shared PHP hosting

## Future improvements

- Add and maintain a versioned SQL schema/seed file and sample configuration templates
- Add automated tests for account, inventory, order, and payment workflows
- Complete production eSewa onboarding and configuration only if the project is intended to process real payments
- Improve payment cancellation/timeout handling and provide a safe admin reconciliation workflow for pending payments
- Add stronger production protections such as CSRF defenses, rate limiting, and hardened deployment configuration
- Improve accessibility, responsive device testing, and image optimization

## License

No license is currently specified in the repository. Unless a license is added, reuse and redistribution are not explicitly granted.
