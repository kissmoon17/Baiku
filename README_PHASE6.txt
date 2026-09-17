BAIKU PHASE 6
Cart, checkout, and simulated payment

Project URL: http://localhost/baiku2/
Database: baiku2_db

Phase 6 uses the existing database schema. Do not import another SQL file.
See PHASE6_SETUP.txt for the exact test checklist.

Main endpoints added:
- api.php?action=cart       GET/POST
- api.php?action=create_order  POST

The payment step is deliberately simulated for the college project. No real
payment provider, card transaction, eSewa transaction, Khalti transaction,
or bank transaction is connected in this phase.
