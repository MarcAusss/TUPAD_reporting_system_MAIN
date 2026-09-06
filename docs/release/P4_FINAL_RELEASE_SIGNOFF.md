# Final Release Sign-Off

Release is approved only when all boxes below are completed.

- [ ] P3 cleanup test passes.
- [ ] Full PHPUnit suite passes with zero failures.
- [ ] `npm run build` succeeds.
- [ ] `php artisan tupad:phase4-audit` passes UAT gate.
- [ ] Production `.env` reviewed against `.env.production.example`.
- [ ] Pre-deployment MySQL backup completed and verified.
- [ ] `php artisan migrate --force` completed without error.
- [ ] `php artisan tupad:release-verify --production` passes.
- [ ] `php artisan tupad:phase4-audit --production` passes.
- [ ] Administrator smoke test passes.
- [ ] Focal smoke test passes.
- [ ] TC assigned-province smoke test passes.
- [ ] Retired GIP role/project-draft routes are absent and historical retired accounts cannot sign in.
- [ ] Print preview/report export QA signed off.
- [ ] Current report signatories confirmed.
- [ ] Completed P4.6 UAT checklist retained with release records.
- [ ] Backup owner, technical owner, and system owner acknowledged handover.

**Release identifier / commit:** __________________________

**Release date:** _________________________________________

**System owner:** _________________________________________

**Technical handover:** __________________________________

**Approval:** _____________________________________________
