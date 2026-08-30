# Geographic Mapping Phase 2 — Mapping Shell + Focal Region View

Phase 2 introduces the first interactive map state while preserving the Phase 14E reporting workspace beneath it.

## Scope implemented

- One Livewire component architecture for the map (`GeographicDistributionMap`).
- Focal/Admin regional initial state: `mapLevel = region`, no selected province.
- Leaflet renders `public/geojson/bicol/provinces.geojson` only.
- Province polygons use PSGC `properties.psgc_code` joins.
- Province hover tooltip shows exact mapped beneficiaries, project distribution, ongoing/completed counts, and province fund allocation using existing reporting semantics.
- Chart.js horizontal ranking uses the exact same server payload as the choropleth.
- Basic Fiscal Year, Status, and Implementation Mode controls update the payload without a full-page reload.
- Region KPI strip contains no invented trend percentages.
- Province click/drill-down is intentionally deferred to Phase 3.
- TC interactive province scope is intentionally deferred to Phase 5. Existing server-side province-scoped Phase 14E report remains available to TC in the meantime.

## Data integrity

The choropleth and bar graph beneficiary values come from `ReportingDataService::beneficiaryGeography(..., PROVINCE)`. Legacy projects without exact geographic allocation remain zero/flagged; their totals are not divided across provinces.

Project/status counts use `ReportingDataService::physicalFinancial`, while allocations use `ReportingDataService::fundStatus`. No new financial formula is introduced.

## Frontend dependencies

- Livewire 4 (`livewire/livewire`)
- Leaflet 1.9 stable line
- Chart.js 4

Livewire bundles Alpine.js; Alpine is not separately installed, preventing duplicate Alpine runtimes.
