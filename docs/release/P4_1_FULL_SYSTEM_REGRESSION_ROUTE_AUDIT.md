# P4.1 — Full System Regression & Route Audit

## Acceptance objective

Prove that the current release candidate is internally consistent and that each supported role reaches only its authorized workflows.

## Automated baseline

1. `php artisan optimize:clear`
2. `php artisan tupad:phase4-audit`
3. `php artisan test`
4. `npm run build`

A release candidate is not accepted if any PHPUnit test or Phase 4 blocking audit check fails.

## Supported role route matrix

| Area | Administrator | Focal | TC |
|---|---|---|---|
| Dashboard | Yes | Yes | Yes |
| Notifications | Yes | Yes | Yes |
| Audit Trail | Yes | No | No |
| User Accounts | Admin/Focal/TC | TC only | No |
| Project Registry | Yes | Read-oriented | Assigned province |
| Project creation/workflow | Yes | No | Assigned province |
| ADL / fund management | Yes | Yes | No |
| DA payment | Yes | Yes | No |
| ACP financial workflow | Yes | Yes | No |
| ACP implementation | Yes | No | Assigned province |
| Reports | Yes | Yes | Assigned province |
| Executive dashboard | Yes | Yes | Assigned province |

## Route/response UAT

For each supported user type:

- sign in with a valid account;
- verify dashboard returns HTTP 200;
- open every visible sidebar item;
- verify no visible link returns 404/500;
- manually enter at least two unauthorized URLs and confirm 403/redirect behavior;
- TC: attempt a project/province URL outside the assigned province and confirm denial;
- Focal: confirm project workflow mutation routes are unavailable;
- Administrator: confirm Audit Trail and all-role User Accounts are available.

Also confirm the retired GIP role and project-draft routes are absent. Existing historical GIP user records, if any, must be inactive `Retired Account` records and must not be assignable or able to sign in.

## Pass criteria

- Full PHPUnit suite passes.
- `tupad:phase4-audit` has zero blocking failures.
- No visible navigation item is dead.
- Unauthorized role/province access is rejected server-side, not only hidden in the UI.
- No live GIP role or project-draft workflow remains.
