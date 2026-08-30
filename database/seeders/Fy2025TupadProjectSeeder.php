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
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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

        $this->seedBicolReferenceData();
        $this->seedDevelopmentUsers();

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

        $directCount = $rows
            ->filter(fn (array $row): bool => $this->implementationMode($row['implementation_mode']) === ImplementationMode::DIRECT_ADMINISTRATION)
            ->count();

        $acpCount = $rows
            ->filter(fn (array $row): bool => $this->implementationMode($row['implementation_mode']) === ImplementationMode::THROUGH_ACP)
            ->count();

        $this->command?->info(
            'FY2025 TUPAD spreadsheet sample seeded successfully: '.$rows->count().' projects. '.$counts.'. '.
            "Direct Administration: {$directCount}; Through ACP: {$acpCount}."
        );

        $this->command?->warn(
            'All seeded projects are intentionally forced to Ongoing Profiling. Source spreadsheet statuses, source implementation modes, and source project codes are retained in traceability remarks.'
        );
    }


    private function seedBicolReferenceData(): void
    {
        $provinceDefinitions = (array) config('tupad_mapping.provinces', []);

        if (count($provinceDefinitions) !== 6) {
            throw new RuntimeException('The fresh FY2025 seeder requires the six reviewed Bicol province definitions in config/tupad_mapping.php.');
        }

        $manifestPath = public_path('geojson/bicol/barangay-labels/manifest.json');
        $manifest = $this->readGeoJson($manifestPath);
        $unavailableByMunicipality = collect($manifest['unavailable_geometry'] ?? [])->groupBy('municipality_psgc_code');

        foreach ($provinceDefinitions as $provinceCode => $definition) {
            $province = Province::query()->updateOrCreate(
                ['code' => (string) $provinceCode],
                ['name' => (string) $definition['name'], 'is_active' => true],
            );

            $municipalityPath = public_path('geojson/bicol/municipalities/'.(string) $definition['slug'].'.geojson');
            $municipalityGeoJson = $this->readGeoJson($municipalityPath);

            foreach (($municipalityGeoJson['features'] ?? []) as $feature) {
                $properties = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
                $municipalityCode = (string) ($properties['psgc_code'] ?? '');
                $municipalityName = (string) ($properties['name'] ?? '');

                if ($municipalityCode === '' || $municipalityName === '') {
                    throw new RuntimeException("Invalid municipality feature in {$municipalityPath}.");
                }

                $district = $this->districtFor((string) $definition['name'], $municipalityName);

                if ($district === null) {
                    throw new RuntimeException("Missing legislative district mapping for {$municipalityName}, {$definition['name']}.");
                }

                $municipality = Municipality::query()->updateOrCreate(
                    ['code' => $municipalityCode],
                    [
                        'province_id' => $province->id,
                        'name' => $municipalityName,
                        'district' => $district,
                        'income_class' => null,
                        'is_city' => (bool) ($properties['is_city'] ?? false),
                        'is_active' => true,
                    ],
                );

                $barangayPath = public_path('geojson/bicol/barangay-labels/'.$municipalityCode.'.geojson');
                $barangayGeoJson = $this->readGeoJson($barangayPath);
                $barangayRows = [];

                foreach (($barangayGeoJson['features'] ?? []) as $barangayFeature) {
                    $barangayProperties = is_array($barangayFeature['properties'] ?? null) ? $barangayFeature['properties'] : [];
                    $barangayCode = (string) ($barangayProperties['psgc_code'] ?? '');
                    $barangayName = (string) ($barangayProperties['name'] ?? '');

                    if ($barangayCode !== '' && $barangayName !== '') {
                        $barangayRows[$barangayCode] = $barangayName;
                    }
                }

                foreach ($unavailableByMunicipality->get($municipalityCode, collect()) as $unavailable) {
                    $barangayCode = (string) ($unavailable['psgc_code'] ?? '');
                    $barangayName = (string) ($unavailable['name'] ?? '');
                    if ($barangayCode !== '' && $barangayName !== '') {
                        $barangayRows[$barangayCode] = $barangayName;
                    }
                }

                if ($barangayRows === []) {
                    throw new RuntimeException("No reviewed barangay reference rows found for {$municipalityName} ({$municipalityCode}).");
                }

                foreach ($barangayRows as $barangayCode => $barangayName) {
                    Barangay::query()->updateOrCreate(
                        ['code' => $barangayCode],
                        [
                            'municipality_id' => $municipality->id,
                            'name' => $barangayName,
                            'is_active' => true,
                        ],
                    );
                }
            }
        }

        $this->command?->info('Complete Bicol PSGC reference data restored from the reviewed local GeoJSON layer.');
    }

    private function seedDevelopmentUsers(): void
    {
        $provinces = Province::query()
            ->whereIn('code', [
                '050500000',
                '051600000',
                '051700000',
                '052000000',
                '054100000',
                '056200000',
            ])
            ->where('is_active', true)
            ->get()
            ->keyBy('code');

        if ($provinces->count() !== 6) {
            throw new RuntimeException(
                'All six active Bicol provinces must exist before development users can be seeded.'
            );
        }

        User::query()->updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'System Administrator',
                'email' => 'admin@tupad.local',
                'position' => 'System Administrator',
                'role' => UserRole::ADMIN,
                'is_active' => true,
                'supervisor_tc_id' => null,
                'assigned_province_id' => null,
                'password' => Hash::make('password'),
            ],
        );

        $coordinatorDefinitions = [
            ['username' => 'Orlan', 'email' => 'orlan.albay@example.com', 'province_code' => '050500000'],
            ['username' => 'Salvs', 'email' => 'salvs.albay@example.com', 'province_code' => '050500000'],
            ['username' => 'Nics', 'email' => 'nics.albay@example.com', 'province_code' => '050500000'],
            ['username' => 'Tay', 'email' => 'tay.camarinesnorte@example.com', 'province_code' => '051600000'],
            ['username' => 'Camz', 'email' => 'camz.camarinessur@example.com', 'province_code' => '051700000'],
            ['username' => 'Jho', 'email' => 'jho.camarinessur@example.com', 'province_code' => '051700000'],
            ['username' => 'Klint', 'email' => 'klint.camarinessur@example.com', 'province_code' => '051700000'],
            ['username' => 'Pau', 'email' => 'pau.catanduanes@example.com', 'province_code' => '052000000'],
            ['username' => 'Julz', 'email' => 'julz.masbate@example.com', 'province_code' => '054100000'],
            ['username' => 'Yhen', 'email' => 'yhen.sorsogon@example.com', 'province_code' => '056200000'],
        ];

        /*
         * Preserve the primary key of the old development `tc` account when
         * upgrading an already-seeded database. This keeps existing GIP
         * supervisor / draft relationships connected while replacing the
         * placeholder account with the real Albay coordinator account Orlan.
         */
        $legacyTc = User::query()
            ->where('username', 'tc')
            ->where('role', UserRole::TC->value)
            ->first();

        $existingOrlan = User::query()
            ->where('username', 'Orlan')
            ->first();

        if ($legacyTc && ! $existingOrlan) {
            $legacyTc->forceFill([
                'username' => 'Orlan',
                'name' => 'Orlan',
                'email' => 'orlan.albay@example.com',
                'position' => 'TUPAD Coordinator',
                'role' => UserRole::TC,
                'is_active' => true,
                'supervisor_tc_id' => null,
                'assigned_province_id' => $provinces['050500000']->id,
                'password' => Hash::make('password'),
            ])->save();
        }

        $coordinators = collect();

        foreach ($coordinatorDefinitions as $definition) {
            $province = $provinces->get($definition['province_code']);

            if (! $province) {
                throw new RuntimeException(
                    "Missing active Bicol province {$definition['province_code']} for coordinator {$definition['username']}."
                );
            }

            $coordinator = User::query()->updateOrCreate(
                ['username' => $definition['username']],
                [
                    'name' => $definition['username'],
                    'email' => $definition['email'],
                    'position' => 'TUPAD Coordinator',
                    'role' => UserRole::TC,
                    'is_active' => true,
                    'supervisor_tc_id' => null,
                    'assigned_province_id' => $province->id,
                    'password' => Hash::make('password'),
                ],
            );

            $coordinators->put($definition['username'], $coordinator);
        }

        /** @var User $defaultGipSupervisor */
        $defaultGipSupervisor = $coordinators->get('Orlan');

        User::query()->updateOrCreate(
            ['username' => 'gip'],
            [
                'name' => 'GIP Encoder',
                'email' => 'gip@tupad.local',
                'position' => 'GIP',
                'role' => UserRole::GIP,
                'is_active' => true,
                'supervisor_tc_id' => $defaultGipSupervisor->id,
                'assigned_province_id' => null,
                'password' => Hash::make('password'),
            ],
        );

        User::query()->updateOrCreate(
            ['username' => 'focal'],
            [
                'name' => 'TUPAD Focal',
                'email' => 'focal@tupad.local',
                'position' => 'TUPAD Focal',
                'role' => UserRole::FOCAL,
                'is_active' => true,
                'supervisor_tc_id' => null,
                'assigned_province_id' => null,
                'password' => Hash::make('password'),
            ],
        );

        $this->command?->info(
            'Development users ready: admin, focal, gip, and 10 province-scoped TUPAD Coordinator accounts.'
        );
    }

    private function readGeoJson(string $path): array
    {
        if (! File::exists($path)) {
            throw new RuntimeException("Required reviewed geographic reference file is missing: {$path}");
        }

        $decoded = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new RuntimeException("Invalid geographic reference file: {$path}");
        }
        return $decoded;
    }

    private function districtFor(string $province, string $municipality): ?string
    {
        return $this->districtMap()[$this->normalizePlace($province)][$this->normalizePlace($municipality)] ?? null;
    }

    private function normalizePlace(string $name): string
    {
        return Str::of($name)
            ->lower()
            ->replace('city of ', '')
            ->replace(' city', '')
            ->replace('ñ', 'n')
            ->replace('á', 'a')
            ->replace('é', 'e')
            ->replace('í', 'i')
            ->replace('ó', 'o')
            ->replace('ú', 'u')
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }

    private function mapDistricts(array $districts): array
    {
        $result = [];
        foreach ($districts as $district => $municipalities) {
            foreach ($municipalities as $municipality) {
                $result[$this->normalizePlace($municipality)] = $district;
            }
        }
        return $result;
    }

    private function districtMap(): array
    {
        return [
            'albay' => $this->mapDistricts([
                '1st District' => ['Bacacay','Malinao','Malilipot','Santo Domingo','Tabaco City','Tiwi'],
                '2nd District' => ['Camalig','Daraga','Legazpi City','Manito','Rapu-Rapu'],
                '3rd District' => ['Guinobatan','Jovellar','Libon','Ligao City','Oas','Pio Duran','Polangui'],
            ]),
            'camarines norte' => $this->mapDistricts([
                '1st District' => ['Capalonga','Jose Panganiban','Labo','Paracale','Santa Elena'],
                '2nd District' => ['Basud','Daet','Mercedes','San Lorenzo Ruiz','San Vicente','Talisay','Vinzons'],
            ]),
            'camarines sur' => $this->mapDistricts([
                '1st District' => ['Del Gallego','Ragay','Lupi','Sipocot','Cabusao'],
                '2nd District' => ['Libmanan','Minalabac','Pamplona','Pasacao','San Fernando','Gainza','Milaor'],
                '3rd District' => ['Naga City','Pili','Ocampo','Camaligan','Canaman','Magarao','Bombon','Calabanga'],
                '4th District' => ['Caramoan','Garchitorena','Goa','Lagonoy','Presentacion','Sagñay','San Jose','Tigaon','Tinambac','Siruma'],
                '5th District' => ['Iriga City','Baao','Balatan','Bato','Buhi','Bula','Nabua'],
            ]),
            'catanduanes' => $this->mapDistricts([
                'Lone District' => ['Bagamanoc','Baras','Bato','Caramoran','Gigmoto','Pandan','Panganiban','San Andres','San Miguel','Viga','Virac'],
            ]),
            'masbate' => $this->mapDistricts([
                '1st District' => ['San Pascual','Claveria','Monreal','San Jacinto','San Fernando','Batuan'],
                '2nd District' => ['Masbate City','Mobo','Milagros','Aroroy','Baleno','Balud','Mandaon'],
                '3rd District' => ['Uson','Dimasalang','Palanas','Cataingan','Pio V. Corpuz','Esperanza','Placer','Cawayan'],
            ]),
            'sorsogon' => $this->mapDistricts([
                '1st District' => ['Sorsogon City','Pilar','Donsol','Castilla','Casiguran','Magallanes'],
                '2nd District' => ['Barcelona','Prieto Diaz','Gubat','Juban','Bulusan','Irosin','Santa Magdalena','Matnog','Bulan'],
            ]),
        ];
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
        if ($rows->count() !== 60) {
            throw new RuntimeException(
                'Expected exactly 60 reviewed FY2025 seed rows: the original 30-row sample plus 30 additional Through ACP projects.'
            );
        }

        $acpExtension = collect($this->throughAcpRows());

        if ($acpExtension->count() !== 30) {
            throw new RuntimeException('The Through ACP extension must contain exactly 30 projects.');
        }

        foreach (self::REQUIRED_PROVINCES as $province) {
            $provinceTotal = $rows->where('source_province', $province)->count();
            $provinceAcpExtension = $acpExtension->where('source_province', $province)->count();

            if ($provinceTotal !== 10 || $provinceAcpExtension !== 5) {
                throw new RuntimeException(
                    "FY2025 seed data for {$province} must contain ten total projects, including exactly five projects from the new Through ACP extension; found total={$provinceTotal}, ACP extension={$provinceAcpExtension}."
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
                fn (array $row): int => $this->implementationMode($row['implementation_mode']) === ImplementationMode::DIRECT_ADMINISTRATION
                    ? $this->moneyToCents($row['service_fee'])
                    : 0
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
                    'tranche' => '60-row reviewed FY2025 sample (30 original + 30 Through ACP extension)',
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
            'admin_cost_amount' => $this->implementationMode($row['implementation_mode']) === ImplementationMode::DIRECT_ADMINISTRATION
                ? $this->money($row['service_fee'])
                : '0.00',
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
        $remarks = [
            self::SEED_MARKER.' Development/test sample imported from '.self::SOURCE_WORKBOOK.'.',
            'Source sheet/row: '.$row['sheet'].' / '.$row['row'],
            'Source ADL no.: '.$row['adl_number'],
            'Source project code: '.($row['source_project_code'] ?: '—'),
            'Source status: '.($row['source_status'] ?: 'Blank'),
            'Source implementation mode: '.($row['implementation_mode'] ?: 'Blank'),
            'Seeder status override: '.ProjectStatus::ONGOING_PROFILING->label(),
            'Source location text: '.$row['source_barangay'].', '.$row['source_municipality'].', '.$row['source_province'].' ('.$row['source_district'].')',
            'Resolved representative PSGC location: municipality '.$row['municipality_psgc'].'; barangay '.$row['barangay_psgc'].'.',
            'Source beneficiary type: '.($row['beneficiary_type'] ?: '—'),
            'Source displacement type: '.($row['displacement_type'] ?: '—'),
            'Source equity (AV-related project counterpart): PHP '.$this->money($row['equity']).'.',
            'Source total project cost including equity: PHP '.$this->money($row['source_total_project_cost']).'.',
        ];

        if (filled($row['source_note'] ?? null)) {
            $remarks[] = 'Seeder source note: '.trim((string) $row['source_note']);
        }

        return implode(PHP_EOL, $remarks);
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

    private function sourceRows(): array
    {
        return array_merge(
            $this->originalSourceRows(),
            $this->throughAcpRows(),
        );
    }

    /**
     * Original reviewed 30-row FY2025 selection. Exactly five source rows are included for each Bicol province.
     * Source implementation modes are preserved; the original selection includes four Masbate Through ACP rows.
     *
     * @return array<int, array<string, mixed>>
     */
    private function originalSourceRows(): array
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

    /**
     * Reviewed Through ACP extension from FY2025 TUPAD DATABASED.xlsx.
     * Exactly five seeded Through ACP projects are included for each Bicol province.
     *
     * Camarines Sur has only four identifiable ACP source entries in the workbook.
     * Two source rows are split into derived test segments so the seeded total reaches
     * five projects while preserving each source row's aggregate beneficiaries and funding.
     *
     * @return array<int, array<string, mixed>>
     */
    private function throughAcpRows(): array
    {
        return [
            [
                'sheet' => 'ALBAY',
                'row' => 65,
                'source_status' => 'Completed/ Liquidated',
                'adl_number' => '2025-02-0185',
                'fund_sponsor' => 'Cong. Fernando Cabredo',
                'partner' => 'Cong. Fernando Cabredo',
                'source_project_code' => 'TUPAD-RO5-APO-LIGC-25-06-19',
                'project_series' => 'RO5-APFO-WP-25-06-1580',
                'receipt_month' => 'JUNE',
                'receipt_datetime' => '2025-06-09 00:00:00',
                'date_received' => '2025-06-09',
                'project_title' => 'Our Community, Our Responsibility: A Tree Planting and Clean-Up Initiative ',
                'nature_of_work' => 'Dengue Prevention Activities, Road Beautification, Tree Planting, Community Gardening and Cleaning of Public Facilities',
                'proponent' => 'LGU-Ligao City',
                'implementation_mode' => 'Through ACP',
                'number_of_days' => 10,
                'source_barangay' => 'Abella, Allang, Amtic, Bacong, Bagumbayan, Balanac, Baligang, Barayong, Basag, Batang, Bay, Binanowan, Binatagan, Bobonsuran, Bonga, Busac, Busay, Cabarian, Calzada, Catburawan, Cavasi, Culliat, Dunao, Francia, Guilid, Herrera, Layon, Macalidong, Mahaba, Malama, Maonon, Nabonton, Oma-Oma, Palapas, Pandan, Paulba, Paulog, Pinamaniquian, Pinit, Ranao-Ranao, San Vicente, Sta. Cruz, Tagpo, Tambo, Tandarura, Tastas, Tinago, Tinampo, Tiongson, Tomolin, Tuburan, Tula-Tula Pequeno, Tula-Tula Grande, Tupas',
                'source_municipality' => 'Ligao City',
                'source_province' => 'Albay',
                'source_district' => '3rd',
                'income_class' => 'Component City',
                'beneficiaries_total' => 3513,
                'beneficiaries_female' => 2403,
                'beneficiary_type' => 'Underemployed/Self-employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '14578950.00',
                'ppe_total' => '1229550.00',
                'insurance_total' => '175650.00',
                'requested_to_dole' => '15984150.00',
                'service_fee' => '70260.00',
                'equity' => '3996037.50',
                'source_total_project_cost' => '19980187.50',
                'wage_rate' => '415.00',
                'insurance_rate' => '50.00',
                'municipality_psgc' => '050508000',
                'municipality_name' => 'City of Ligao',
                'barangay_psgc' => '050508001',
                'barangay_name' => 'Abella',
                'source_note' => '',
            ],
            [
                'sheet' => 'ALBAY',
                'row' => 125,
                'source_status' => 'For Payment',
                'adl_number' => '2025-01-0008-2',
                'fund_sponsor' => 'AKB PL-2',
                'partner' => 'AKB PL-2',
                'source_project_code' => 'TUPAD-RO5-APO-TABC-25-10-04',
                'project_series' => 'RO5-APFO-WP-2025-09-1652',
                'receipt_month' => 'OCTOBER',
                'receipt_datetime' => '2025-10-07 08:50:00',
                'date_received' => '2025-10-07',
                'project_title' => 'Rise of Adventure: An Initiative To Develop Popular Destinations and Promote Sustainable Tourism in Tabaco City',
                'nature_of_work' => 'Restoration, Beautification, Maintenance of Tourism Sites and Facilities',
                'proponent' => 'LGU Tabaco City',
                'implementation_mode' => 'Through ACP',
                'number_of_days' => 30,
                'source_barangay' => 'Buang',
                'source_municipality' => 'Tabaco City',
                'source_province' => 'Albay',
                'source_district' => '1st',
                'income_class' => 'Component City',
                'beneficiaries_total' => 100,
                'beneficiaries_female' => 55,
                'beneficiary_type' => 'Underemployed/Self-employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '1245000.00',
                'ppe_total' => '0.00',
                'insurance_total' => '5000.00',
                'requested_to_dole' => '1250000.00',
                'service_fee' => '2000.00',
                'equity' => '312500.00',
                'source_total_project_cost' => '1562500.00',
                'wage_rate' => '415.00',
                'insurance_rate' => '50.00',
                'municipality_psgc' => '050517000',
                'municipality_name' => 'City of Tabaco',
                'barangay_psgc' => '050517012',
                'barangay_name' => 'Buang',
                'source_note' => '',
            ],
            [
                'sheet' => 'ALBAY',
                'row' => 154,
                'source_status' => 'Completed/ Liquidated',
                'adl_number' => '2025-10-1110',
                'fund_sponsor' => 'AKB PL',
                'partner' => 'AKB PL',
                'source_project_code' => 'TUPAD-RO5-APO-LEGC-25-11-16',
                'project_series' => 'RO5-APFO-WP-2025-081638',
                'receipt_month' => 'NOVEMBER',
                'receipt_datetime' => '2025-11-19 13:38:00',
                'date_received' => '2025-11-19',
                'project_title' => 'ROOTED IN UNITY: A COLLECTIVE EFFORT FOR A SUSTAINABLE TOMORROW',
                'nature_of_work' => 'Post- disaster Clearing Opertaion, Urban/Community Gardening Using Recyc;ed Containers, Maintenance of Public Facilities and Dengue Prevention Activities',
                'proponent' => 'LGU Legazpi ',
                'implementation_mode' => 'Through ACP',
                'number_of_days' => 10,
                'source_barangay' => 'EMs Barrio East, Sagpon, Sagmin, Bagumbayan, Pinaric, Tula-tuka, Ilawod West, Ilawod, Kawit East Washington, Cabangan West, Binanuahan West, Imperial Court, Rizal St., Lapu-lapu, Victory Souh, Victory North, Sabang, Centro Baybay, Pigcale, Penaranda, Bitano, Gogon, Bogtong, Tamaoyan, Pawa, Arimbay, Bagong Abre, Bigaa, Buyuan, Matanag, Bonga, Mabinit, Estanza, Taysan, Buraguis, Puro, Lamba, Homapon, Cagbacong, ',
                'source_municipality' => 'Legazpi City',
                'source_province' => 'Albay',
                'source_district' => '2nd',
                'income_class' => 'Component City',
                'beneficiaries_total' => 2406,
                'beneficiaries_female' => 1489,
                'beneficiary_type' => 'Underemployed/Self-employed',
                'displacement_type' => 'Economic Crisis and Typhoon Uwan',
                'wages_total' => '9984900.00',
                'ppe_total' => '842100.00',
                'insurance_total' => '120300.00',
                'requested_to_dole' => '10947300.00',
                'service_fee' => '48120.00',
                'equity' => '2736825.00',
                'source_total_project_cost' => '13684125.00',
                'wage_rate' => '415.00',
                'insurance_rate' => '50.00',
                'municipality_psgc' => '050506000',
                'municipality_name' => 'City of Legazpi',
                'barangay_psgc' => '050506027',
                'barangay_name' => 'Bgy. 3 - Em\'s Barrio East (Pob.)',
                'source_note' => '',
            ],
            [
                'sheet' => 'RO',
                'row' => 9,
                'source_status' => 'Implemented (convergence site row)',
                'adl_number' => '2025-02-0160',
                'fund_sponsor' => 'RMF',
                'partner' => 'DA',
                'source_project_code' => '',
                'project_series' => 'RO5-APFO-WP-2025-03-1555',
                'receipt_month' => 'MARCH',
                'receipt_datetime' => '2025-03-24 00:00:00',
                'date_received' => '2025-03-24',
                'project_title' => 'DOLE-DA TUPAD Convergence Project — Albay Breeding Station',
                'nature_of_work' => 'Agricultural Project (Maintenance of Stations, Hands-on Trainings and Production of Agricultural Products)',
                'proponent' => 'Department of Agriculture Regional Field Office V',
                'implementation_mode' => 'Through ACP',
                'number_of_days' => 90,
                'source_barangay' => 'ALBAY -Albay Research and Development Center- Albay Breeding Station\n\nCabangan',
                'source_municipality' => 'Camalig',
                'source_province' => 'Albay',
                'source_district' => '2nd',
                'income_class' => '',
                'beneficiaries_total' => 15,
                'beneficiaries_female' => 13,
                'beneficiary_type' => '',
                'displacement_type' => '',
                'wages_total' => '533250.00',
                'ppe_total' => '16950.00',
                'insurance_total' => '750.00',
                'requested_to_dole' => '550950.00',
                'service_fee' => '300.00',
                'equity' => '137737.50',
                'source_total_project_cost' => '688687.50',
                'wage_rate' => '395.00',
                'insurance_rate' => '50.00',
                'municipality_psgc' => '050502000',
                'municipality_name' => 'Camalig',
                'barangay_psgc' => '050502010',
                'barangay_name' => 'Cabagñan',
                'source_note' => 'RO sheet site row; project metadata/date inherited from the workbook convergence parent row.',
            ],
            [
                'sheet' => 'RO',
                'row' => 14,
                'source_status' => 'Implemented',
                'adl_number' => '2025-02-0160',
                'fund_sponsor' => 'RMF',
                'partner' => 'DENR',
                'source_project_code' => 'TUPAD-RO5-PROV(2)-LGU(3)-25-04-01',
                'project_series' => 'RO5-APFO-WP-2025-04-1557',
                'receipt_month' => 'APRIL',
                'receipt_datetime' => '2025-04-11 00:00:00',
                'date_received' => '2025-04-11',
                'project_title' => '"Seeds of Progress, Fields of Prosperity" A TUPAD Project in Partnership with DENR',
                'nature_of_work' => 'Agro-Forestry Community Projects - Seedling Production',
                'proponent' => 'Department of Environment and Natural Resources ROV',
                'implementation_mode' => 'Through ACP',
                'number_of_days' => 90,
                'source_barangay' => 'Banao\nMMFN',
                'source_municipality' => 'Guinobatan',
                'source_province' => 'Albay',
                'source_district' => '3rd',
                'income_class' => '',
                'beneficiaries_total' => 80,
                'beneficiaries_female' => 41,
                'beneficiary_type' => 'Underemployed and Self-employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '2988000.00',
                'ppe_total' => '50400.00',
                'insurance_total' => '4000.00',
                'requested_to_dole' => '3042400.00',
                'service_fee' => '1600.00',
                'equity' => '760600.00',
                'source_total_project_cost' => '3803000.00',
                'wage_rate' => '415.00',
                'insurance_rate' => '50.00',
                'municipality_psgc' => '050504000',
                'municipality_name' => 'Guinobatan',
                'barangay_psgc' => '050504003',
                'barangay_name' => 'Banao',
                'source_note' => '',
            ],
            [
                'sheet' => 'CAMNORTE',
                'row' => 55,
                'source_status' => 'Completed/ Liquidated',
                'adl_number' => '2025-02-0160',
                'fund_sponsor' => 'RMF',
                'partner' => 'TESDA',
                'source_project_code' => 'TUPAD-RO5-CNPO-SLR-MTCG-25-05-01',
                'project_series' => 'RO5-CNFO-WP-2025-02-0514',
                'receipt_month' => 'MAY',
                'receipt_datetime' => '2025-05-08 00:00:00',
                'date_received' => '2025-05-08',
                'project_title' => 'Joint Department of Labor and Employment (DOLE) Tulong Panghanapbuhay sa ating Disadvantaged Workers (TUPAD) - Technical Education and Skills Development Authority (TESDA) Training cum Production Program (Construction Painting NC II)',
                'nature_of_work' => 'Repair, Maintenance and/or Improvement of Municipal Buildings/Compound',
                'proponent' => 'LGU San Lorenzo Ruiz',
                'implementation_mode' => 'Through ACP',
                'number_of_days' => 37,
                'source_barangay' => 'Matacong',
                'source_municipality' => 'San Lorenzo Ruiz',
                'source_province' => 'Camarines Norte',
                'source_district' => '2nd',
                'income_class' => '5th Class',
                'beneficiaries_total' => 25,
                'beneficiaries_female' => 1,
                'beneficiary_type' => 'Self Employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '383875.00',
                'ppe_total' => '28250.00',
                'insurance_total' => '1250.00',
                'requested_to_dole' => '413375.00',
                'service_fee' => '500.00',
                'equity' => '103343.75',
                'source_total_project_cost' => '516718.75',
                'wage_rate' => '415.00',
                'insurance_rate' => '50.00',
                'municipality_psgc' => '051604000',
                'municipality_name' => 'San Lorenzo Ruiz',
                'barangay_psgc' => '051604008',
                'barangay_name' => 'Matacong (Pob.)',
                'source_note' => '',
            ],
            [
                'sheet' => 'CAMNORTE',
                'row' => 68,
                'source_status' => 'Completed/ Liquidated',
                'adl_number' => '2025-03-0268',
                'fund_sponsor' => 'RMF',
                'partner' => 'Unfunded',
                'source_project_code' => 'TUPAD-RO5-CNPO-JPN-LUKS-25-06-01',
                'project_series' => 'RO5-CNFO-WP-2025-06-0552',
                'receipt_month' => 'JUNE',
                'receipt_datetime' => '2025-06-20 00:00:00',
                'date_received' => '2025-06-20',
                'project_title' => 'Tree Planting for Sustainable Mine Rehabilitation',
                'nature_of_work' => 'Clearing and Tree Planting within Mining Area',
                'proponent' => 'BLGU - Luklukan Sur, Jose Panganiban',
                'implementation_mode' => 'Through ACP',
                'number_of_days' => 10,
                'source_barangay' => 'Luklukan Sur',
                'source_municipality' => 'Jose Panganiban',
                'source_province' => 'Camarines Norte',
                'source_district' => '1st',
                'income_class' => '1st Class',
                'beneficiaries_total' => 100,
                'beneficiaries_female' => 95,
                'beneficiary_type' => 'Self Employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '415000.00',
                'ppe_total' => '35000.00',
                'insurance_total' => '5000.00',
                'requested_to_dole' => '455000.00',
                'service_fee' => '2000.00',
                'equity' => '113750.00',
                'source_total_project_cost' => '568750.00',
                'wage_rate' => '415.00',
                'insurance_rate' => '50.00',
                'municipality_psgc' => '051605000',
                'municipality_name' => 'Jose Panganiban',
                'barangay_psgc' => '051605007',
                'barangay_name' => 'Luklukan Sur',
                'source_note' => '',
            ],
            [
                'sheet' => 'CAMNORTE',
                'row' => 69,
                'source_status' => 'With Notice to Proceed',
                'adl_number' => '2025-02-0160',
                'fund_sponsor' => 'RMF',
                'partner' => 'TESDA',
                'source_project_code' => 'TUPAD-RO5-CNPO-PAR-PBNR-25-06-02',
                'project_series' => 'RO5-CNFO-WP-2025-06-0554',
                'receipt_month' => 'JUNE',
                'receipt_datetime' => '2025-06-20 00:00:00',
                'date_received' => '2025-06-20',
                'project_title' => 'Joint Department of Labor and Employment (DOLE) Tulong Panghanapbuhay para sa ating Disadvantaged Workers (TUPAD) - Technical Education and Skills Development Authority (TESDA) Training Cum Production Program',
                'nature_of_work' => 'Repair, Maintenance and/or Improvement of Municipal Buildings/Compound',
                'proponent' => 'LGU Paracale',
                'implementation_mode' => 'Through ACP',
                'number_of_days' => 37,
                'source_barangay' => 'Poblacion Norte',
                'source_municipality' => 'Paracale',
                'source_province' => 'Camarines Norte',
                'source_district' => '1st',
                'income_class' => '1st Class',
                'beneficiaries_total' => 25,
                'beneficiaries_female' => 2,
                'beneficiary_type' => 'Self Employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '383875.00',
                'ppe_total' => '19500.00',
                'insurance_total' => '1250.00',
                'requested_to_dole' => '404625.00',
                'service_fee' => '500.00',
                'equity' => '101156.25',
                'source_total_project_cost' => '505781.25',
                'wage_rate' => '415.00',
                'insurance_rate' => '50.00',
                'municipality_psgc' => '051608000',
                'municipality_name' => 'Paracale',
                'barangay_psgc' => '051608022',
                'barangay_name' => 'Poblacion Norte',
                'source_note' => '',
            ],
            [
                'sheet' => 'CAMNORTE',
                'row' => 70,
                'source_status' => 'Completed/ Liquidated',
                'adl_number' => '2025-03-0268',
                'fund_sponsor' => 'RMF',
                'partner' => 'Unfunded',
                'source_project_code' => 'TUPAD-RO5-CNPO-PAR-CASA-25-06-03',
                'project_series' => 'RO5-CNFO-WP-2025-06-0555',
                'receipt_month' => 'JUNE',
                'receipt_datetime' => '2025-06-25 00:00:00',
                'date_received' => '2025-06-25',
                'project_title' => 'Ecological Restoration through Tree Planting at the Mine Site',
                'nature_of_work' => 'Clearing and Tree Planting within Mining Area',
                'proponent' => 'BLGU - Casalugan, Paracale',
                'implementation_mode' => 'Through ACP',
                'number_of_days' => 10,
                'source_barangay' => 'Casalugan',
                'source_municipality' => 'Paracale',
                'source_province' => 'Camarines Norte',
                'source_district' => '1st',
                'income_class' => '1st Class',
                'beneficiaries_total' => 98,
                'beneficiaries_female' => 58,
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
                'municipality_psgc' => '051608000',
                'municipality_name' => 'Paracale',
                'barangay_psgc' => '051608007',
                'barangay_name' => 'Casalugan',
                'source_note' => '',
            ],
            [
                'sheet' => 'RO',
                'row' => 13,
                'source_status' => 'Implemented (convergence site row)',
                'adl_number' => '2025-02-0160',
                'fund_sponsor' => 'RMF',
                'partner' => 'DA',
                'source_project_code' => '',
                'project_series' => 'RO5-CNFO-WP-2025-03-0546',
                'receipt_month' => 'MARCH',
                'receipt_datetime' => '2025-03-24 00:00:00',
                'date_received' => '2025-03-24',
                'project_title' => 'DOLE-DA TUPAD Convergence Project — Camarines Norte Lowland Rainfed Research Station',
                'nature_of_work' => 'Agricultural Project (Maintenance of Stations, Hands-on Trainings and Production of Agricultural Products)',
                'proponent' => 'Department of Agriculture Regional Field Office V',
                'implementation_mode' => 'Through ACP',
                'number_of_days' => 90,
                'source_barangay' => 'CAM. NORTE- Camarines Norte Lowland Rainfed Research Station\n\nCalagasgasan',
                'source_municipality' => 'Daet',
                'source_province' => 'Camarines Norte',
                'source_district' => '2nd',
                'income_class' => '',
                'beneficiaries_total' => 15,
                'beneficiaries_female' => 9,
                'beneficiary_type' => '',
                'displacement_type' => '',
                'wages_total' => '533250.00',
                'ppe_total' => '16950.00',
                'insurance_total' => '750.00',
                'requested_to_dole' => '550950.00',
                'service_fee' => '300.00',
                'equity' => '137737.50',
                'source_total_project_cost' => '688687.50',
                'wage_rate' => '395.00',
                'insurance_rate' => '50.00',
                'municipality_psgc' => '051603000',
                'municipality_name' => 'Daet',
                'barangay_psgc' => '051603006',
                'barangay_name' => 'Calasgasan',
                'source_note' => 'RO sheet site row; project metadata/date inherited from the workbook convergence parent row.',
            ],
            [
                'sheet' => 'CAMSUR',
                'row' => 58,
                'source_status' => 'Completed/ Liquidated',
                'adl_number' => '2025-01-0008',
                'fund_sponsor' => 'RMF',
                'partner' => 'Unfunded',
                'source_project_code' => 'TUPAD-RO5-CSPO-PRST-25-04-07',
                'project_series' => 'RO5-CSFO-WP-2025-04-0868',
                'receipt_month' => 'APRIL',
                'receipt_datetime' => '2025-04-23 00:00:00',
                'date_received' => '2025-04-23',
                'project_title' => 'Kalinisan at Pangkabuhayan Huwag Kalimutan Para sa Masaganang Kinabukasan — Derived Test Segment A',
                'nature_of_work' => 'Cleaning, Crop Planting, Seedlings/seed Preparation, Cultivation of Barangay Gulayan',
                'proponent' => 'LGU Presentacion',
                'implementation_mode' => 'Through ACP',
                'number_of_days' => 10,
                'source_barangay' => 'Ayugao-63, Bagong Sirang-55, Baligiuan-66, Bantugan-124, Buenavista-122, Bulalacao-66, Lagha-67, Tanawan-39, Lidong-72, Liwacsa-77, Maangas-176, Pagsangaan-51, Patricinio-72, Pili-129, Sta Maria-366',
                'source_municipality' => 'Presentacion',
                'source_province' => 'Camarines Sur',
                'source_district' => '4th',
                'income_class' => '3rd Class',
                'beneficiaries_total' => 862,
                'beneficiaries_female' => 437,
                'beneficiary_type' => 'Underemployed and Self employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '3577300.00',
                'ppe_total' => '301700.00',
                'insurance_total' => '48272.00',
                'requested_to_dole' => '3927272.00',
                'service_fee' => '17240.00',
                'equity' => '981818.00',
                'source_total_project_cost' => '4909090.00',
                'wage_rate' => '415.00',
                'insurance_rate' => '56.00',
                'municipality_psgc' => '051729000',
                'municipality_name' => 'Presentacion',
                'barangay_psgc' => '051729001',
                'barangay_name' => 'Ayugao',
                'source_note' => 'Derived test split A of the workbook\'s single ACP row; the two seeded segments preserve the source row\'s aggregate beneficiary and funding totals.',
            ],
            [
                'sheet' => 'CAMSUR',
                'row' => 58,
                'source_status' => 'Completed/ Liquidated',
                'adl_number' => '2025-01-0008',
                'fund_sponsor' => 'RMF',
                'partner' => 'Unfunded',
                'source_project_code' => 'TUPAD-RO5-CSPO-PRST-25-04-07',
                'project_series' => 'RO5-CSFO-WP-2025-04-0868',
                'receipt_month' => 'APRIL',
                'receipt_datetime' => '2025-04-23 00:00:00',
                'date_received' => '2025-04-23',
                'project_title' => 'Kalinisan at Pangkabuhayan Huwag Kalimutan Para sa Masaganang Kinabukasan — Derived Test Segment B',
                'nature_of_work' => 'Cleaning, Crop Planting, Seedlings/seed Preparation, Cultivation of Barangay Gulayan',
                'proponent' => 'LGU Presentacion',
                'implementation_mode' => 'Through ACP',
                'number_of_days' => 10,
                'source_barangay' => 'Ayugao-63, Bagong Sirang-55, Baligiuan-66, Bantugan-124, Buenavista-122, Bulalacao-66, Lagha-67, Tanawan-39, Lidong-72, Liwacsa-77, Maangas-176, Pagsangaan-51, Patricinio-72, Pili-129, Sta Maria-366',
                'source_municipality' => 'Presentacion',
                'source_province' => 'Camarines Sur',
                'source_district' => '4th',
                'income_class' => '3rd Class',
                'beneficiaries_total' => 861,
                'beneficiaries_female' => 437,
                'beneficiary_type' => 'Underemployed and Self employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '3573150.00',
                'ppe_total' => '301350.00',
                'insurance_total' => '48216.00',
                'requested_to_dole' => '3922716.00',
                'service_fee' => '17220.00',
                'equity' => '980679.00',
                'source_total_project_cost' => '4903395.00',
                'wage_rate' => '415.00',
                'insurance_rate' => '56.00',
                'municipality_psgc' => '051729000',
                'municipality_name' => 'Presentacion',
                'barangay_psgc' => '051729002',
                'barangay_name' => 'Bagong Sirang',
                'source_note' => 'Derived test split B of the workbook\'s single ACP row; the two seeded segments preserve the source row\'s aggregate beneficiary and funding totals.',
            ],
            [
                'sheet' => 'CAMSUR',
                'row' => 113,
                'source_status' => 'With Notice to Proceed',
                'adl_number' => '2025-03-0268',
                'fund_sponsor' => 'RMF',
                'partner' => 'Unfunded',
                'source_project_code' => 'TUPAD-RO5-CSPO-LBMA-25-09-12',
                'project_series' => 'RO5-CSFO-WP-2025-08-0924',
                'receipt_month' => 'SEPTEMBER',
                'receipt_datetime' => '2025-09-17 00:00:00',
                'date_received' => '2025-09-17',
                'project_title' => 'KAPIT BISIG SA PAG LILINIS: DECLOGGING AND DESILTING DRIVE — Derived Test Segment A',
                'nature_of_work' => 'De Clogging and Desilting of Canals and Creeks and Search and Destroy Deque Prevention',
                'proponent' => 'DOLE-CSPO\nLibmanan',
                'implementation_mode' => 'Through ACP',
                'number_of_days' => 10,
                'source_barangay' => 'Bagumbayan-49\nBigajo Norte-70\nBigajo Sur-45\nConcepcion-12\nHandong-57\nInalahan-14\nLibod I-19\nLibod II-21\nPoblacion-35\nPotot-12\nPuro-Batia-54\nSan Juan-53\nStation Church Site-59\nTaban-Fundado-103',
                'source_municipality' => 'Libmanan',
                'source_province' => 'Camarines Sur',
                'source_district' => '2nd',
                'income_class' => '1st Class',
                'beneficiaries_total' => 302,
                'beneficiaries_female' => 174,
                'beneficiary_type' => 'Self employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '1253300.00',
                'ppe_total' => '256700.00',
                'insurance_total' => '16912.00',
                'requested_to_dole' => '1526912.00',
                'service_fee' => '6040.00',
                'equity' => '381728.00',
                'source_total_project_cost' => '1908640.00',
                'wage_rate' => '415.00',
                'insurance_rate' => '56.00',
                'municipality_psgc' => '051718000',
                'municipality_name' => 'Libmanan',
                'barangay_psgc' => '051718006',
                'barangay_name' => 'Bagumbayan',
                'source_note' => 'Derived test split A of the workbook\'s single ACP row; the two seeded segments preserve the source row\'s aggregate beneficiary and funding totals.',
            ],
            [
                'sheet' => 'CAMSUR',
                'row' => 113,
                'source_status' => 'With Notice to Proceed',
                'adl_number' => '2025-03-0268',
                'fund_sponsor' => 'RMF',
                'partner' => 'Unfunded',
                'source_project_code' => 'TUPAD-RO5-CSPO-LBMA-25-09-12',
                'project_series' => 'RO5-CSFO-WP-2025-08-0924',
                'receipt_month' => 'SEPTEMBER',
                'receipt_datetime' => '2025-09-17 00:00:00',
                'date_received' => '2025-09-17',
                'project_title' => 'KAPIT BISIG SA PAG LILINIS: DECLOGGING AND DESILTING DRIVE — Derived Test Segment B',
                'nature_of_work' => 'De Clogging and Desilting of Canals and Creeks and Search and Destroy Deque Prevention',
                'proponent' => 'DOLE-CSPO\nLibmanan',
                'implementation_mode' => 'Through ACP',
                'number_of_days' => 10,
                'source_barangay' => 'Bagumbayan-49\nBigajo Norte-70\nBigajo Sur-45\nConcepcion-12\nHandong-57\nInalahan-14\nLibod I-19\nLibod II-21\nPoblacion-35\nPotot-12\nPuro-Batia-54\nSan Juan-53\nStation Church Site-59\nTaban-Fundado-103',
                'source_municipality' => 'Libmanan',
                'source_province' => 'Camarines Sur',
                'source_district' => '2nd',
                'income_class' => '1st Class',
                'beneficiaries_total' => 301,
                'beneficiaries_female' => 174,
                'beneficiary_type' => 'Self employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '1249150.00',
                'ppe_total' => '255850.00',
                'insurance_total' => '16856.00',
                'requested_to_dole' => '1521856.00',
                'service_fee' => '6020.00',
                'equity' => '380464.00',
                'source_total_project_cost' => '1902320.00',
                'wage_rate' => '415.00',
                'insurance_rate' => '56.00',
                'municipality_psgc' => '051718000',
                'municipality_name' => 'Libmanan',
                'barangay_psgc' => '051718023',
                'barangay_name' => 'Concepcion',
                'source_note' => 'Derived test split B of the workbook\'s single ACP row; the two seeded segments preserve the source row\'s aggregate beneficiary and funding totals.',
            ],
            [
                'sheet' => 'RO',
                'row' => 5,
                'source_status' => 'Implemented',
                'adl_number' => '2025-02-0160',
                'fund_sponsor' => 'RMF',
                'partner' => 'DA',
                'source_project_code' => 'TUPAD-RO5-PROV(6)-LGU(15)-25-03-01',
                'project_series' => 'RO5-CSFO-WP-2025-03-0858',
                'receipt_month' => 'MARCH',
                'receipt_datetime' => '2025-03-24 00:00:00',
                'date_received' => '2025-03-24',
                'project_title' => 'DOLE-DA TUPAD Convergence Project',
                'nature_of_work' => 'Agricultural Project ( Maintenance of Stations, Hands-on Trainings and Production of Agricultural Products)',
                'proponent' => 'Department of Agriculture Regional Field Office V',
                'implementation_mode' => 'Through ACP',
                'number_of_days' => 90,
                'source_barangay' => 'CAM. SUR - DA RESEARCH DIVISION\n\nSan Agustin',
                'source_municipality' => 'Pili',
                'source_province' => 'Camarines Sur',
                'source_district' => '3rd',
                'income_class' => '',
                'beneficiaries_total' => 30,
                'beneficiaries_female' => 26,
                'beneficiary_type' => 'Underemployed and Self-employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '1066500.00',
                'ppe_total' => '33900.00',
                'insurance_total' => '1500.00',
                'requested_to_dole' => '1101900.00',
                'service_fee' => '600.00',
                'equity' => '275475.00',
                'source_total_project_cost' => '1377375.00',
                'wage_rate' => '395.00',
                'insurance_rate' => '50.00',
                'municipality_psgc' => '051728000',
                'municipality_name' => 'Pili',
                'barangay_psgc' => '051728017',
                'barangay_name' => 'San Agustin',
                'source_note' => '',
            ],
            [
                'sheet' => 'CATANDUANES',
                'row' => 149,
                'source_status' => 'For procurement of PPEs',
                'adl_number' => '2025-10-1110',
                'fund_sponsor' => 'RMF',
                'partner' => 'Unfunded',
                'source_project_code' => 'TUPAD-RO5-CFO-PGCAT-25-11-07',
                'project_series' => 'RO5-CFO-WP-2025-08-0487',
                'receipt_month' => 'NOVEMBER',
                'receipt_datetime' => '2025-11-11 13:14:59',
                'date_received' => '2025-11-11',
                'project_title' => 'Kalikasan (Kakahuyan Para Sa Kaligtasan At Kabuhayan) in Catanduanes',
                'nature_of_work' => 'Mangrove, Tree, Bamboo and  Vegetable Planting, Community Gardening and Dengue Prevention Activity',
                'proponent' => 'PG-Catanduanes',
                'implementation_mode' => 'Through ACP',
                'number_of_days' => 10,
                'source_barangay' => 'Bacak-10\nBagatabao-12\nBugao-12\nQuezon-13\nQuigaray-11\nSan Isidro-15\nSan Rafael-12\nSan Vicente-15\nSanta Mesa-7\nSanta Teresa-8\nSuchan-11',
                'source_municipality' => 'Bagamanoc',
                'source_province' => 'Catanduanes',
                'source_district' => 'Lone',
                'income_class' => '4th Class',
                'beneficiaries_total' => 1214,
                'beneficiaries_female' => 579,
                'beneficiary_type' => 'Underemployed and Self employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '5038100.00',
                'ppe_total' => '890260.00',
                'insurance_total' => '67984.00',
                'requested_to_dole' => '5996344.00',
                'service_fee' => '24280.00',
                'equity' => '1499086.00',
                'source_total_project_cost' => '7495430.00',
                'wage_rate' => '415.00',
                'insurance_rate' => '56.00',
                'municipality_psgc' => '052001000',
                'municipality_name' => 'Bagamanoc',
                'barangay_psgc' => '052001002',
                'barangay_name' => 'Bacak',
                'source_note' => '',
            ],
            [
                'sheet' => 'CATANDUANES',
                'row' => 160,
                'source_status' => 'For procurement of PPEs',
                'adl_number' => '2025-10-1110',
                'fund_sponsor' => 'RMF',
                'partner' => 'Unfunded',
                'source_project_code' => 'TUPAD-RO5-CFO-PGCAT-25-11-08',
                'project_series' => 'RO5-CFO-WP-2025-08-0493',
                'receipt_month' => 'NOVEMBER',
                'receipt_datetime' => '2025-11-11 13:14:59',
                'date_received' => '2025-11-11',
                'project_title' => 'TUPAD-Kalikasan (Kakahuyan Para Sa Kaligtasan at Kabuhayan) sa Catanduanes',
                'nature_of_work' => 'Tree Planting, Vegetable Gardening and Dengue Prevention Activity',
                'proponent' => 'PG-Catanduanes',
                'implementation_mode' => 'Through ACP',
                'number_of_days' => 10,
                'source_barangay' => 'Bagong Sirang-1\nEastern Poblacion-3\nRizal-16\nSagrada-1\nTilod-2\nWestern Poblacion-3',
                'source_municipality' => 'Baras',
                'source_province' => 'Catanduanes',
                'source_district' => 'Lone',
                'income_class' => '4th Class',
                'beneficiaries_total' => 381,
                'beneficiaries_female' => 159,
                'beneficiary_type' => 'Underemployed and Self employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '1581150.00',
                'ppe_total' => '133350.00',
                'insurance_total' => '21336.00',
                'requested_to_dole' => '1735836.00',
                'service_fee' => '7620.00',
                'equity' => '433959.00',
                'source_total_project_cost' => '2169795.00',
                'wage_rate' => '415.00',
                'insurance_rate' => '56.00',
                'municipality_psgc' => '052002000',
                'municipality_name' => 'Baras',
                'barangay_psgc' => '052002003',
                'barangay_name' => 'Bagong Sirang',
                'source_note' => '',
            ],
            [
                'sheet' => 'CATANDUANES',
                'row' => 167,
                'source_status' => 'For procurement of PPEs',
                'adl_number' => '2025-10-1110',
                'fund_sponsor' => 'RMF',
                'partner' => 'Unfunded',
                'source_project_code' => 'TUPAD-RO5-CFO-PGCAT-25-11-09',
                'project_series' => 'RO5-CFO-WP-2025-08-0496',
                'receipt_month' => 'NOVEMBER',
                'receipt_datetime' => '2025-11-11 13:14:59',
                'date_received' => '2025-11-11',
                'project_title' => 'Kalikasan (Kakahuyan Para Sa Kaligtasan at Kabuhayan) sa Catanduanes 3',
                'nature_of_work' => 'Mangrove, Tree and Vegetable Planting and Dengue Prevention Activity',
                'proponent' => 'PG-Catanduanes',
                'implementation_mode' => 'Through ACP',
                'number_of_days' => 10,
                'source_barangay' => 'Bacak-4\nBagatabao-3\nBugao-1\nQuigaray-1\nSan Rafael-4\nSanta Mesa-4',
                'source_municipality' => 'Bagamanoc',
                'source_province' => 'Catanduanes',
                'source_district' => 'Lone',
                'income_class' => '4th Class',
                'beneficiaries_total' => 429,
                'beneficiaries_female' => 216,
                'beneficiary_type' => 'Underemployed and Self employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '1780350.00',
                'ppe_total' => '150150.00',
                'insurance_total' => '24024.00',
                'requested_to_dole' => '1954524.00',
                'service_fee' => '8580.00',
                'equity' => '488631.00',
                'source_total_project_cost' => '2443155.00',
                'wage_rate' => '415.00',
                'insurance_rate' => '56.00',
                'municipality_psgc' => '052001000',
                'municipality_name' => 'Bagamanoc',
                'barangay_psgc' => '052001003',
                'barangay_name' => 'Bagatabao',
                'source_note' => '',
            ],
            [
                'sheet' => 'CATANDUANES',
                'row' => 200,
                'source_status' => 'For Payment',
                'adl_number' => '2025-11-1343',
                'fund_sponsor' => 'RMF',
                'partner' => 'Unfunded',
                'source_project_code' => 'TUPAD-RO5-CFO-BAT-25-11-17',
                'project_series' => 'RO5-CFO-WP-2025-08-0497',
                'receipt_month' => 'NOVEMBER',
                'receipt_datetime' => '2025-11-20 08:26:00',
                'date_received' => '2025-11-20',
                'project_title' => 'Gayon Turismo (Pride of Place, Path of Progress) in Bato 2',
                'nature_of_work' => 'Maintenance and Beautification of Tourist Areas',
                'proponent' => 'LGU-Bato',
                'implementation_mode' => 'Through ACP',
                'number_of_days' => 20,
                'source_barangay' => 'Bagumbayan-1\nBinanuahan-72\nCabugao-1\nIlawod Pob-1\nLibod Pob-4\nMarinawa-3\nMintay-1\nSipi-3\nTamburan-9',
                'source_municipality' => 'Bato',
                'source_province' => 'Catanduanes',
                'source_district' => 'Lone',
                'income_class' => '4th Class',
                'beneficiaries_total' => 95,
                'beneficiaries_female' => 62,
                'beneficiary_type' => 'Underemployed and Self employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '788500.00',
                'ppe_total' => '33250.00',
                'insurance_total' => '4750.00',
                'requested_to_dole' => '826500.00',
                'service_fee' => '1900.00',
                'equity' => '206625.00',
                'source_total_project_cost' => '1033125.00',
                'wage_rate' => '415.00',
                'insurance_rate' => '50.00',
                'municipality_psgc' => '052003000',
                'municipality_name' => 'Bato',
                'barangay_psgc' => '052003002',
                'barangay_name' => 'Bagumbayan',
                'source_note' => '',
            ],
            [
                'sheet' => 'CATANDUANES',
                'row' => 201,
                'source_status' => 'For Payment',
                'adl_number' => '2025-11-1343',
                'fund_sponsor' => 'RMF',
                'partner' => 'Unfunded',
                'source_project_code' => 'TUPAD-RO5-CFO-VIR-25-11-18',
                'project_series' => 'RO5-CFO-WP-2025-10-0518',
                'receipt_month' => 'NOVEMBER',
                'receipt_datetime' => '2025-11-24 15:18:00',
                'date_received' => '2025-11-24',
                'project_title' => 'Environment and Agriculture Warriors in Virac',
                'nature_of_work' => 'Tree Planting, River Clean Up, Creek/Drainage Clean Up, Community Gardening and Dengue Prevention Activity',
                'proponent' => 'LGU-Virac',
                'implementation_mode' => 'Through ACP',
                'number_of_days' => 20,
                'source_barangay' => 'Antipolo Del Norte-9\nAntipolo Del Sur-13\nBalite-20\nBatag-17\nBigaa-22\nCapilihan-13\nConcepcion-19\nConstantino-22\nDanicop-16\nFrancia-18\nGogon Centro-16\nIbong Sapa-14\nLanao-18\nMarcelo Alberto-6\nMarilima-14\nPajo Baguio-28\nPajo San Isidro-44\nPalnab Del Norte-9\nPalnab Del Sur-29\nRawis-21\nSan Juan-13\nSan Pablo-17\nSan Vicente-13\nSanta Cruz-20\nSanta Elena-23\nValencia-16',
                'source_municipality' => 'Virac',
                'source_province' => 'Catanduanes',
                'source_district' => 'Lone',
                'income_class' => '1st Class',
                'beneficiaries_total' => 470,
                'beneficiaries_female' => 219,
                'beneficiary_type' => 'Underemployed and Self employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '3901000.00',
                'ppe_total' => '164500.00',
                'insurance_total' => '23500.00',
                'requested_to_dole' => '4089000.00',
                'service_fee' => '9400.00',
                'equity' => '1022250.00',
                'source_total_project_cost' => '5111250.00',
                'wage_rate' => '415.00',
                'insurance_rate' => '50.00',
                'municipality_psgc' => '052011000',
                'municipality_name' => 'Virac',
                'barangay_psgc' => '052011001',
                'barangay_name' => 'Antipolo Del Norte',
                'source_note' => '',
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
                'implementation_mode' => 'Through ACP',
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
                'source_note' => '',
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
                'implementation_mode' => 'Through ACP',
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
                'source_note' => '',
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
                'implementation_mode' => 'Through ACP',
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
                'source_note' => '',
            ],
            [
                'sheet' => 'MASBATE',
                'row' => 40,
                'source_status' => 'For Liquidation',
                'adl_number' => '2025-01-0008',
                'fund_sponsor' => 'Cong. Wilton Kho',
                'partner' => 'Cong. Wilton Kho',
                'source_project_code' => 'TUPAD-RO5-MPO-DIM-GAID-25-04-01',
                'project_series' => 'RO5-MFO-WP-2025-03-0684',
                'receipt_month' => 'APRIL',
                'receipt_datetime' => '2025-04-07 00:00:00',
                'date_received' => '2025-04-07',
                'project_title' => 'Street Sweeping and Cleaning: Ensuring Accessibility For All',
                'nature_of_work' => 'Street and Sidewalk Sweeping and Cleaning of Public Facilties',
                'proponent' => 'BLGU-Gaid, Dimasalang, Masbate',
                'implementation_mode' => 'Through ACP',
                'number_of_days' => 15,
                'source_barangay' => 'Gaid',
                'source_municipality' => 'Dimasalang',
                'source_province' => 'Masbate',
                'source_district' => '3rd',
                'income_class' => '3rd Class',
                'beneficiaries_total' => 30,
                'beneficiaries_female' => 19,
                'beneficiary_type' => 'A',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '186750.00',
                'ppe_total' => '10500.00',
                'insurance_total' => '1680.00',
                'requested_to_dole' => '198930.00',
                'service_fee' => '600.00',
                'equity' => '49732.50',
                'source_total_project_cost' => '248662.50',
                'wage_rate' => '415.00',
                'insurance_rate' => '56.00',
                'municipality_psgc' => '054108000',
                'municipality_name' => 'Dimasalang',
                'barangay_psgc' => '054108012',
                'barangay_name' => 'Gaid',
                'source_note' => '',
            ],
            [
                'sheet' => 'MASBATE',
                'row' => 47,
                'source_status' => 'For Liquidation',
                'adl_number' => '2025-02-0185',
                'fund_sponsor' => 'Cong. Wilton T. Kho',
                'partner' => 'Cong. Wilton T. Kho',
                'source_project_code' => 'TUPAD-RO5-MPO-PLC-LBAS-25-05-06',
                'project_series' => 'RO5-MFO-WP-2025-05-0692\n',
                'receipt_month' => 'MAY',
                'receipt_datetime' => '2025-06-05 00:00:00',
                'date_received' => '2025-06-05',
                'project_title' => 'Ang Pagtatanim ay Susi sa Malusog at Payak na Pamumuhay ng Mamamayan',
                'nature_of_work' => 'Community Vegetable Gardening',
                'proponent' => 'DOLE-MPO Placer, Masbate',
                'implementation_mode' => 'Through ACP',
                'number_of_days' => 15,
                'source_barangay' => 'Libas',
                'source_municipality' => 'Placer',
                'source_province' => 'Masbate',
                'source_district' => '3rd',
                'income_class' => '1st Class',
                'beneficiaries_total' => 29,
                'beneficiaries_female' => 19,
                'beneficiary_type' => 'A',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '171825.00',
                'ppe_total' => '10150.00',
                'insurance_total' => '1624.00',
                'requested_to_dole' => '183599.00',
                'service_fee' => '580.00',
                'equity' => '45899.75',
                'source_total_project_cost' => '229498.75',
                'wage_rate' => '395.00',
                'insurance_rate' => '56.00',
                'municipality_psgc' => '054117000',
                'municipality_name' => 'Placer',
                'barangay_psgc' => '054117014',
                'barangay_name' => 'Libas',
                'source_note' => '',
            ],
            [
                'sheet' => 'SORSOGON',
                'row' => 28,
                'source_status' => 'For Payment',
                'adl_number' => '2025-01-0008-4',
                'fund_sponsor' => 'RMF',
                'partner' => 'Unfunded2',
                'source_project_code' => 'TUPAD-RO5-SPO-BLN-25-03-24',
                'project_series' => 'R05-SFO-WP-2024-12--0567',
                'receipt_month' => 'MARCH',
                'receipt_datetime' => '2025-03-12 17:00:00',
                'date_received' => '2025-03-12',
                'project_title' => 'Kapaligiran Ko, Kinabukasan Ko',
                'nature_of_work' => 'Cleaning of Streets and Public Facilities and Declogging of Canals and/or Tree Planting',
                'proponent' => 'LGU-Bulan, Sorsogon',
                'implementation_mode' => 'Through ACP',
                'number_of_days' => 15,
                'source_barangay' => 'Aquino-11\nCalomagon-30\nM. Roxas-14\nSan Vicente-38',
                'source_municipality' => 'Bulan',
                'source_province' => 'Sorsogon',
                'source_district' => '2nd',
                'income_class' => '1st Class',
                'beneficiaries_total' => 93,
                'beneficiaries_female' => 73,
                'beneficiary_type' => 'Underemployed/Self Employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '551025.00',
                'ppe_total' => '32550.00',
                'insurance_total' => '4650.00',
                'requested_to_dole' => '588225.00',
                'service_fee' => '1860.00',
                'equity' => '147056.25',
                'source_total_project_cost' => '735281.25',
                'wage_rate' => '395.00',
                'insurance_rate' => '50.00',
                'municipality_psgc' => '056203000',
                'municipality_name' => 'Bulan',
                'barangay_psgc' => '056203025',
                'barangay_name' => 'Benigno S. Aquino',
                'source_note' => '',
            ],
            [
                'sheet' => 'SORSOGON',
                'row' => 29,
                'source_status' => 'For Payment',
                'adl_number' => '2025-03-0268',
                'fund_sponsor' => 'RMF',
                'partner' => 'Unfunded',
                'source_project_code' => 'TUPAD-RO5-SPO-BLN-25-03-25',
                'project_series' => 'R05-SFO-WP-2024-12--0551',
                'receipt_month' => 'MARCH',
                'receipt_datetime' => '2025-03-12 17:00:00',
                'date_received' => '2025-03-12',
                'project_title' => 'Agaw TUPAD Bulusan: Achieving Green and Well-maintained Spaces through TUPAD',
                'nature_of_work' => 'Street Sweeping/Cleaning of Public Areas and/or Community Gardening',
                'proponent' => 'LGU-Bulusan, Sorsogon',
                'implementation_mode' => 'Through ACP',
                'number_of_days' => 10,
                'source_barangay' => 'Bagacay-23\nCentral-4\nCogon-12\nDancalan-20\nDapdap-21\nLalud-14\nLooban-4\nMabuhay-26\nMadlawon-13\nSan Antonio-31\nSan Isidro-44\nSan Jose-14\nSan Rafael-8\nSan Roque-14\nSan Vicente-19\nSapngan-10\nSta. Barbara-50\nTinampo-14',
                'source_municipality' => 'Bulusan',
                'source_province' => 'Sorsogon',
                'source_district' => '2nd',
                'income_class' => '3rd Class',
                'beneficiaries_total' => 400,
                'beneficiaries_female' => 286,
                'beneficiary_type' => 'Underemployed/Self Employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '1580000.00',
                'ppe_total' => '140000.00',
                'insurance_total' => '20000.00',
                'requested_to_dole' => '1740000.00',
                'service_fee' => '8000.00',
                'equity' => '435000.00',
                'source_total_project_cost' => '2175000.00',
                'wage_rate' => '395.00',
                'insurance_rate' => '50.00',
                'municipality_psgc' => '056204000',
                'municipality_name' => 'Bulusan',
                'barangay_psgc' => '056204001',
                'barangay_name' => 'Bagacay',
                'source_note' => '',
            ],
            [
                'sheet' => 'SORSOGON',
                'row' => 35,
                'source_status' => 'For Payment',
                'adl_number' => '2025-01-0008',
                'fund_sponsor' => 'RMF',
                'partner' => 'Unfunded2',
                'source_project_code' => 'TUPAD-RO5-SPO-PLR-25-03-31',
                'project_series' => 'R05-SFO-WP-',
                'receipt_month' => 'MARCH',
                'receipt_datetime' => '2025-03-14 08:48:00',
                'date_received' => '2025-03-14',
                'project_title' => 'Kapaligiran Ko, Kinabukasan Ko',
                'nature_of_work' => 'Tree Planting and/or Declogging of Canals',
                'proponent' => 'LGU-Pilar, Sorsogon',
                'implementation_mode' => 'Through ACP',
                'number_of_days' => 15,
                'source_barangay' => 'Abucay-13\nCalongay-14\nDanlog-10\nDapdap-14\nEsperanza-11\nInang-12\nMigabod-10\nPineda-8',
                'source_municipality' => 'Pilar',
                'source_province' => 'Sorsogon',
                'source_district' => '1st',
                'income_class' => '1st Class',
                'beneficiaries_total' => 92,
                'beneficiaries_female' => 48,
                'beneficiary_type' => 'Underemployed/Self Employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '545100.00',
                'ppe_total' => '78200.00',
                'insurance_total' => '4600.00',
                'requested_to_dole' => '627900.00',
                'service_fee' => '1840.00',
                'equity' => '156975.00',
                'source_total_project_cost' => '784875.00',
                'wage_rate' => '395.00',
                'insurance_rate' => '50.00',
                'municipality_psgc' => '056213000',
                'municipality_name' => 'Pilar',
                'barangay_psgc' => '056213002',
                'barangay_name' => 'Abucay',
                'source_note' => '',
            ],
            [
                'sheet' => 'SORSOGON',
                'row' => 36,
                'source_status' => 'For Payment',
                'adl_number' => '2025-03-0268',
                'fund_sponsor' => 'RMF',
                'partner' => 'Unfunded',
                'source_project_code' => 'TUPAD-RO5-SPO-PRD-25-03-32',
                'project_series' => 'R05-SFO-WP-',
                'receipt_month' => 'MARCH',
                'receipt_datetime' => '2025-03-14 08:48:00',
                'date_received' => '2025-03-14',
                'project_title' => 'Kapaligiran Ko, Kinabukasan Ko',
                'nature_of_work' => 'Street Cleaning of Public Facilities, River/Coastal Clean Up and/or Community Gardening',
                'proponent' => 'LGU-Prieto Diaz',
                'implementation_mode' => 'Through ACP',
                'number_of_days' => 10,
                'source_barangay' => 'Briallante-15\nBulawan-16\nCalao-19\nCarayat-16\nGogon-18\nLupi-18\nManingcay De Oro-18\nManlabong-18\nPerlas-18\nQuidolog-18\nRizal-18\nSan Antonio-11\nSan Fernando-18\nSan Isidro-18\nSan Juan-18\nSan Rafael-18\nSan Ramon-18\nSta Lourdes-18\nSto Domingo-18\nTalisayan-18\nTupaz-18\nUlag-18',
                'source_municipality' => 'Prieto Diaz',
                'source_province' => 'Sorsogon',
                'source_district' => '2nd',
                'income_class' => '4th Class',
                'beneficiaries_total' => 400,
                'beneficiaries_female' => 266,
                'beneficiary_type' => 'Underemployed/Self Employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '1580000.00',
                'ppe_total' => '140000.00',
                'insurance_total' => '20000.00',
                'requested_to_dole' => '1740000.00',
                'service_fee' => '8000.00',
                'equity' => '435000.00',
                'source_total_project_cost' => '2175000.00',
                'wage_rate' => '395.00',
                'insurance_rate' => '50.00',
                'municipality_psgc' => '056214000',
                'municipality_name' => 'Prieto Diaz',
                'barangay_psgc' => '056214002',
                'barangay_name' => 'Bulawan',
                'source_note' => '',
            ],
            [
                'sheet' => 'SORSOGON',
                'row' => 42,
                'source_status' => 'For Payment',
                'adl_number' => '2025-01-0008',
                'fund_sponsor' => 'RMF',
                'partner' => 'Unfunded1',
                'source_project_code' => 'TUPAD-RO5-SPO-PLR-25-03-38',
                'project_series' => 'R05-SFO-WP-2024-12--0558',
                'receipt_month' => 'MARCH',
                'receipt_datetime' => '2025-03-18 02:42:00',
                'date_received' => '2025-03-18',
                'project_title' => 'Agaw TUPAD Pilar: Achieving Green and Well-maintained Spaces through TUPAD',
                'nature_of_work' => 'Street and Sidewalk Sweeping',
                'proponent' => 'LGU-Pilar, Sorsogon',
                'implementation_mode' => 'Through ACP',
                'number_of_days' => 10,
                'source_barangay' => 'Abas-15\nAbucay-15\nBantayan-18\nBanuyo-29\nBayasong-15\nBayawas-30\nBinanuahan-15\nCabiguan-15\nCagdongon-18\nCalongay-15\nCalpi-15',
                'source_municipality' => 'Pilar',
                'source_province' => 'Sorsogon',
                'source_district' => '1st',
                'income_class' => '1st Class',
                'beneficiaries_total' => 1000,
                'beneficiaries_female' => 633,
                'beneficiary_type' => 'Underemployed/Self Employed',
                'displacement_type' => 'Economic Crisis',
                'wages_total' => '3950000.00',
                'ppe_total' => '350000.00',
                'insurance_total' => '50000.00',
                'requested_to_dole' => '4350000.00',
                'service_fee' => '20000.00',
                'equity' => '1087500.00',
                'source_total_project_cost' => '5437500.00',
                'wage_rate' => '395.00',
                'insurance_rate' => '50.00',
                'municipality_psgc' => '056213000',
                'municipality_name' => 'Pilar',
                'barangay_psgc' => '056213001',
                'barangay_name' => 'Abas',
                'source_note' => '',
            ],
        ];
    }
}
