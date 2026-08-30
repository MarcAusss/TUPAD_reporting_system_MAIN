# Geographic Mapping Phase 3 — Province Drill-down

## Scope

Phase 3 extends the Phase 2 Focal/Admin regional map without introducing municipality-to-barangay selection yet.

Implemented behavior:

- Region V still starts with the six Bicol province polygons.
- Clicking a province validates the selected database province id against the active configured Region V PSGC references on the server.
- The map immediately fits the clicked province, then lazily loads only that province's municipality/city GeoJSON file.
- The Chart.js ranking changes from province rows to municipality/city rows using the same filtered server payload as the Leaflet choropleth.
- The breadcrumb changes from `Bicol Region` to `Bicol Region > Selected Province`.
- `Back to Region` restores the six-province boundary and province ranking.
- Fiscal year, status, and implementation-mode filters preserve the selected province while refreshing the municipality payload.
- Municipality/city hover shows beneficiary, project, ongoing, and completed counts.
- Municipality financial allocation is shown as unavailable because the current database has no authoritative municipality-level financial split. The selected-province KPI may still show the authoritative province-scoped cohort allocation without dividing it among municipalities.

## Security

`selectedProvinceId` remains a Livewire `#[Locked]` property. The `selectProvince()` action resolves the supplied id again through `BicolGeographicFoundation::provinceById()` before changing state. Non-Bicol/inactive province ids fail closed.

Coordinator behavior is intentionally unchanged in Phase 3. TC interactive province scope remains Phase 5.

## Lazy boundary loading

Region state:

`public/geojson/bicol/provinces.geojson`

Province state:

`public/geojson/bicol/municipalities/{province-slug}.geojson`

The JavaScript boundary loader compares the current boundary URL with the next payload and fetches a new file only when the geographic level/province changes. Filter-only changes reuse the loaded polygon layer and only update styles, tooltips, and chart values.

## Phase boundary

Not included in Phase 3:

- municipality click selection
- persistent selected municipality styling
- barangay ranking
- coordinator initial assigned-province map
- metric selector for Projects/Allocation

Those remain in Phases 4–6.
