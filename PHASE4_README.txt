BAIKU — PHASE 4
================

Real customer authentication is now added to api.php.

FEATURES
--------
- Registration
- Password hashing with PHP password_hash()
- Login with password_verify()
- PHP sessions
- Session status check
- Logout
- Customer accounts are created with role = customer
- Existing admin role is recognized by the session
- admin_guard.php is ready for protected admin endpoints

FILES ADDED
-----------
js/baiku-auth.js
admin_guard.php

FILES UPDATED
-------------
api.php
login.html

IMPORTANT
---------
The existing visual login/register UI is preserved. The next integration
step is to connect its exact form fields/buttons to these functions.

TEST
----
Open:
http://localhost/baiku2/login.html

The API itself can be tested with browser developer tools or through the
login UI once its form handlers are wired.

DATABASE
--------
New registrations appear in:
phpMyAdmin -> baiku -> users

Passwords are stored as hashes, never as plain text.

ADMIN
-----
Do NOT manually put a plain password into the password column.

For the first admin account, Phase 5 will provide a safe setup method and
then protect admin.php/admin_api.php with the admin role.

NEXT
----
Phase 5:
- fully wire login.html forms
- create/admin setup
- admin_api.php
- protected admin product CRUD
- orders/customers dashboard
