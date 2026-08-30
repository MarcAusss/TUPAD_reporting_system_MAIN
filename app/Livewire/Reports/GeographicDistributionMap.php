<?php

namespace App\Livewire\Reports;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Enums\ReportDimension;
use App\Enums\ReportType;
use App\Models\User;
use App\Services\Auth\ProvinceAccessService;
use App\Services\Mapping\BicolGeographicFoundation;
use App\Services\Mapping\BicolMapDataService;
use App\Services\Mapping\BicolMapMetricService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class GeographicDistributionMap extends Component
{
    #[Locked]
    public string $mapLevel = 'region';

    #[Locked]
    public ?int $selectedProvinceId = null;

    #[Locked]
    public ?int $selectedMunicipalityId = null;

    public int|string|null $fiscalYear = null;

    public ?string $reportingPeriod = null;

    public ?string $status = null;

    public ?string $implementationMode = null;

    public string $mapMetric = BicolMapMetricService::BENEFICIARIES;

    public function boot(): void
    {
        $user = $this->authenticatedUser();

        \abort_unless(
            $user->isFocal() || $user->isAdmin() || $user->isTc(),
            403,
            'The interactive geographic map is not available to the current role.',
        );

        if ($user->isTc()) {
            $assignedProvinceId = \app(ProvinceAccessService::class)->assignedProvinceId($user);

            \abort_unless(
                $assignedProvinceId !== null
                    && \app(BicolGeographicFoundation::class)->provinceById($assignedProvinceId) !== null,
                403,
                'This TUPAD Coordinator account has no active Bicol province assignment.',
            );
        }
    }

    public function mount(
        int|string|null $fiscalYear = null,
        int|string|null $quarter = null,
        int|string|null $month = null,
        ?string $status = null,
        ?string $implementationMode = null,
    ): void {
        $this->fiscalYear = \filled($fiscalYear) ? (string) $fiscalYear : null;
        $this->reportingPeriod = $this->reportingPeriodFromInputs($quarter, $month);
        $this->status = \filled($status) ? (string) $status : null;
        $this->implementationMode = \filled($implementationMode) ? (string) $implementationMode : null;

        $this->initializeViewerScope();
    }

    /** @return array<string,array<int,mixed>> */
    protected function rules(): array
    {
        return [
            'fiscalYear' => ['nullable', 'integer', 'between:2000,2100'],
            'reportingPeriod' => ['nullable', Rule::in($this->reportingPeriodValues())],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'implementationMode' => ['nullable', Rule::enum(ImplementationMode::class)],
            'mapMetric' => ['required', Rule::in(BicolMapMetricService::keys())],
        ];
    }

    public function updated(string $property): void
    {
        if (! in_array($property, [
            'fiscalYear',
            'reportingPeriod',
            'status',
            'implementationMode',
            'mapMetric',
        ], true)) {
            return;
        }

        if (in_array($property, ['fiscalYear', 'reportingPeriod', 'status', 'implementationMode'], true)
            && $this->{$property} === '') {
            $this->{$property} = null;
        }

        if ($property === 'fiscalYear' && ! \filled($this->fiscalYear)) {
            $this->reportingPeriod = null;
            $this->resetErrorBag('reportingPeriod');
        }

        if ($property === 'reportingPeriod'
            && \filled($this->reportingPeriod)
            && ! \filled($this->fiscalYear)) {
            $this->reportingPeriod = null;
            $this->addError('reportingPeriod', 'Select a fiscal year before applying a quarter or month.');

            return;
        }

        $this->validateOnly($property);

        if ($property === 'mapMetric'
            && $this->mapMetric === BicolMapMetricService::ALLOCATION
            && $this->mapLevel !== 'region') {
            $this->mapMetric = BicolMapMetricService::BENEFICIARIES;
            $this->addError(
                'mapMetric',
                'Allocation is available only on the Bicol Region province map because municipality/barangay financial values are not split.',
            );
        } else {
            $this->resetErrorBag('mapMetric');
        }

        $this->refreshBrowserMap();
    }

    public function clearFilters(): void
    {
        $this->fiscalYear = null;
        $this->reportingPeriod = null;
        $this->status = null;
        $this->implementationMode = null;
        $this->resetValidation();
        $this->refreshBrowserMap();
    }

    public function selectProvince(int $provinceId): void
    {
        $this->authorizeProvinceSelection($provinceId);

        $province = \app(BicolGeographicFoundation::class)->provinceById($provinceId);
        \abort_unless($province !== null, 404, 'Selected Bicol province was not found.');

        if ($this->mapMetric === BicolMapMetricService::ALLOCATION) {
            $this->mapMetric = BicolMapMetricService::BENEFICIARIES;
        }

        $this->selectedProvinceId = (int) $province->id;
        $this->selectedMunicipalityId = null;
        $this->mapLevel = 'province';
        $this->refreshBrowserMap();
    }

    public function selectMunicipality(int $municipalityId): void
    {
        $this->authorizeCurrentProvinceScope();

        \abort_unless(
            $this->selectedProvinceId !== null,
            404,
            'Select a Bicol province before selecting a municipality/city.',
        );

        $municipality = \app(BicolGeographicFoundation::class)->municipalityById(
            $this->selectedProvinceId,
            $municipalityId,
        );
        \abort_unless($municipality !== null, 404, 'Selected municipality/city was not found in this Bicol province.');

        $this->selectedMunicipalityId = (int) $municipality->id;
        $this->mapLevel = 'municipality';
        $this->refreshBrowserMap();
    }

    public function showProvince(): void
    {
        $this->authorizeCurrentProvinceScope();

        if ($this->selectedProvinceId === null) {
            $this->showRegion();
            return;
        }

        $this->selectedMunicipalityId = null;
        $this->mapLevel = 'province';
        $this->refreshBrowserMap();
    }

    public function showRegion(): void
    {
        $user = $this->authenticatedUser();

        \abort_unless(
            ! $user->isTc(),
            403,
            'TUPAD Coordinators are restricted to their assigned province.',
        );

        $this->selectedProvinceId = null;
        $this->selectedMunicipalityId = null;
        $this->mapLevel = 'region';
        $this->refreshBrowserMap();
    }

    #[Computed]
    public function mapPayload(): array
    {
        $user = $this->authenticatedUser();
        $filters = $this->filterInput();
        $service = \app(BicolMapDataService::class);

        if (
            $this->mapLevel === 'municipality'
            && $this->selectedProvinceId !== null
            && $this->selectedMunicipalityId !== null
        ) {
            $payload = $service->municipalityPayload(
                $user,
                $this->selectedProvinceId,
                $this->selectedMunicipalityId,
                $filters,
            );
        } elseif ($this->mapLevel === 'province' && $this->selectedProvinceId !== null) {
            $payload = $service->provincePayload($user, $this->selectedProvinceId, $filters);
        } else {
            $payload = $service->regionPayload($user, $filters);
        }

        return \app(BicolMapMetricService::class)->apply($payload, $this->mapMetric);
    }

    public function render()
    {
        $payload = $this->mapPayload;
        $mapLevel = $payload['map_level'] ?? 'region';
        $isProvinceScope = in_array($mapLevel, ['province', 'municipality'], true);
        $isMunicipalityView = $mapLevel === 'municipality';
        $effectiveMetric = (string) \data_get($payload, 'metric.key', BicolMapMetricService::BENEFICIARIES);
        $periodFilters = $this->periodFilterInput();

        $reportType = match ($effectiveMetric) {
            BicolMapMetricService::PROJECTS => ReportType::PHYSICAL_FINANCIAL,
            BicolMapMetricService::ALLOCATION => ReportType::FUND_STATUS,
            default => ReportType::GEOGRAPHIC_BENEFICIARIES,
        };

        return view('livewire.reports.geographic-distribution-map', [
            'mapPayload' => $payload,
            'statuses' => ProjectStatus::cases(),
            'implementationModes' => ImplementationMode::cases(),
            'reportingPeriods' => $this->reportingPeriodOptions(),
            'exportQuery' => array_filter([
                'report_type' => $reportType->value,
                'group_by' => $isMunicipalityView
                    ? ReportDimension::BARANGAY->value
                    : ($isProvinceScope ? ReportDimension::MUNICIPALITY->value : ReportDimension::PROVINCE->value),
                'province_id' => $isProvinceScope
                    ? ($payload['selected_province']['id'] ?? null)
                    : null,
                'municipality_id' => $isMunicipalityView
                    ? ($payload['selected_municipality']['id'] ?? null)
                    : null,
                'fiscal_year' => $this->fiscalYear,
                'quarter' => $periodFilters['quarter'] ?? null,
                'month' => $periodFilters['month'] ?? null,
                'status' => $this->status,
                'implementation_mode' => $this->implementationMode,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
        ]);
    }

    /** @return array<string,mixed> */
    private function filterInput(): array
    {
        return array_filter([
            'fiscal_year' => $this->fiscalYear,
            ...$this->periodFilterInput(),
            'status' => $this->status,
            'implementation_mode' => $this->implementationMode,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /** @return array{quarter?:int,month?:int} */
    private function periodFilterInput(): array
    {
        if (! \filled($this->reportingPeriod)) {
            return [];
        }

        if (preg_match('/^q([1-4])$/', (string) $this->reportingPeriod, $matches)) {
            return ['quarter' => (int) $matches[1]];
        }

        if (preg_match('/^m(0[1-9]|1[0-2])$/', (string) $this->reportingPeriod, $matches)) {
            return ['month' => (int) $matches[1]];
        }

        return [];
    }

    private function reportingPeriodFromInputs(int|string|null $quarter, int|string|null $month): ?string
    {
        $quarter = \filled($quarter) ? (int) $quarter : null;
        $month = \filled($month) ? (int) $month : null;

        if ($quarter !== null && $quarter >= 1 && $quarter <= 4) {
            return 'q'.$quarter;
        }

        if ($month !== null && $month >= 1 && $month <= 12) {
            return 'm'.str_pad((string) $month, 2, '0', STR_PAD_LEFT);
        }

        return null;
    }

    /** @return array<int,string> */
    private function reportingPeriodValues(): array
    {
        return array_keys($this->reportingPeriodOptions());
    }

    /** @return array<string,string> */
    private function reportingPeriodOptions(): array
    {
        return [
            'q1' => 'Quarter 1 (Jan–Mar)',
            'q2' => 'Quarter 2 (Apr–Jun)',
            'q3' => 'Quarter 3 (Jul–Sep)',
            'q4' => 'Quarter 4 (Oct–Dec)',
            'm01' => 'January',
            'm02' => 'February',
            'm03' => 'March',
            'm04' => 'April',
            'm05' => 'May',
            'm06' => 'June',
            'm07' => 'July',
            'm08' => 'August',
            'm09' => 'September',
            'm10' => 'October',
            'm11' => 'November',
            'm12' => 'December',
        ];
    }

    private function initializeViewerScope(): void
    {
        $user = $this->authenticatedUser();

        if (! $user->isTc()) {
            return;
        }

        $assignedProvinceId = \app(ProvinceAccessService::class)->assignedProvinceId($user);
        \abort_unless($assignedProvinceId !== null, 403, 'This TUPAD Coordinator account has no assigned province.');

        $province = \app(BicolGeographicFoundation::class)->provinceById($assignedProvinceId);
        \abort_unless(
            $province !== null,
            403,
            'The assigned province is outside the active Bicol Region mapping scope.',
        );

        $this->selectedProvinceId = (int) $province->id;
        $this->selectedMunicipalityId = null;
        $this->mapLevel = 'province';
    }

    private function authorizeProvinceSelection(int $provinceId): void
    {
        $user = $this->authenticatedUser();

        if (! $user->isTc()) {
            return;
        }

        \abort_unless(
            \app(ProvinceAccessService::class)->canAccessProvince($user, $provinceId),
            403,
            'TUPAD Coordinators may only access their assigned province.',
        );
    }

    private function authorizeCurrentProvinceScope(): void
    {
        $user = $this->authenticatedUser();

        if (! $user->isTc()) {
            return;
        }

        \abort_unless(
            $this->selectedProvinceId !== null
                && \app(ProvinceAccessService::class)->canAccessProvince($user, $this->selectedProvinceId),
            403,
            'TUPAD Coordinators may only access municipalities inside their assigned province.',
        );
    }

    private function authenticatedUser(): User
    {
        $user = Auth::user();

        \abort_unless(
            $user instanceof User,
            403,
            'Authentication is required to use the geographic map.',
        );

        return $user;
    }

    private function refreshBrowserMap(): void
    {
        unset($this->mapPayload);

        $this->dispatch(
            'tupad-map-data-updated',
            payload: $this->mapPayload,
        );
    }
}
