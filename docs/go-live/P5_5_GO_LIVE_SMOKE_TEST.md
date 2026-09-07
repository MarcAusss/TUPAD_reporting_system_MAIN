# P5.5 — Go-Live Smoke Test

Run the automated portion first:

```powershell
.\P5_SMOKE_CHECK.cmd
```

Then complete the following using designated test records. Do not use personal beneficiary data solely for testing.

| Area | Administrator | Focal | TC | Expected Result | Pass/Fail | Remarks |
|---|---|---|---|---|---|---|
| Login | Yes | Yes | Yes | Active account signs in; inactive/retired account cannot | | |
| Forced password change | Test once | — | Test once | Temporary password cannot remain as permanent credential | | |
| Dashboard | Yes | Yes | Yes | Correct role workspace and queue visibility | | |
| User administration | All live roles | TC only | Forbidden | No GIP assignable role appears | | |
| Project create | Yes | As authorized | Yes | New project status = Ongoing Profiling | | |
| Quick workflow action | Yes | As authorized | Yes | Ongoing Profiling can progress to TSSD Evaluation without long page scrolling | | |
| TSSD evaluation | Yes | — | Yes | Approval/compliance branch follows authorization | | |
| TC province isolation | Review | — | Yes | TC sees/updates only assigned province records | | |
| Direct Administration | Yes | Financial scope | Operational scope | Correct mode-specific workflow | | |
| Through ACP | Yes | Financial scope | Operational scope | Correct payment/check/liquidation workflow | | |
| Notifications | Yes | Yes | Yes | Role-appropriate live operational notifications | | |
| Audit trail | Yes | Forbidden | Forbidden | Administrator read-only audit visibility | | |
| Reports | Yes | Yes | Yes | Authorized reports render; scope matches role | | |
| PDF/CSV/XLSX | Yes | Yes | Yes | Export data matches browser filters | | |
| Signatories | Yes | Yes | Yes | Current authorized names/version metadata render | | |
| Logout | Yes | Yes | Yes | Session ends and protected routes require login | | |

## Go-live decision

Do not mark the system operational until all critical rows pass and the production Phase 5 audit has zero blocking failures.
