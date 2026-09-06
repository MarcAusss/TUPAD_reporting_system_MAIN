# TUPAD Current-Phase Regression Fix V4

This overlay addresses the final six failures reported after V3.

## Root cause

The affected regression tests created TUPAD Coordinator (TC) users without a valid active Bicol province assignment. The current P0 security/data-integrity architecture intentionally fails closed for unassigned TCs, so those requests correctly stopped at `province.scope` with HTTP 403 before reaching the workflow or resource checks the tests intended to exercise.

## Changes

Only test fixtures are changed. Runtime authorization/application code is unchanged.

### `tests/Feature/ProjectWorkflowTest.php`
- Catanduanes now uses PSGC code `052000000`.
- The test TC is assigned to that Catanduanes province.
- This allows the three workflow tests to reach evaluation/approval actions while retaining province scoping.

### `tests/Feature/SecurityAuthorizationTest.php`
- Catanduanes now uses PSGC code `052000000`.
- The test TC is assigned to that Catanduanes province.
- `projects.create` can now be tested for an authorized TC.
- Wrong-project post-document download now reaches the resource mismatch check and can return the expected 404 instead of failing earlier with province-scope 403.

### `tests/Feature/SponsorPartnerOwnershipTest.php`
- Catanduanes now uses PSGC code `052000000`.
- The test TC is assigned to that province before project creation.
- Sponsor/partner persistence is tested without bypassing province security.

## Apply

Extract this ZIP into the TUPAD project root and overwrite matching files.

No migration, seed, or npm rebuild is needed because this overlay changes tests only.

Run:

```bash
php artisan optimize:clear
php artisan test --filter=ProjectWorkflowTest
php artisan test --filter=SecurityAuthorizationTest
php artisan test --filter=SponsorPartnerOwnershipTest
```

Then run the complete suite:

```bash
php artisan test
```

Expected based on the supplied V3 run: the six remaining failures should be removed while the existing province-scope security behavior remains unchanged.
