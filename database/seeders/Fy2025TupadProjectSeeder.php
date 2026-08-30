<?php

namespace Database\Seeders;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Enums\ProjectTerm;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Project;
use App\Models\ProjectLocation;
use App\Models\ProjectMonitoringDetail;
use App\Models\Province;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class Fy2025TupadProjectSeeder extends Seeder
{
    private const SOURCE_WORKBOOK = 'FY2025 TUPAD DATABASED.xlsx';

    private const SEED_BATCH = 'FY2025-TUPAD-SHEET-SAMPLE';

    private const SEED_MARKER = '[FY2025-TUPAD-SHEET-SEED]';

    private const REQUIRED_PROVINCES = [
        'Albay',
        'Camarines Norte',
        'Camarines Sur',
        'Catanduanes',
        'Masbate',
        'Sorsogon',
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException(
                'Fy2025TupadProjectSeeder is a reviewed development/test data seeder and is disabled in production.'
            );
        }

        $actor = $this->seedActor();
        $rows = collect($this->sourceRows());

        $this->validateSourceRows($rows);
        $this->guardExistingAdls($rows);

        DB::transaction(function () use ($actor, $rows): void {
            $this->resetPreviouslySeededRows($rows);

            $adls = $this->seedAdls($actor, $rows);

            foreach ($rows as $row) {
                $this->seedProjectRow(
                    actor: $actor,
                    adl: $adls[$row['adl_number']],
                    row: $row,
                );
            }
        });

        $counts = $rows
            ->countBy('source_province')
            ->sortKeys()
            ->map(fn (int $count, string $province): string => "{$province}: {$count}")
            ->values()
            ->implode(', ');

        $this->command?->info(
            'FY2025 TUPAD spreadsheet sample seeded successfully: '.$rows->count().' projects. '.$counts.'.'
        );

        $this->command?->warn(
            'All seeded projects are intentionally forced to Ongoing Profiling. Source spreadsheet statuses and source project codes are retained only in traceability remarks.'
        );
    }

    private function seedActor(): User
    {
        $actor = User::query()
            ->where('is_active', true)
            ->whereIn('role', [
                UserRole::FOCAL->value,
                UserRole::ADMIN->value,
            ])
            ->orderByRaw(
                "CASE WHEN role = ? THEN 0 ELSE 1 END",
                [UserRole::FOCAL->value],
            )
            ->orderBy('id')
            ->first();

        if (! $actor) {
            throw new RuntimeException(
                'Fy2025TupadProjectSeeder requires an active Focal or Administrator user. Run the reviewed user seeder/create an authorized user first.'
            );
        }

        return $actor;
    }

    private function validateSourceRows(Collection $rows): void
    {
        if ($rows->count() < 30) {
            throw new RuntimeException('Expected at least 30 reviewed FY2025 source rows.');
        }

        foreach (self::REQUIRED_PROVINCES as $province) {
            $count = $rows->where('source_province', $province)->count();

            if ($count < 5) {
                throw new RuntimeException(
                    "FY2025 source selection must contain at least five projects for {$province}; found {$count}."
                );
            }
        }

        foreach ($rows as $row) {
            $total = (int) $row['beneficiaries_total'];
            $female = (int) $row['beneficiaries_female'];
            $days = (int) $row['number_of_days'];

            if ($total < 1 || $female < 0 || $female > $total) {
                throw new RuntimeException($this->rowLabel($row).' has invalid beneficiary totals.');
            }

            ProjectTerm::fromDays($days);

            $computed = $this->moneyToCents($row['wages_total'])
                + $this->moneyToCents($row['ppe_total'])
                + $this->moneyToCents($row['insurance_total']);

            if ($computed !== $this->moneyToCents($row['requested_to_dole'])) {
                throw new RuntimeException(
                    $this->rowLabel($row).' does not reconcile: wages + PPE + insurance must equal the DOLE-requested project amount.'
                );
            }
        }
    }

    private function guardExistingAdls(Collection $rows): void
    {
        foreach ($rows->pluck('adl_number')->unique()->values() as $adlNumber) {
            $existing = Adl::query()
                ->where('adl_number', $adlNumber)
                ->first();

            if ($existing && $existing->batch !== self::SEED_BATCH) {
                throw new RuntimeException(
                    "ADL [{$adlNumber}] already exists and is not owned by this seeder. No existing ADL was modified. Remove/rename the conflicting development record or use a clean development database."
                );
            }
        }
    }

    private function resetPreviouslySeededRows(Collection $rows): void
    {
        $adlIds = Adl::query()
            ->whereIn('adl_number', $rows->pluck('adl_number')->unique()->all())
            ->where('batch', self::SEED_BATCH)
            ->pluck('id');

        if ($adlIds->isEmpty()) {
            return;
        }

        $allocationIds = AdlAllocation::query()
            ->whereIn('adl_id', $adlIds)
            ->where('remarks', 'like', self::SEED_MARKER.'%')
            ->pluck('id');

        if ($allocationIds->isNotEmpty()) {
            Project::query()
                ->whereIn('adl_allocation_id', $allocationIds)
                ->delete();

            AdlAllocation::query()
                ->whereIn('id', $allocationIds)
                ->delete();
        }
    }

    /** @return array<string, Adl> */
    private function seedAdls(User $actor, Collection $rows): array
    {
        $result = [];

        foreach ($rows->groupBy('adl_number') as $adlNumber => $group) {
            $grantCents = $group->sum(
                fn (array $row): int => $this->moneyToCents($row['requested_to_dole'])
            );

            $adminCents = $group->sum(
                fn (array $row): int => $this->moneyToCents($row['service_fee'])
            );

            $dateReceived = $group
                ->pluck('date_received')
                ->filter()
                ->sort()
                ->first();

            $result[$adlNumber] = Adl::query()->updateOrCreate(
                ['adl_number' => $adlNumber],
                [
                    'date_received' => $dateReceived,
                    'batch' => self::SEED_BATCH,
                    'tranche' => '30-row reviewed FY2025 sample',
                    'sponsor_reference' => self::SOURCE_WORKBOOK,
                    'grants' => $this->centsToMoney($grantCents),
                    'admin_cost' => $this->centsToMoney($adminCents),
                    // Current project rule: ADL total follows grants; admin cost is tracked separately.
                    'total' => $this->centsToMoney($grantCents),
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ],
            );
        }

        return $result;
    }

    private function seedProjectRow(User $actor, Adl $adl, array $row): void
    {
        $province = Province::query()
            ->where('name', $row['source_province'])
            ->where('is_active', true)
            ->firstOrFail();

        $municipality = Municipality::query()
            ->where('province_id', $province->id)
            ->where('code', $row['municipality_psgc'])
            ->where('is_active', true)
            ->firstOrFail();

        $barangay = Barangay::query()
            ->where('municipality_id', $municipality->id)
            ->where('code', $row['barangay_psgc'])
            ->where('is_active', true)
            ->firstOrFail();

        $sourceTag = self::SEED_MARKER
            .' '.self::SOURCE_WORKBOOK
            .' | '.$row['sheet'].' row '.$row['row']
            .' | source project code '.$row['source_project_code'];

        $amount = $this->money($row['requested_to_dole']);

        $allocation = AdlAllocation::query()->create([
            'adl_id' => $adl->id,
            'fund_sponsor' => $this->nullableText($row['fund_sponsor']),
            'partner' => $this->nullableText($row['partner']),
            'local_chief_executive_partylist' => $this->nullableText($row['partner']),
            'location' => $barangay->name.', '.$municipality->name.', '.$province->name,
            'province' => $province->name,
            'district' => $municipality->district,
            'municipality' => $municipality->name,
            'amount' => $amount,
            'grant_amount' => $amount,
            'admin_cost_amount' => $this->money($row['service_fee']),
            'total_amount' => $amount,
            'remarks' => $sourceTag,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $project = Project::query()->create([
            'adl_allocation_id' => $allocation->id,
            'date_received' => $row['date_received'],
            'project_title' => $row['project_title'],
            'nature_of_work' => $row['nature_of_work'],
            'fund_sponsor' => $this->nullableText($row['fund_sponsor']),
            'partner' => $this->nullableText($row['partner']),
            'project_series' => $this->nullableText($row['project_series']),
            'project_series_remarks' => $sourceTag,
            'tevs_date_verified' => null,
            'tevs_remarks' => null,
            'province_id' => $province->id,
            'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id,
            'province' => $province->name,
            'district' => $municipality->district,
            'municipality' => $municipality->name,
            'barangay' => $barangay->name,
            'income_class' => $this->nullableText($row['income_class']),
            'implementation_mode' => $this->implementationMode($row['implementation_mode']),
            'number_of_days' => (int) $row['number_of_days'],
            'term' => ProjectTerm::fromDays((int) $row['number_of_days']),
            'intervention_focus' => null,
            'beneficiaries_total' => (int) $row['beneficiaries_total'],
            'beneficiaries_female' => (int) $row['beneficiaries_female'],
            'wage_rate' => $this->money($row['wage_rate']),
            'wages_total' => $this->money($row['wages_total']),
            'ppe_total' => $this->money($row['ppe_total']),
            'insurance_rate' => $this->money($row['insurance_rate']),
            'insurance_beneficiaries' => (int) $row['beneficiaries_total'],
            'insurance_total' => $this->money($row['insurance_total']),
            // The application's project cost is the amount requested/charged to DOLE grants (AO + AP + AQ in the source sheet), not the external equity-inclusive AV column.
            'total_project_cost' => $amount,
            'status' => ProjectStatus::ONGOING_PROFILING,
            'remarks' => $this->projectRemarks($row),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        // Make the automatically-created initial status history auditable to the seeder actor.
        $history = $project->statusHistory()
            ->whereNull('from_status')
            ->latest('id')
            ->first();

        $history?->update([
            'changed_by' => $actor->id,
            'remarks' => 'FY2025 spreadsheet seed record created in Ongoing Profiling.',
        ]);

        $location = ProjectLocation::query()->create([
            'project_id' => $project->id,
            'province_id' => $province->id,
            'municipality_id' => $municipality->id,
            'district' => $municipality->district,
            'sort_order' => 1,
        ]);

        $location->barangays()->attach($barangay->id, [
            'beneficiaries_total' => (int) $row['beneficiaries_total'],
            'beneficiaries_female' => (int) $row['beneficiaries_female'],
        ]);

        ProjectMonitoringDetail::query()->create([
            'project_id' => $project->id,
            'project_series' => $this->nullableText($row['project_series']),
            'proponent' => $this->nullableText($row['proponent']),
            'receipt_month' => $this->nullableText($this->titleCaseMonth($row['receipt_month'])),
            'receipt_datetime' => $this->nullableText($row['receipt_datetime']),
            'process_cycle_days' => null,
            'monitoring_remarks' => $sourceTag,
            'updated_by' => $actor->id,
        ]);
    }

    private function projectRemarks(array $row): string
    {
        return implode(PHP_EOL, [
            self::SEED_MARKER.' Development/test sample imported from '.self::SOURCE_WORKBOOK.'.',
            'Source sheet/row: '.$row['sheet'].' / '.$row['row'],
            'Source ADL no.: '.$row['adl_number'],
            'Source project code: '.($row['source_project_code'] ?: '—'),
            'Source status: '.($row['source_status'] ?: 'Blank'),
            'Seeder status override: '.ProjectStatus::ONGOING_PROFILING->label(),
            'Source location text: '.$row['source_barangay'].', '.$row['source_municipality'].', '.$row['source_province'].' ('.$row['source_district'].')',
            'Resolved PSGC: municipality '.$row['municipality_psgc'].'; barangay '.$row['barangay_psgc'].'.',
            'Source beneficiary type: '.($row['beneficiary_type'] ?: '—'),
            'Source displacement type: '.($row['displacement_type'] ?: '—'),
            'Source equity (AV-related project counterpart): PHP '.$this->money($row['equity']).'.',
            'Source total project cost including equity: PHP '.$this->money($row['source_total_project_cost']).'.',
        ]);
    }

    private function implementationMode(string $source): ImplementationMode
    {
        $normalized = strtolower(trim($source));

        return str_contains($normalized, 'acp')
            ? ImplementationMode::THROUGH_ACP
            : ImplementationMode::DIRECT_ADMINISTRATION;
    }

    private function titleCaseMonth(string $month): string
    {
        return $month === '' ? '' : ucfirst(strtolower($month));
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function money(mixed $value): string
    {
        return $this->centsToMoney($this->moneyToCents($value));
    }

    private function moneyToCents(mixed $value): int
    {
        $normalized = str_replace([',', ' '], '', (string) ($value ?? '0'));

        if ($normalized === '' || ! is_numeric($normalized)) {
            throw new RuntimeException('Invalid money value ['.(string) $value.'] in FY2025 seed source.');
        }

        return (int) round(((float) $normalized) * 100);
    }

    private function centsToMoney(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function rowLabel(array $row): string
    {
        return self::SOURCE_WORKBOOK.' '.$row['sheet'].' row '.$row['row'];
    }

    /**
     * Reviewed 30-row selection from FY2025 TUPAD DATABASED.xlsx.
     * Exactly five source rows are included for each Bicol province.
     * PSGC codes were resolved against the project's generated Bicol reference/label layer.
     *
     * @return array<int, array<string, mixed>>
     */
    private function sourceRows(): array
    {
        return [
            [
                'sheet' => 'ALBAY',
                'row' => 26,
                'source_status' => 'Completed/ Claimed',
                'adl_number' => '2025-01-0008',
                'fund_sponsor' => 'RMF',
                'partner' => 'RDs Initiative',
                'source_project_code' => 'TUPAD-RO5-APO-SDA-25-03-20',
                'project_series' => 'RO5-APFO-WP-2025-03-1540',
                'receipt_month' => 'MARCH',
                'receipt_datetime' => '2025-03-13 00:00:00',
                'date_received' => '2025-03-13',
                'project_title' => 'Paglinis at Pagtanim sa Ating Kapaligiran upang Makatulong sa Ating Bayan',
                'nature_of_work' => 'Community Gardening, Street and Sidewalk Sweeping and Cleaning of Public Facilities, Cleaning and Declogging of Canals',
                'proponent' => 'DOLE APO',
                'implementation_mode' => 'Direct Administration',
                'number_of_days' => 20,
                'source_barangay' => 'San Andres',
                'source_municipality' => 'Sto. Domingo',
                'source_province' => 'Albay',
                'source_district' => '1st',
                'income_class' => '2nd Class',
                'beneficiaries_total' => 60,
                'beneficiaries_female' => 44,
                'beneficiary_type' => 'self employed and under employed',
                'displacement_type' => 'Economic crisis',
                'wages_total' => '474000.00',
                'ppe_total' => '47500.00',
                'insurance_total' => '3000.00',
                'requested_to_dole' => '524500.00',
                'service_fee' => '1200.00',
                'equity' => '131125.00',
                'source_total_project_cost' => '655625.00',
                'wage_rate' => '395.00',
                'insurance_rate' => '50.00',
                'municipality_psgc' => '050516000',
                'municipality_name' => 'Santo Domingo',
                'barangay_psgc' => '050516019',
                'barangay_name' => 'San Andres',
            ],
            [
                'sheet' => 'ALBAY',
                'row' => 30,
                'source_status' => 'Completed/ Claimed',
                'adl_number' => '2025-01-0008',
                'fund_sponsor' => 'RMF',
                'partner' => 'RDs Initiative',
                'source_project_code' => 'TUPAD-RO5-APO-LIB-25-03-22',
                'project_series' => 'RO5-APFO-WP-2025-03-1546',
                'receipt_month' => 'MARCH',
                'receipt_datetime' => '2025-03-19 00:00:00',
                'date_received' => '2025-03-19',
                'project_title' => 'Hands Together for a Cleaner Tommorow in Libon',
                'nature_of_work' => 'Street and Sidewalk Sweeping, Maintenance of Community Garden, Cleaning of Public Facilities',
                'proponent' => 'DOLE APO',
                'implementation_mode' => 'Direct Administration',
                'number_of_days' => 10,
                'source_barangay' => 'San Jose',
                'source_municipality' => 'Libon',
                'source_province' => 'Albay',
                'source_district' => '3rd',
                'income_class' => '1st Class',
                'beneficiaries_total' => 99,
                'beneficiaries_female' => 76,
                'beneficiary_type' => 'self employed and under employed',
                'displacement_type' => 'Economic crisis',
                'wages_total' => '391050.00',
                'ppe_total' => '34650.00',
                'insurance_total' => '4950.00',
                'requested_to_dole' => '430650.00',
                'service_fee' => '1980.00',
                'equity' => '107662.50',
                'source_total_project_cost' => '538312.50',
                'wage_rate' => '395.00',
                'insurance_rate' => '50.00',
                'municipality_psgc' => '050507000',
                'municipality_name' => 'Libon',
                'barangay_psgc' => '050507040',
                'barangay_name' => 'San Jose',
            ],
            [
                'sheet' => 'ALBAY',
                'row' => 33,
                'source_status' => 'Completed/ Claimed',
                'adl_number' => '2025-01-0008-1',
                'fund_sponsor' => 'AKB PL-1',
                'partner' => 'AKB PL-1',
                'source_project_code' => 'TUPAD-RO5-APO-BAC-25-03-26',
                'project_series' => 'RO5-APFO-WP-2025-03-1551',
                'receipt_month' => 'MARCH',
                'receipt_datetime' => '2025-03-28 00:00:00',
                'date_received' => '2025-03-28',
                'project_title' => 'MANGROVES: A TREASURE TROVE OF NATURAL ASSETS',
                'nature_of_work' => 'River/Coastal Clean Up, Planting of Mangrove and Street and Sidewalk Sweeping',
                'proponent' => 'DOLE APO',
                'implementation_mode' => 'Direct Administration',
                'number_of_days' => 10,
                'source_barangay' => 'Buang',
                'source_municipality' => 'Bacacay',
                'source_province' => 'Albay',
                'source_district' => '1st',
                'income_class' => '1st Class',
                'beneficiaries_total' => 109,
                'beneficiaries_female' => 53,
                'beneficiary_type' => 'self employed and under employed',
                'displacement_type' => 'Economic crisis',
                'wages_total' => '452350.00',
                'ppe_total' => '91150.00',
                'insurance_total' => '5450.00',
                'requested_to_dole' => '548950.00',
                'service_fee' => '2180.00',
                'equity' => '137237.50',
                'source_total_project_cost' => '686187.50',
                'wage_rate' => '415.00',
                'insurance_rate' => '50.00',
                'municipality_psgc' => '050501000',
                'municipality_name' => 'Bacacay',
                'barangay_psgc' => '050501008',
                'barangay_name' => 'Buang',
            ],
            [
                'sheet' => 'ALBAY',
                'row' => 51,
                'source_status' => 'Completed/ Claimed',
                'adl_number' => '2025-03-0268',
                'fund_sponsor' => 'RMF',
                'partner' => 'Unfunded',
                'source_project_code' => 'TUPAD-RO5-APO-GNBTN-25-06-08',
                'project_series' => 'RO5-APFO-WP-2025-05-1570',
                'receipt_month' => 'JUNE',
                'receipt_datetime' => '2025-06-05 00:00:00',
                'date_received' => '2025-06-05',
                'project_title' => 'PRO-FARMER\'S PROJECT: AN INITIATIVE FOR A CLEAN AND GREEN ENVIRONMENT',
                'nature_of_work' => 'Search and Destroy-Dengue Prevention, Tree Planting, Seedling Preparation, Maintenance of Community Garden and Cleaning Facilities',
                'proponent' => 'DOLE-APO',
                'implementation_mode' => 'Direct Administration',
                'number_of_days' => 10,
                'source_barangay' => 'Minto',
                'source_municipality' => 'Guinobatan',
                'source_province' => 'Albay',
                'source_district' => '3rd',
                'income_class' => '1st Class',
                'beneficiaries_total' => 49,
                'beneficiaries_female' => 21,
                'beneficiary_type' => 'Laid-off or terminated workers due to retrenchment or permanent closure of an estab.',
                'displacement_type' => 'Economic crisis',
                'wages_total' => '203350.00',
                'ppe_total' => '17150.00',
                'insurance_total' => '2450.00',
                'requested_to_dole' => '222950.00',
                'service_fee' => '980.00',
                'equity' => '55737.50',
                'source_total_project_cost' => '278687.50',
                'wage_rate' => '415.00',
                'insurance_rate' => '50.00',
                'municipality_psgc' => '050504000',
                'municipality_name' => 'Guinobatan',
                'barangay_psgc' => '050504031',
                'barangay_name' => 'Minto',
            ],
            [
                'sheet' => 'ALBAY',
                'row' => 55,
                'source_status' => 'Completed/ Claimed',
                'adl_number' => '2025-03-0268',
                'fund_sponsor' => 'RMF',
                'partner' => 'Unfunded',
                'source_project_code' => 'TUPAD-RO5-APO-OAS-25-06-09',
                'project_series' => 'RO5-APFO-WP-2025-06-1577',
                'receipt_month' => 'JUNE',
                'receipt_datetime' => '2025-06-09 00:00:00',
                'date_received' => '2025-06-09',
                'project_title' => 'Camagong\'s Mission is to Stop pollution and Planting is the Solution',
                'nature_of_work' => 'Search and Destroy-dengue Prevention, Tree Planting and Maintenance of Community Garden and Public Facilities',
                'proponent' => 'DOLE APO',
                'implementation_mode' => 'Direct Administration',
                'number_of_days' => 10,
                'source_barangay' => 'Brgy. Camagong',
                'source_municipality' => 'Oas',
                'source_province' => 'Albay',
                'source_district' => '3rd',
                'income_class' => '1st Class',
                'beneficiaries_total' => 87,
                'beneficiaries_female' => 49,
                'beneficiary_type' => 'Underemployed/Self-employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '361050.00',
                'ppe_total' => '30450.00',
                'insurance_total' => '4350.00',
                'requested_to_dole' => '395850.00',
                'service_fee' => '1740.00',
                'equity' => '98962.50',
                'source_total_project_cost' => '494812.50',
                'wage_rate' => '415.00',
                'insurance_rate' => '50.00',
                'municipality_psgc' => '050512000',
                'municipality_name' => 'Oas',
                'barangay_psgc' => '050512018',
                'barangay_name' => 'Camagong',
            ],
            [
                'sheet' => 'CAMNORTE',
                'row' => 8,
                'source_status' => 'Completed/ Claimed',
                'adl_number' => '2025-01-0008',
                'fund_sponsor' => 'Cong Panotes',
                'partner' => 'Cong Panotes',
                'source_project_code' => 'TUPAD-RO5-CNPO-BSD-GNTN-25-03-04',
                'project_series' => 'RO5-CNFO-WP-2025-02-0515',
                'receipt_month' => 'MARCH',
                'receipt_datetime' => '2025-03-04 00:00:00',
                'date_received' => '2025-03-04',
                'project_title' => 'Pag may Tanim may Aanihin Project',
                'nature_of_work' => 'Streets/Roads and Sidewalks Sweeping, Cleaning of Public Facilities and Community Gardening',
                'proponent' => 'DOLE-CNPO',
                'implementation_mode' => 'Direct Administration',
                'number_of_days' => 10,
                'source_barangay' => 'Guinatungan',
                'source_municipality' => 'Basud',
                'source_province' => 'Camarines Norte',
                'source_district' => '2nd',
                'income_class' => '1st Class',
                'beneficiaries_total' => 93,
                'beneficiaries_female' => 46,
                'beneficiary_type' => 'Self Employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '367350.00',
                'ppe_total' => '32550.00',
                'insurance_total' => '4650.00',
                'requested_to_dole' => '404550.00',
                'service_fee' => '1860.00',
                'equity' => '101137.50',
                'source_total_project_cost' => '505687.50',
                'wage_rate' => '395.00',
                'insurance_rate' => '50.00',
                'municipality_psgc' => '051601000',
                'municipality_name' => 'Basud',
                'barangay_psgc' => '051601005',
                'barangay_name' => 'Guinatungan',
            ],
            [
                'sheet' => 'CAMNORTE',
                'row' => 16,
                'source_status' => 'Completed/ Claimed',
                'adl_number' => '2025-01-0008',
                'fund_sponsor' => 'RMF',
                'partner' => 'RDs Initiative',
                'source_project_code' => 'TUPAD-RO5-CNPO-MER-TRUM-25-03-12',
                'project_series' => 'RO5-CNFO-WP-2025-03-0525',
                'receipt_month' => 'MARCH',
                'receipt_datetime' => '2025-03-12 00:00:00',
                'date_received' => '2025-03-12',
                'project_title' => 'Seeds of Change: Cultivating Community through Vegetable Gardening',
                'nature_of_work' => 'Community Vegetable Gardening',
                'proponent' => 'DOLE-CNPO',
                'implementation_mode' => 'Direct Administration',
                'number_of_days' => 20,
                'source_barangay' => 'Tarum',
                'source_municipality' => 'Mercedes',
                'source_province' => 'Camarines Norte',
                'source_district' => '2nd',
                'income_class' => '1st Class',
                'beneficiaries_total' => 60,
                'beneficiaries_female' => 29,
                'beneficiary_type' => 'Self Employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '474000.00',
                'ppe_total' => '21000.00',
                'insurance_total' => '3000.00',
                'requested_to_dole' => '498000.00',
                'service_fee' => '1200.00',
                'equity' => '124500.00',
                'source_total_project_cost' => '622500.00',
                'wage_rate' => '395.00',
                'insurance_rate' => '50.00',
                'municipality_psgc' => '051607000',
                'municipality_name' => 'Mercedes',
                'barangay_psgc' => '051607026',
                'barangay_name' => 'Tarum',
            ],
            [
                'sheet' => 'CAMNORTE',
                'row' => 19,
                'source_status' => 'Completed/ Claimed',
                'adl_number' => '2025-01-0008',
                'fund_sponsor' => 'RMF',
                'partner' => 'RDs Initiative',
                'source_project_code' => 'TUPAD-RO5-CNPO-CAP-PBLC-25-03-17',
                'project_series' => 'RO5-CNFO-WP-2025-03-0530',
                'receipt_month' => 'MARCH',
                'receipt_datetime' => '2025-03-14 00:00:00',
                'date_received' => '2025-03-14',
                'project_title' => 'Gulayan at Kalinisan Pagtulungan para sa Maunlad na Barangay',
                'nature_of_work' => 'Vegetable Gardening and Cleaning of Roads/Public Facilities',
                'proponent' => 'DOLE-CNPO',
                'implementation_mode' => 'Direct Administration',
                'number_of_days' => 20,
                'source_barangay' => 'Poblacion',
                'source_municipality' => 'Capalonga',
                'source_province' => 'Camarines Norte',
                'source_district' => '1st',
                'income_class' => '1st Class',
                'beneficiaries_total' => 60,
                'beneficiaries_female' => 47,
                'beneficiary_type' => 'Self Employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '474000.00',
                'ppe_total' => '21000.00',
                'insurance_total' => '3000.00',
                'requested_to_dole' => '498000.00',
                'service_fee' => '1200.00',
                'equity' => '124500.00',
                'source_total_project_cost' => '622500.00',
                'wage_rate' => '395.00',
                'insurance_rate' => '50.00',
                'municipality_psgc' => '051602000',
                'municipality_name' => 'Capalonga',
                'barangay_psgc' => '051602014',
                'barangay_name' => 'Poblacion',
            ],
            [
                'sheet' => 'CAMNORTE',
                'row' => 52,
                'source_status' => 'Completed/ Claimed',
                'adl_number' => '2025-02-0160',
                'fund_sponsor' => 'RMF',
                'partner' => 'DSWD (Lawat at Binhi)',
                'source_project_code' => 'TUPAD-RO5-CNPO-PAR-CASA-25-04-01',
                'project_series' => 'RO5-CNFO-WP-2025-03-0547',
                'receipt_month' => 'MARCH',
                'receipt_datetime' => '2025-03-26 00:00:00',
                'date_received' => '2025-03-26',
                'project_title' => 'Empowering Communities through Cash for Work: DOLE-TUPAD & DSWD Convergence through Project Local Adaptation to Water Access (Lawa) and Breaking Insufficiency through Nutritious Harvest for the Impoverished (Binhi) 2',
                'nature_of_work' => 'Community Gardening and Construction of Small Farm Reservoir',
                'proponent' => 'DOLE-CNPO',
                'implementation_mode' => 'Direct Administration',
                'number_of_days' => 19,
                'source_barangay' => 'Casalugan',
                'source_municipality' => 'Paracale',
                'source_province' => 'Camarines Norte',
                'source_district' => '1st',
                'income_class' => '1st Class',
                'beneficiaries_total' => 42,
                'beneficiaries_female' => 27,
                'beneficiary_type' => 'Self Employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '331170.00',
                'ppe_total' => '35700.00',
                'insurance_total' => '2100.00',
                'requested_to_dole' => '368970.00',
                'service_fee' => '840.00',
                'equity' => '92242.50',
                'source_total_project_cost' => '461212.50',
                'wage_rate' => '415.00',
                'insurance_rate' => '50.00',
                'municipality_psgc' => '051608000',
                'municipality_name' => 'Paracale',
                'barangay_psgc' => '051608007',
                'barangay_name' => 'Casalugan',
            ],
            [
                'sheet' => 'CAMNORTE',
                'row' => 56,
                'source_status' => 'Completed/ Claimed',
                'adl_number' => '2025-01-0008',
                'fund_sponsor' => 'AKB PL',
                'partner' => 'AKB PL',
                'source_project_code' => 'TUPAD-RO5-CNPO-LAB-MLYA-25-05-02',
                'project_series' => 'RO5-CNFO-WP-2025-05-0549',
                'receipt_month' => 'MAY',
                'receipt_datetime' => '2025-05-15 00:00:00',
                'date_received' => '2025-05-15',
                'project_title' => 'Greening for Recovery with Magkamatao Miners',
                'nature_of_work' => 'Cleaning of Roads and Tree Planting',
                'proponent' => 'DOLE-CNPO',
                'implementation_mode' => 'Direct Administration',
                'number_of_days' => 10,
                'source_barangay' => 'Malaya',
                'source_municipality' => 'Labo',
                'source_province' => 'Camarines Norte',
                'source_district' => '1st',
                'income_class' => '1st Class',
                'beneficiaries_total' => 98,
                'beneficiaries_female' => 76,
                'beneficiary_type' => 'Self Employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '406700.00',
                'ppe_total' => '34300.00',
                'insurance_total' => '4900.00',
                'requested_to_dole' => '445900.00',
                'service_fee' => '1960.00',
                'equity' => '111475.00',
                'source_total_project_cost' => '557375.00',
                'wage_rate' => '415.00',
                'insurance_rate' => '50.00',
                'municipality_psgc' => '051606000',
                'municipality_name' => 'Labo',
                'barangay_psgc' => '051606038',
                'barangay_name' => 'Malaya',
            ],
            [
                'sheet' => 'CAMSUR',
                'row' => 8,
                'source_status' => 'Completed/ Claimed',
                'adl_number' => '2025-01-0008',
                'fund_sponsor' => 'RMF',
                'partner' => 'RDs Initiative',
                'source_project_code' => 'TUPAD-RO5-CSPO-SIPO-25-03-02',
                'project_series' => 'RO5-CSFO-WP-2025-02-0819',
                'receipt_month' => 'MARCH',
                'receipt_datetime' => '2025-03-03 15:45:00',
                'date_received' => '2025-03-03',
                'project_title' => 'Clean & Green: Cultivating Community',
                'nature_of_work' => 'Clean Up Drive, Cleaning of Public Facilities and Community Gardening',
                'proponent' => 'DOLE CSPO',
                'implementation_mode' => 'Direct Administration',
                'number_of_days' => 20,
                'source_barangay' => 'Tara-60',
                'source_municipality' => 'Sipocot',
                'source_province' => 'Camarines Sur',
                'source_district' => '1st',
                'income_class' => '1st Class',
                'beneficiaries_total' => 60,
                'beneficiaries_female' => 29,
                'beneficiary_type' => 'Underemployed and Self employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '474000.00',
                'ppe_total' => '21000.00',
                'insurance_total' => '3360.00',
                'requested_to_dole' => '498360.00',
                'service_fee' => '1200.00',
                'equity' => '124590.00',
                'source_total_project_cost' => '622950.00',
                'wage_rate' => '395.00',
                'insurance_rate' => '56.00',
                'municipality_psgc' => '051734000',
                'municipality_name' => 'Sipocot',
                'barangay_psgc' => '051734043',
                'barangay_name' => 'Tara',
            ],
            [
                'sheet' => 'CAMSUR',
                'row' => 13,
                'source_status' => 'Completed/ Claimed',
                'adl_number' => '2025-01-0008',
                'fund_sponsor' => 'RMF',
                'partner' => 'RDs Initiative',
                'source_project_code' => 'TUPAD-RO5-CSPO-RAGY-25-03-14',
                'project_series' => 'RO5-CSFO-WP-2025-03-0825',
                'receipt_month' => 'MARCH',
                'receipt_datetime' => '2025-03-10 13:30:00',
                'date_received' => '2025-03-10',
                'project_title' => 'Gulayan sa Barangay: Masagana at Malusog na Pamumuhay',
                'nature_of_work' => 'Clean Up Drive, Cleaning of Public Facilities and Community Gardening',
                'proponent' => 'DOLE CSPO',
                'implementation_mode' => 'Direct Administration',
                'number_of_days' => 20,
                'source_barangay' => 'Panaytayan-60',
                'source_municipality' => 'Ragay',
                'source_province' => 'Camarines Sur',
                'source_district' => '1st',
                'income_class' => '1st Class',
                'beneficiaries_total' => 60,
                'beneficiaries_female' => 47,
                'beneficiary_type' => 'Underemployed and Self employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '474000.00',
                'ppe_total' => '21000.00',
                'insurance_total' => '3360.00',
                'requested_to_dole' => '498360.00',
                'service_fee' => '1200.00',
                'equity' => '124590.00',
                'source_total_project_cost' => '622950.00',
                'wage_rate' => '395.00',
                'insurance_rate' => '56.00',
                'municipality_psgc' => '051730000',
                'municipality_name' => 'Ragay',
                'barangay_psgc' => '051730026',
                'barangay_name' => 'Panaytayan',
            ],
            [
                'sheet' => 'CAMSUR',
                'row' => 28,
                'source_status' => 'Completed/ Claimed',
                'adl_number' => '2025-01-0008',
                'fund_sponsor' => 'RMF',
                'partner' => 'RDs Initiative',
                'source_project_code' => 'TUPAD-RO5-CSPO-CMAL-25-03-25',
                'project_series' => 'RO5-CSFO-WP-2025-02-0817',
                'receipt_month' => 'MARCH',
                'receipt_datetime' => '2025-03-10 14:55:59',
                'date_received' => '2025-03-10',
                'project_title' => 'Cultivating a Community: A Neighborhood Vegetable Garden',
                'nature_of_work' => 'Community Gardening',
                'proponent' => 'DOLE CSPO',
                'implementation_mode' => 'Direct Administration',
                'number_of_days' => 20,
                'source_barangay' => 'Brgy. San Roque-60',
                'source_municipality' => 'Camaligan',
                'source_province' => 'Camarines Sur',
                'source_district' => '3rd',
                'income_class' => '4th Class',
                'beneficiaries_total' => 60,
                'beneficiaries_female' => 23,
                'beneficiary_type' => 'Underemployed and Self employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '474000.00',
                'ppe_total' => '21000.00',
                'insurance_total' => '3360.00',
                'requested_to_dole' => '498360.00',
                'service_fee' => '1200.00',
                'equity' => '124590.00',
                'source_total_project_cost' => '622950.00',
                'wage_rate' => '395.00',
                'insurance_rate' => '56.00',
                'municipality_psgc' => '051709000',
                'municipality_name' => 'Camaligan',
                'barangay_psgc' => '051709013',
                'barangay_name' => 'San Roque',
            ],
            [
                'sheet' => 'CAMSUR',
                'row' => 29,
                'source_status' => 'Completed/ Claimed',
                'adl_number' => '2025-01-0008',
                'fund_sponsor' => 'RMF',
                'partner' => 'RDs Initiative',
                'source_project_code' => 'TUPAD-RO5-CSPO-PILI-25-03-26',
                'project_series' => 'RO5-CSFO-WP-2025-02-0837',
                'receipt_month' => 'MARCH',
                'receipt_datetime' => '2025-03-16 16:00:00',
                'date_received' => '2025-03-16',
                'project_title' => 'Cultivating a Community: A Neighborhood Vegetable Garden',
                'nature_of_work' => 'Community Gardening',
                'proponent' => 'DOLE CSPO',
                'implementation_mode' => 'Direct Administration',
                'number_of_days' => 20,
                'source_barangay' => 'Brgy. Cadlan',
                'source_municipality' => 'Pili',
                'source_province' => 'Camarines Sur',
                'source_district' => '3rd',
                'income_class' => '1st Class',
                'beneficiaries_total' => 60,
                'beneficiaries_female' => 43,
                'beneficiary_type' => 'Underemployed and Self employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '474000.00',
                'ppe_total' => '21000.00',
                'insurance_total' => '3360.00',
                'requested_to_dole' => '498360.00',
                'service_fee' => '1200.00',
                'equity' => '124590.00',
                'source_total_project_cost' => '622950.00',
                'wage_rate' => '395.00',
                'insurance_rate' => '56.00',
                'municipality_psgc' => '051728000',
                'municipality_name' => 'Pili',
                'barangay_psgc' => '051728005',
                'barangay_name' => 'Cadlan',
            ],
            [
                'sheet' => 'CAMSUR',
                'row' => 34,
                'source_status' => 'Completed/ Claimed',
                'adl_number' => '2025-01-0008',
                'fund_sponsor' => 'RMF',
                'partner' => 'RDs Initiative',
                'source_project_code' => 'TUPAD-RO5-CSPO-BULA-25-03-31',
                'project_series' => 'RO5-CSFO-WP-2025-03-0849',
                'receipt_month' => 'MARCH',
                'receipt_datetime' => '2025-03-19 08:44:59',
                'date_received' => '2025-03-19',
                'project_title' => 'Gulayan sa Barangay: Nourishing Homes & Foestering Community',
                'nature_of_work' => 'Clean Up Drive and Community Gardening (Planting and Maintenance)',
                'proponent' => 'DOLE CSPO',
                'implementation_mode' => 'Direct Administration',
                'number_of_days' => 20,
                'source_barangay' => 'La Victoria-60',
                'source_municipality' => 'Bula',
                'source_province' => 'Camarines Sur',
                'source_district' => '5th',
                'income_class' => '1st Class',
                'beneficiaries_total' => 60,
                'beneficiaries_female' => 33,
                'beneficiary_type' => 'Underemployed and Self employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '474000.00',
                'ppe_total' => '21000.00',
                'insurance_total' => '3360.00',
                'requested_to_dole' => '498360.00',
                'service_fee' => '1200.00',
                'equity' => '124590.00',
                'source_total_project_cost' => '622950.00',
                'wage_rate' => '395.00',
                'insurance_rate' => '56.00',
                'municipality_psgc' => '051706000',
                'municipality_name' => 'Bula',
                'barangay_psgc' => '051706013',
                'barangay_name' => 'La Victoria',
            ],
            [
                'sheet' => 'CATANDUANES',
                'row' => 28,
                'source_status' => 'Completed/ Claimed',
                'adl_number' => '2025-02-0185',
                'fund_sponsor' => 'Cong. Leo Rodriguez',
                'partner' => 'Cong. Leo Rodriguez',
                'source_project_code' => 'TUPAD-RO5-CFO-VIR-25-03-23',
                'project_series' => 'RO5-CFO-WP-2025-03-0448',
                'receipt_month' => 'MARCH',
                'receipt_datetime' => '2025-03-25 15:24:59',
                'date_received' => '2025-03-25',
                'project_title' => 'ALISTOng Pagtanom Borogkos sa Kabuhayan in Virac TUPAD Program',
                'nature_of_work' => 'Street and Sidewalk Cleaning, and Community Gardening',
                'proponent' => 'DOLE CPO',
                'implementation_mode' => 'Direct Administration',
                'number_of_days' => 10,
                'source_barangay' => 'Sogod-Tibgao-36',
                'source_municipality' => 'Virac',
                'source_province' => 'Catanduanes',
                'source_district' => 'Lone',
                'income_class' => '1st Class',
                'beneficiaries_total' => 36,
                'beneficiaries_female' => 21,
                'beneficiary_type' => 'Underemployed and Self employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '142200.00',
                'ppe_total' => '12600.00',
                'insurance_total' => '2016.00',
                'requested_to_dole' => '156816.00',
                'service_fee' => '720.00',
                'equity' => '39204.00',
                'source_total_project_cost' => '196020.00',
                'wage_rate' => '395.00',
                'insurance_rate' => '56.00',
                'municipality_psgc' => '052011000',
                'municipality_name' => 'Virac',
                'barangay_psgc' => '052011063',
                'barangay_name' => 'Sogod-Tibgao',
            ],
            [
                'sheet' => 'CATANDUANES',
                'row' => 45,
                'source_status' => 'Completed/ Claimed',
                'adl_number' => '2025-03-0293',
                'fund_sponsor' => 'Kalinga PL',
                'partner' => 'Kalinga PL',
                'source_project_code' => 'TUPAD-RO5-CFO-VIR-25-04-05',
                'project_series' => 'RO5-CFO-WP-2025-04-0456',
                'receipt_month' => 'APRIL',
                'receipt_datetime' => '2025-04-22 08:39:00',
                'date_received' => '2025-04-22',
                'project_title' => 'Kalinga sa Barangay Tubaon TUPAD Program',
                'nature_of_work' => 'Cleaning of Public Facilities, Community Vegetable Gardening, De-clogging of drainage and creeks, Painting of Public Schools and Facilities',
                'proponent' => 'DOLE CPO',
                'implementation_mode' => 'Direct Administration',
                'number_of_days' => 10,
                'source_barangay' => 'Tubaon-99',
                'source_municipality' => 'Virac',
                'source_province' => 'Catanduanes',
                'source_district' => 'Lone',
                'income_class' => '1st Class',
                'beneficiaries_total' => 99,
                'beneficiaries_female' => 56,
                'beneficiary_type' => 'Underemployed and Self employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '410850.00',
                'ppe_total' => '34650.00',
                'insurance_total' => '5544.00',
                'requested_to_dole' => '451044.00',
                'service_fee' => '1980.00',
                'equity' => '112761.00',
                'source_total_project_cost' => '563805.00',
                'wage_rate' => '415.00',
                'insurance_rate' => '56.00',
                'municipality_psgc' => '052011000',
                'municipality_name' => 'Virac',
                'barangay_psgc' => '052011064',
                'barangay_name' => 'Tubaon',
            ],
            [
                'sheet' => 'CATANDUANES',
                'row' => 70,
                'source_status' => 'Completed/ Claimed',
                'adl_number' => '2025-02-0160',
                'fund_sponsor' => 'RMF',
                'partner' => 'Unfunded',
                'source_project_code' => 'TUPAD-RO5-CFO-BAT-VIR-25-06-03',
                'project_series' => 'RO5-CFO-WP-2025-06-0465',
                'receipt_month' => 'JUNE',
                'receipt_datetime' => '2025-06-19 15:29:59',
                'date_received' => '2025-06-19',
                'project_title' => 'Benteng Bigas Pasa sa Mamamayan in Virac TUPAD Program',
                'nature_of_work' => 'Repacking, Truck loading and unloading of rice and Cleaning of the work area',
                'proponent' => 'DOLE CPO',
                'implementation_mode' => 'Direct Administration',
                'number_of_days' => 10,
                'source_barangay' => 'Talisay-1',
                'source_municipality' => 'Bato',
                'source_province' => 'Catanduanes',
                'source_district' => 'Lone',
                'income_class' => '4th Class',
                'beneficiaries_total' => 10,
                'beneficiaries_female' => 1,
                'beneficiary_type' => 'Underemployed and Self employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '41500.00',
                'ppe_total' => '3500.00',
                'insurance_total' => '560.00',
                'requested_to_dole' => '45560.00',
                'service_fee' => '200.00',
                'equity' => '11390.00',
                'source_total_project_cost' => '56950.00',
                'wage_rate' => '415.00',
                'insurance_rate' => '56.00',
                'municipality_psgc' => '052003000',
                'municipality_name' => 'Bato',
                'barangay_psgc' => '052003024',
                'barangay_name' => 'Talisay',
            ],
            [
                'sheet' => 'CATANDUANES',
                'row' => 76,
                'source_status' => 'Completed/ Claimed',
                'adl_number' => '2025-02-0160',
                'fund_sponsor' => 'RMF',
                'partner' => 'Unfunded',
                'source_project_code' => 'TUPAD-RO5-CFO-BAG-BAR-BAT-SA-SM-VIR-25-07-01',
                'project_series' => 'RO5-CFO-WP-2025-06-0466',
                'receipt_month' => 'JULY',
                'receipt_datetime' => '2025-07-11 11:50:00',
                'date_received' => '2025-07-11',
                'project_title' => 'KADIWA sa Pamayanan: Tindahan ng Sariwa at Abot-Kayang Ani TUPAD Program',
                'nature_of_work' => 'Selling of Products,Cleaning, Setting-Up and maintenance of Kadiwa Sites',
                'proponent' => 'DOLE CPO',
                'implementation_mode' => 'Direct Administration',
                'number_of_days' => 10,
                'source_barangay' => 'Suchan-1',
                'source_municipality' => 'Bagamanoc',
                'source_province' => 'Catanduanes',
                'source_district' => 'Lone',
                'income_class' => '4th Class',
                'beneficiaries_total' => 19,
                'beneficiaries_female' => 15,
                'beneficiary_type' => 'Underemployed and Self employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '78850.00',
                'ppe_total' => '6650.00',
                'insurance_total' => '1064.00',
                'requested_to_dole' => '86564.00',
                'service_fee' => '0.00',
                'equity' => '21641.00',
                'source_total_project_cost' => '108205.00',
                'wage_rate' => '415.00',
                'insurance_rate' => '56.00',
                'municipality_psgc' => '052001000',
                'municipality_name' => 'Bagamanoc',
                'barangay_psgc' => '052001019',
                'barangay_name' => 'Suchan',
            ],
            [
                'sheet' => 'CATANDUANES',
                'row' => 82,
                'source_status' => 'Completed/ Claimed',
                'adl_number' => '2025-01-0008',
                'fund_sponsor' => 'AKB PL',
                'partner' => 'AKB PL',
                'source_project_code' => 'TUPAD-RO5-CFO-VIR-25-07-02',
                'project_series' => 'RO5-CFO-WP-2025-07-0467',
                'receipt_month' => 'JULY',
                'receipt_datetime' => '2025-07-17 15:45:59',
                'date_received' => '2025-07-17',
                'project_title' => 'Agarang Tulong para sa Displaced Quarry Workers in Virac TUPAD Program',
                'nature_of_work' => 'Ecological Renewal: Tree Regeneration in Quarry Site and Vegetable Gardening',
                'proponent' => 'DOLE CPO',
                'implementation_mode' => 'Direct Administration',
                'number_of_days' => 10,
                'source_barangay' => 'Sto. Cristo-29',
                'source_municipality' => 'Virac',
                'source_province' => 'Catanduanes',
                'source_district' => 'Lone',
                'income_class' => '1st Class',
                'beneficiaries_total' => 29,
                'beneficiaries_female' => 7,
                'beneficiary_type' => 'Displaced',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '120350.00',
                'ppe_total' => '10150.00',
                'insurance_total' => '1624.00',
                'requested_to_dole' => '132124.00',
                'service_fee' => '0.00',
                'equity' => '33031.00',
                'source_total_project_cost' => '165155.00',
                'wage_rate' => '415.00',
                'insurance_rate' => '56.00',
                'municipality_psgc' => '052011000',
                'municipality_name' => 'Virac',
                'barangay_psgc' => '052011056',
                'barangay_name' => 'Santo Cristo',
            ],
            [
                'sheet' => 'MASBATE',
                'row' => 25,
                'source_status' => 'Completed/ Claimed',
                'adl_number' => '2025-01-0008',
                'fund_sponsor' => 'RMF',
                'partner' => 'RDs Initiative',
                'source_project_code' => 'TUPAD-RO5-MPO-CAW-25-03-021',
                'project_series' => 'RO5-MPO-WP-2025-03-0668',
                'receipt_month' => 'MARCH',
                'receipt_datetime' => '2025-03-12 00:00:00',
                'date_received' => '2025-03-12',
                'project_title' => 'Growing Green: A Guide to Planting for a Sustainable Future',
                'nature_of_work' => 'Community Vegetable Gardening and Vegetable Farning the require Land Preparation',
                'proponent' => 'DOLE-MPO-Cawayan',
                'implementation_mode' => 'Direct Administration',
                'number_of_days' => 20,
                'source_barangay' => 'Poblacion',
                'source_municipality' => 'Cawayan',
                'source_province' => 'Masbate',
                'source_district' => '3rd',
                'income_class' => '1st Class',
                'beneficiaries_total' => 50,
                'beneficiaries_female' => 37,
                'beneficiary_type' => 'A',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '395000.00',
                'ppe_total' => '17500.00',
                'insurance_total' => '2800.00',
                'requested_to_dole' => '415300.00',
                'service_fee' => '1000.00',
                'equity' => '103825.00',
                'source_total_project_cost' => '519125.00',
                'wage_rate' => '395.00',
                'insurance_rate' => '56.00',
                'municipality_psgc' => '054106000',
                'municipality_name' => 'Cawayan',
                'barangay_psgc' => '054106022',
                'barangay_name' => 'Poblacion',
            ],
            [
                'sheet' => 'MASBATE',
                'row' => 33,
                'source_status' => 'Completed/ Liquidated',
                'adl_number' => '2025-01-0008',
                'fund_sponsor' => 'Cong. Wilton Kho',
                'partner' => 'Cong. Wilton Kho',
                'source_project_code' => 'TUPAD-RO5-MPO-DIM-BANA-25-03-29',
                'project_series' => 'RO5-MFO-WP-2025-03-0679',
                'receipt_month' => 'MARCH',
                'receipt_datetime' => '2025-03-25 00:00:00',
                'date_received' => '2025-03-25',
                'project_title' => 'Street Sweeping and Cleaning: Ensuring Accessibility For All',
                'nature_of_work' => 'Street and Sidewalk Sweeping and Cleaning of Public Facilties',
                'proponent' => 'BLGU-Banahao, Dimasalang',
                'implementation_mode' => 'thru ACP',
                'number_of_days' => 15,
                'source_barangay' => 'Banahao',
                'source_municipality' => 'Dimasalang',
                'source_province' => 'Masbate',
                'source_district' => '3rd',
                'income_class' => '3rd Class',
                'beneficiaries_total' => 30,
                'beneficiaries_female' => 17,
                'beneficiary_type' => 'A',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '177750.00',
                'ppe_total' => '10500.00',
                'insurance_total' => '1680.00',
                'requested_to_dole' => '189930.00',
                'service_fee' => '600.00',
                'equity' => '47482.50',
                'source_total_project_cost' => '237412.50',
                'wage_rate' => '395.00',
                'insurance_rate' => '56.00',
                'municipality_psgc' => '054108000',
                'municipality_name' => 'Dimasalang',
                'barangay_psgc' => '054108003',
                'barangay_name' => 'Banahao',
            ],
            [
                'sheet' => 'MASBATE',
                'row' => 34,
                'source_status' => 'For Liquidation',
                'adl_number' => '2025-01-0008',
                'fund_sponsor' => 'Cong. Wilton Kho',
                'partner' => 'Cong. Wilton Kho',
                'source_project_code' => 'TUPAD-RO5-MPO-DIM-CDLN-25-03-30',
                'project_series' => 'RO5-MFO-WP-2025-03-0675',
                'receipt_month' => 'MARCH',
                'receipt_datetime' => '2025-03-25 00:00:00',
                'date_received' => '2025-03-25',
                'project_title' => 'Street Sweeping and Cleaning: Ensuring Accessibility For All',
                'nature_of_work' => 'Street and Sidewalk Sweeping and Cleaning of Public Facilties',
                'proponent' => 'BLGU-Cadulan,  Dimasalang',
                'implementation_mode' => 'thru ACP',
                'number_of_days' => 15,
                'source_barangay' => 'Cadulan',
                'source_municipality' => 'Dimasalang',
                'source_province' => 'Masbate',
                'source_district' => '3rd',
                'income_class' => '3rd Class',
                'beneficiaries_total' => 30,
                'beneficiaries_female' => 14,
                'beneficiary_type' => 'A',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '177750.00',
                'ppe_total' => '10500.00',
                'insurance_total' => '1680.00',
                'requested_to_dole' => '189930.00',
                'service_fee' => '600.00',
                'equity' => '47482.50',
                'source_total_project_cost' => '237412.50',
                'wage_rate' => '395.00',
                'insurance_rate' => '56.00',
                'municipality_psgc' => '054108000',
                'municipality_name' => 'Dimasalang',
                'barangay_psgc' => '054108008',
                'barangay_name' => 'Cadulan',
            ],
            [
                'sheet' => 'MASBATE',
                'row' => 35,
                'source_status' => 'Completed/ Liquidated',
                'adl_number' => '2025-01-0008',
                'fund_sponsor' => 'Cong. Wilton Kho',
                'partner' => 'Cong. Wilton Kho',
                'source_project_code' => 'TUPAD-RO5-MPO-DIM-DVSR-25-03-31',
                'project_series' => 'RO5-MFO-WP-2025-03-06777',
                'receipt_month' => 'MARCH',
                'receipt_datetime' => '2025-03-25 00:00:00',
                'date_received' => '2025-03-25',
                'project_title' => 'Street Sweeping and Cleaning: Ensuring Accessibility For All',
                'nature_of_work' => 'Street and Sidewalk Sweeping and Cleaning of Public Facilties',
                'proponent' => 'BLGU-Divisoria, Dimasalang',
                'implementation_mode' => 'thru ACP',
                'number_of_days' => 15,
                'source_barangay' => 'Divisoria',
                'source_municipality' => 'Dimasalang',
                'source_province' => 'Masbate',
                'source_district' => '3rd',
                'income_class' => '3rd Class',
                'beneficiaries_total' => 30,
                'beneficiaries_female' => 7,
                'beneficiary_type' => 'A',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '177750.00',
                'ppe_total' => '10500.00',
                'insurance_total' => '1680.00',
                'requested_to_dole' => '189930.00',
                'service_fee' => '600.00',
                'equity' => '47482.50',
                'source_total_project_cost' => '237412.50',
                'wage_rate' => '395.00',
                'insurance_rate' => '56.00',
                'municipality_psgc' => '054108000',
                'municipality_name' => 'Dimasalang',
                'barangay_psgc' => '054108011',
                'barangay_name' => 'Divisoria',
            ],
            [
                'sheet' => 'MASBATE',
                'row' => 36,
                'source_status' => 'For Liquidation',
                'adl_number' => '2025-01-0008',
                'fund_sponsor' => 'Cong. Wilton Kho',
                'partner' => 'Cong. Wilton Kho',
                'source_project_code' => 'TUPAD-RO5-MPO-DIM-MGCGT-25-03-32',
                'project_series' => 'RO5-MFO-WP-2025-03-0680',
                'receipt_month' => 'MARCH',
                'receipt_datetime' => '2025-03-25 00:00:00',
                'date_received' => '2025-03-25',
                'project_title' => 'Street Sweeping and Cleaning: Ensuring Accessibility For All',
                'nature_of_work' => 'Street and Sidewalk Sweeping and Cleaning of Public Facilties',
                'proponent' => 'BLGU-Magcaraguit, Dimasalang',
                'implementation_mode' => 'thru ACP',
                'number_of_days' => 15,
                'source_barangay' => 'Magcaraguit',
                'source_municipality' => 'Dimasalang',
                'source_province' => 'Masbate',
                'source_district' => '3rd',
                'income_class' => '3rd Class',
                'beneficiaries_total' => 30,
                'beneficiaries_female' => 19,
                'beneficiary_type' => 'A',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '177750.00',
                'ppe_total' => '10500.00',
                'insurance_total' => '1680.00',
                'requested_to_dole' => '189930.00',
                'service_fee' => '600.00',
                'equity' => '47482.50',
                'source_total_project_cost' => '237412.50',
                'wage_rate' => '395.00',
                'insurance_rate' => '56.00',
                'municipality_psgc' => '054108000',
                'municipality_name' => 'Dimasalang',
                'barangay_psgc' => '054108014',
                'barangay_name' => 'Magcaraguit',
            ],
            [
                'sheet' => 'SORSOGON',
                'row' => 6,
                'source_status' => 'For Payment',
                'adl_number' => '2025-03-0268',
                'fund_sponsor' => 'RMF',
                'partner' => 'Unfunded',
                'source_project_code' => 'TUPAD-RO5-SPO-SRC-GMLT-25-03-02',
                'project_series' => 'R05-SFO-WP-2025-01-0568',
                'receipt_month' => 'MARCH',
                'receipt_datetime' => '2025-03-04 08:30:00',
                'date_received' => '2025-03-04',
                'project_title' => 'Emergency Response for Tricycle Drivers Affected by Massive Flooding',
                'nature_of_work' => 'Declogging of Canals, Community Gardening and Cleaning of  of Streets and Public Facilities',
                'proponent' => 'DOLE-SPO',
                'implementation_mode' => 'Direct Administration',
                'number_of_days' => 10,
                'source_barangay' => 'Gimaloto',
                'source_municipality' => 'Sorsogon City',
                'source_province' => 'Sorsogon',
                'source_district' => '1st',
                'income_class' => 'Component City',
                'beneficiaries_total' => 20,
                'beneficiaries_female' => 3,
                'beneficiary_type' => 'Underemployed/Self Employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '79000.00',
                'ppe_total' => '17000.00',
                'insurance_total' => '1000.00',
                'requested_to_dole' => '97000.00',
                'service_fee' => '400.00',
                'equity' => '24250.00',
                'source_total_project_cost' => '121250.00',
                'wage_rate' => '395.00',
                'insurance_rate' => '50.00',
                'municipality_psgc' => '056216000',
                'municipality_name' => 'City of Sorsogon',
                'barangay_psgc' => '056216017',
                'barangay_name' => 'Gimaloto',
            ],
            [
                'sheet' => 'SORSOGON',
                'row' => 56,
                'source_status' => '',
                'adl_number' => '2025-02-0160',
                'fund_sponsor' => 'RMF',
                'partner' => 'BJMP',
                'source_project_code' => 'TUPAD-RO5-SPO-SRC-25-06-01',
                'project_series' => 'R05-SFO-WP-2025-05-609',
                'receipt_month' => 'MAY',
                'receipt_datetime' => '2025-05-29 11:05:00',
                'date_received' => '2025-05-29',
                'project_title' => 'TUPAD behind bars: Reintegration project empowering  PDLS',
                'nature_of_work' => 'Painting of Edifice around Jail Station and Vegetable Gardening/Landscaping',
                'proponent' => 'DOLE-SPO',
                'implementation_mode' => 'Direct Administration',
                'number_of_days' => 20,
                'source_barangay' => 'Cabid-an',
                'source_municipality' => 'Sorsogon City',
                'source_province' => 'Sorsogon',
                'source_district' => '1st',
                'income_class' => 'Component City',
                'beneficiaries_total' => 30,
                'beneficiaries_female' => 2,
                'beneficiary_type' => 'Vulnerable Sectors',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '249000.00',
                'ppe_total' => '10500.00',
                'insurance_total' => '1500.00',
                'requested_to_dole' => '261000.00',
                'service_fee' => '600.00',
                'equity' => '65250.00',
                'source_total_project_cost' => '326250.00',
                'wage_rate' => '415.00',
                'insurance_rate' => '50.00',
                'municipality_psgc' => '056216000',
                'municipality_name' => 'City of Sorsogon',
                'barangay_psgc' => '056216014',
                'barangay_name' => 'Cabid-An',
            ],
            [
                'sheet' => 'SORSOGON',
                'row' => 58,
                'source_status' => '',
                'adl_number' => '2025-02-0160',
                'fund_sponsor' => 'RMF',
                'partner' => 'BJMP',
                'source_project_code' => 'TUPAD-RO5-SPO-IRS-25-06-03',
                'project_series' => 'R05-SFO-WP-2025-05-612',
                'receipt_month' => 'MAY',
                'receipt_datetime' => '2025-05-29 11:05:00',
                'date_received' => '2025-05-29',
                'project_title' => 'TUPAD behind bars: Reintegration project empowering  PDLS',
                'nature_of_work' => 'Painting of Edifice around Jail Station and Vegetable Gardening/Landscaping',
                'proponent' => 'DOLE-SPO',
                'implementation_mode' => 'Direct Administration',
                'number_of_days' => 20,
                'source_barangay' => 'San Isidro',
                'source_municipality' => 'Irosin',
                'source_province' => 'Sorsogon',
                'source_district' => '2nd',
                'income_class' => '1st Class',
                'beneficiaries_total' => 30,
                'beneficiaries_female' => 0,
                'beneficiary_type' => 'Vulnerable Sectors',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '249000.00',
                'ppe_total' => '10500.00',
                'insurance_total' => '1500.00',
                'requested_to_dole' => '261000.00',
                'service_fee' => '600.00',
                'equity' => '65250.00',
                'source_total_project_cost' => '326250.00',
                'wage_rate' => '415.00',
                'insurance_rate' => '50.00',
                'municipality_psgc' => '056209000',
                'municipality_name' => 'Irosin',
                'barangay_psgc' => '056209022',
                'barangay_name' => 'San Isidro',
            ],
            [
                'sheet' => 'SORSOGON',
                'row' => 65,
                'source_status' => '',
                'adl_number' => '2025-03-0268',
                'fund_sponsor' => 'RMF',
                'partner' => 'Unfunded',
                'source_project_code' => 'TUPAD-RO5-SPO-SRC-25-06-10',
                'project_series' => 'R05-SFO-WP-2025-06-0617',
                'receipt_month' => 'JUNE',
                'receipt_datetime' => '2025-06-03 11:38:00',
                'date_received' => '2025-06-03',
                'project_title' => 'Securing Rice, Securing Future',
                'nature_of_work' => 'Repacking, Loading and Unloading of Rice Sacks',
                'proponent' => 'DOLE-SPO',
                'implementation_mode' => 'Direct Administration',
                'number_of_days' => 21,
                'source_barangay' => 'Cabid-an',
                'source_municipality' => 'Sorsogon City',
                'source_province' => 'Sorsogon',
                'source_district' => '1st',
                'income_class' => 'Component City',
                'beneficiaries_total' => 20,
                'beneficiaries_female' => 20,
                'beneficiary_type' => 'Underemployed/Self Employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '174300.00',
                'ppe_total' => '7000.00',
                'insurance_total' => '1000.00',
                'requested_to_dole' => '182300.00',
                'service_fee' => '400.00',
                'equity' => '45575.00',
                'source_total_project_cost' => '227875.00',
                'wage_rate' => '415.00',
                'insurance_rate' => '50.00',
                'municipality_psgc' => '056216000',
                'municipality_name' => 'City of Sorsogon',
                'barangay_psgc' => '056216014',
                'barangay_name' => 'Cabid-An',
            ],
            [
                'sheet' => 'SORSOGON',
                'row' => 93,
                'source_status' => '',
                'adl_number' => '2025-01-0008',
                'fund_sponsor' => 'RMF',
                'partner' => 'Unfunded',
                'source_project_code' => 'TUPAD-RO5-SPO-PLR-25-08-12',
                'project_series' => 'R05-SFO-WP-2025-08-0642',
                'receipt_month' => 'AUGUST',
                'receipt_datetime' => '2025-08-19 00:00:00',
                'date_received' => '2025-08-19',
                'project_title' => 'Concrete Works of the Installation of Water System Project in Putiao, Pilar',
                'nature_of_work' => 'Concrete Works of the Installation of Water System Project in Putiao, Pilar',
                'proponent' => 'DOLE-SPO',
                'implementation_mode' => 'Direct Administration',
                'number_of_days' => 20,
                'source_barangay' => 'Putiao',
                'source_municipality' => 'Pilar',
                'source_province' => 'Sorsogon',
                'source_district' => '1st',
                'income_class' => '1st Class',
                'beneficiaries_total' => 30,
                'beneficiaries_female' => 14,
                'beneficiary_type' => 'Underemployed/Self Employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '249000.00',
                'ppe_total' => '23100.00',
                'insurance_total' => '1500.00',
                'requested_to_dole' => '273600.00',
                'service_fee' => '600.00',
                'equity' => '68400.00',
                'source_total_project_cost' => '342000.00',
                'wage_rate' => '415.00',
                'insurance_rate' => '50.00',
                'municipality_psgc' => '056213000',
                'municipality_name' => 'Pilar',
                'barangay_psgc' => '056213044',
                'barangay_name' => 'Putiao',
            ],
        ];
    }
}
