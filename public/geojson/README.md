# TUPAD Region V GeoJSON

This directory is the generated geographic-boundary source for the interactive TUPAD Distribution Map.

Do not hand-draw or manually edit province/municipality polygons. Generate the files with:

```powershell
php artisan tupad:mapping-sync-boundaries
```

The command validates the existing Bicol `provinces`, `municipalities`, and `barangays` PSGC codes before downloading boundaries. It writes:

- `provinces.geojson` — six Region V province polygons.
- `municipalities/albay.geojson`
- `municipalities/camarines-norte.geojson`
- `municipalities/camarines-sur.geojson`
- `municipalities/catanduanes.geojson`
- `municipalities/masbate.geojson`
- `municipalities/sorsogon.geojson`
- `manifest.json` — source/feature counts/checksums.

Application joins use `properties.psgc_code`, never a province or municipality display name.

Boundary source configuration and attribution are stored in `config/tupad_mapping.php`.
