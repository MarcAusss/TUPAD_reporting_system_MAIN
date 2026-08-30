# TUPAD Coordinator Account Seeder Extension

This overlay updates only:

`database/seeders/Fy2025TupadProjectSeeder.php`

It keeps the existing 60-project FY2025 sample dataset and adds the actual province-scoped TUPAD Coordinator development accounts.

## Coordinator accounts

| Province | Username | Email | Password |
|---|---|---|---|
| Albay | Orlan | orlan.albay@example.com | password |
| Albay | Salvs | salvs.albay@example.com | password |
| Albay | Nics | nics.albay@example.com | password |
| Camarines Norte | Tay | tay.camarinesnorte@example.com | password |
| Camarines Sur | Camz | camz.camarinessur@example.com | password |
| Camarines Sur | Jho | jho.camarinessur@example.com | password |
| Camarines Sur | Klint | klint.camarinessur@example.com | password |
| Catanduanes | Pau | pau.catanduanes@example.com | password |
| Masbate | Julz | julz.masbate@example.com | password |
| Sorsogon | Yhen | yhen.sorsogon@example.com | password |

All accounts use role `tc`, position `TUPAD Coordinator`, are active, and are assigned to the matching active Bicol province.

The old placeholder username `tc` is upgraded in place to `Orlan` when present and Orlan does not already exist. This preserves its existing user ID and therefore preserves development GIP supervisor relationships.

The generic `gip` development account uses Orlan as its default supervisor.

## Apply

Extract the ZIP over the project root and replace the seeder file, then run:

```powershell
php artisan optimize:clear
composer dump-autoload -o
php artisan db:seed --class=Fy2025TupadProjectSeeder
```

No migration is required.
