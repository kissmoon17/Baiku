BAIKU — PHASE 3 INTEGRATED
===========================

This ZIP is built from the user's current baiku2 project.

WHAT CHANGED
------------
1. js/main.js no longer contains the hardcoded product catalog.
2. Homepage featured products are loaded from api.php.
3. Shop catalog is loaded from api.php.
4. Category filters request the selected category from api.php.
5. Product detail is loaded from api.php using ?id=PRODUCT_ID.
6. Related products come from the database.
7. Checkout waits for the database product catalog before rendering its summary.
8. Homepage newsletter form now writes subscribers to MySQL.
9. A temporary image fallback is included because the database currently
   contains paths like images/helmet-1.jpg. When the local image is missing,
   the frontend falls back to the image URLs from the original Phase 1 project.

IMPORTANT
---------
Your existing db.php, api.php and database are expected to be present.

INSTALL
-------
1. BACK UP your current C:\xampp\htdocs\baiku2\ folder.
2. Extract this ZIP.
3. Copy the contents of the included baiku2 folder into your existing
   C:\xampp\htdocs\baiku2\ folder, replacing the files when Windows asks.
4. Do NOT delete your database.
5. Keep your existing images if you have added any.

TEST
----
Open:
http://localhost/baiku2/

Then:
- Home should show 4 featured products from MySQL.
- Shop should show 8 database products.
- Click Helmets/Jackets/Pants/Boots to test API filtering.
- Open product.html?id=1 to test the single-product API.
- Add a product to the cart and confirm the cart still uses the database data.
- Newsletter subscription should create a row in newsletter_subscribers.

NOTE ABOUT IMAGES
-----------------
The products table currently stores local image paths. If you later add:
images/helmet-1.jpg, images/jacket-1.jpg, etc., those local images will be
used automatically. The temporary remote fallbacks can then be removed.

NEXT
----
Phase 4: real customer registration/login/logout with hashed passwords,
PHP sessions, and role-based admin authentication.
