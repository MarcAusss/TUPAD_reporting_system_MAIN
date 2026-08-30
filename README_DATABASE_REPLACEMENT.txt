TUPAD REPORTING SYSTEM - COMPLETE DATABASE FOLDER REPLACEMENT
=============================================================

IMPORTANT
---------
This package is intended to REPLACE the entire existing Laravel database/ folder.
Do not merge it with old migrations.

SAFE APPLY STEPS
----------------
1. Stop composer run dev / php artisan serve if running.
2. Back up your current database if you need any existing data.
3. In the Laravel project root, DELETE the entire existing database folder:

   C:\laravel\TUPAD_reporting_system_MAIN\database

4. Extract/copy the database folder from this ZIP into the Laravel project root.
5. Verify database/migrations contains EXACTLY these four files:

   0001_01_01_000000_create_platform_geography_and_users.php
   0001_01_01_000100_create_funding_and_project_core.php
   0001_01_01_000200_create_project_workflow_and_payments.php
   0001_01_01_000300_create_classification_acp_and_audit.php

6. Verify database/seeders contains ONLY:

   DatabaseSeeder.php
   Fy2025TupadProjectSeeder.php

7. Run:

   php artisan optimize:clear
   composer dump-autoload -o
   php artisan migrate:fresh --seed

DO NOT copy any old migration back into database/migrations.

WHAT THE SEEDER CREATES
-----------------------
- Complete Bicol reference geography from the reviewed local mapping files.
- Development users: admin, focal, tc, gip.
- TC is assigned to Albay so the coordinator geographic map can open immediately.
- 30 FY2025 TUPAD projects from the reviewed workbook selection.
- Exactly 5 projects per Bicol province.
- Every seeded project is forced to Ongoing Profiling.

DEVELOPMENT LOGIN PASSWORD
--------------------------
password

REQUIREMENTS
------------
The current project must retain the reviewed mapping files under:
public/geojson/bicol/

No internet download is required by this seeder.
