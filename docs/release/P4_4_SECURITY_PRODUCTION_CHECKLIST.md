# P4.4 — Security & Production Configuration Checklist

Run:

```bash
php artisan tupad:release-verify --production
php artisan tupad:phase4-audit --production
```

Both commands must exit successfully before release.

## Required production settings

- `APP_ENV=production`
- `APP_DEBUG=false`
- persistent non-empty `APP_KEY`
- `APP_URL=https://...`
- `DB_CONNECTION=mysql`
- dedicated database account with only required application privileges
- `SESSION_ENCRYPT=true`
- `SESSION_SECURE_COOKIE=true`
- `SESSION_HTTP_ONLY=true`
- organization-approved `SESSION_SAME_SITE` value
- production log policy configured
- production mail transport configured if email delivery is required

## Account/security checks

- no active account uses a development password;
- at least one active Administrator exists;
- every active TC has a valid active Region V province;
- only Administrator, Focal, and TC are live assignable roles;
- no user row remains with role value `gip`;
- historical retired accounts are inactive;
- project-draft tables/routes are absent after the retirement migration;
- temporary passwords require change on first sign-in;
- login throttling remains enabled;
- password/remember-token values do not appear in audit details.

## Authorization checks

- Audit Trail: Administrator only.
- User Administration: Administrator or Focal, with Focal limited to TC management.
- TC project operations are province scoped and fail closed outside assignment.
- Focal financial routes remain separate from TC operational mutation routes.
- Removed GIP/project-draft URLs do not resolve.

## Production gate

The release is blocked on any security, migration, required-schema, role-integrity, route-middleware, or production-environment failure reported by the release commands.
