# Geographic Mapping Phase 1 — Existing Data Foundation

## Existing geographic schema (reused)

The project already has the required reference hierarchy:

- `provinces.code` — PSGC correspondence code.
- `municipalities.code` — PSGC correspondence code; belongs to a province.
- `barangays.code` — PSGC correspondence code; belongs to a municipality.
- `project_locations` — one project may cover multiple municipalities.
- `project_location_barangay` — project/location barangay pivot with exact `beneficiaries_total` and `beneficiaries_female` when encoded.
- `users.assigned_province_id` — authoritative Coordinator geographic scope.

No duplicate geographic table is introduced by the mapping module.

## Authoritative map join

GeoJSON is normalized to:

```json
{
  "properties": {
    "psgc_code": "051700000",
    "name": "Camarines Sur",
    "level": "province"
  }
}
```

The application joins `properties.psgc_code` to the existing database `code` column. Display names are never the authorization or join key.

## Boundary loading architecture

Generated files:

```text
public/geojson/bicol/
├── provinces.geojson
├── manifest.json
└── municipalities/
    ├── albay.geojson
    ├── camarines-norte.geojson
    ├── camarines-sur.geojson
    ├── catanduanes.geojson
    ├── masbate.geojson
    └── sorsogon.geojson
```

Only `provinces.geojson` is needed for a Focal user's initial Region V view. A province municipality file is loaded only after that province is selected. A Coordinator starts with only their assigned province municipality file in Phase 5.

## Current reporting/data sources to reuse

- Project count: distinct `projects.id` in the authorized/filtered geographic cohort.
- Completed count: `projects.status = completed`.
- Ongoing implementation count: `projects.status = ongoing_implementation` when that KPI is introduced.
- Beneficiaries: use the existing reporting layer and exact `project_location_barangay` allocations. Missing legacy allocations stay explicitly incomplete; do not divide project totals among locations.
- Province fund/allocation values: use the existing reporting/fund-status semantics only where the allocation is authoritative for the requested level.
- Municipality/barangay financial allocation: currently unavailable as an exact geographic split. Do not fabricate it. If an Allocation choropleth is enabled later, the UI must disable/qualify it at levels where exact allocation does not exist unless a reviewed data-model change is introduced.

## Authorization source

`App\Services\Auth\ProvinceAccessService` remains authoritative. Leaflet/Livewire selections are untrusted input. A TC's accessible province is always derived from `users.assigned_province_id` on the server.

## Boundary source

The sync command uses the reviewed source URLs in `config/tupad_mapping.php`. Source GeoJSON is not accepted blindly: the command validates that every configured Region V province and every active municipality/city polygon can be joined to the current PSGC reference rows before replacing the local mapping files.
