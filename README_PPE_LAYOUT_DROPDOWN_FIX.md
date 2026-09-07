# TUPAD PPE Layout + Product Dropdown Fix

Direct-extract overlay for the TUPAD Reporting System.

## Changes
- Fixes PPE item row overflow on the right side.
- Uses a responsive 12-column layout on desktop and 2-column wrapping on smaller screens.
- Adds `w-full` / `min-w-0` sizing so inputs and the Remove button stay inside the PPE card.
- Changes PPE Product from free-text input to a dropdown with:
  - TUPAD Shirt
  - Gloves
  - Rubber Boots
  - Mask
  - Bucket Hat
- Existing PPE computation, beneficiary count, unit amount, totals, and Remove/Add item behavior are preserved.

## File replaced
- `resources/views/projects/create.blade.php`

## Install
1. Extract this ZIP into the TUPAD project root.
2. Allow replacement of the file above.
3. Run:
   `php artisan optimize:clear`
4. Refresh the Create Project page (hard refresh if needed).

No database migration is required.
