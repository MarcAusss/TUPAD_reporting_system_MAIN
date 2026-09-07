TUPAD REPORTING SYSTEM - FAVICON OVERLAY

PURPOSE
Adds public\images\favicon.png as the browser-tab favicon for every full Blade
layout that contains a <head> section.

REQUIREMENT
Your project must already contain:

    public\images\favicon.png

HOW TO APPLY
1. Extract this ZIP.
2. Copy apply-favicon-overlay.ps1 into the ROOT of your Laravel project
   (the same folder that contains artisan).
3. Open PowerShell in the project root.
4. Run:

   powershell -ExecutionPolicy Bypass -File .\apply-favicon-overlay.ps1

5. The script:
   - verifies public\images\favicon.png
   - finds Blade layouts containing <head>...</head>
   - inserts the favicon tags
   - creates .favicon-backup copies of modified Blade files
   - runs php artisan optimize:clear

6. Open the system again and press:

   Ctrl + Shift + R

INSERTED BLADE CODE

    {{-- TUPAD Reporting System favicon --}}
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <link rel="shortcut icon" type="image/png" href="{{ asset('images/favicon.png') }}">

NOTES
- Existing favicon configuration is not duplicated.
- Your favicon image is NOT included in this ZIP because it should already exist at:
  public\images\favicon.png
- Backup files end with:
  .favicon-backup
