<?php

namespace Database\Seeders;

use App\Enums\ImplementationMode;
use App\Enums\PpeType;
use App\Enums\ProjectStatus;
use App\Enums\ProjectTerm;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Project;
use App\Models\ProjectMonitoringDetail;
use App\Models\ProjectObligation;
use App\Models\Province;
use App\Models\User;
use App\Services\Projects\ProjectCodeGenerator;
use App\Services\Projects\ProjectLocationCanonicalService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Seeds 13 projects per Bicol province (one per ProjectStatus value), split
 * unevenly and differently per province between Direct Administration and
 * Through ACP, with every downstream workflow record a real project at that
 * status would already have.
 *
 * Approved and For Submission of Post-Docs are always Direct Administration:
 * ProjectStatusEngine auto-advances an Approved Through ACP project (with an
 * approval record) straight to For Payment, and For Submission of Post-Docs
 * does not exist on the Through ACP happy path at all
 * (see ProjectWorkflowDefinition::happyPathFor()). For Release of Check to
 * Proponent, For Liquidation, and Partially Liquidated are always Through
 * ACP for the mirror-image reason: none of them exist on the Direct
 * 
 * Administration happy path. Every other status is free, and is assigned
 * per province so the Direct Administration vs Through ACP split is uneven
 * and looks different from one province to the next.
 */
final class ProvinceProjectStatusCoverageSeeder extends Seeder
{
    private const ADL_BATCH = 'PROVINCE-STATUS-COVERAGE';

    private const SEED_MARKER = '[PROVINCE-STATUS-COVERAGE-SEED]';

    /** @var array<int, ProjectStatus> */
    private const STATUS_ORDER = [
        ProjectStatus::ONGOING_PROFILING,
        ProjectStatus::TSSD_EVALUATION,
        ProjectStatus::FOR_COMPLIANCE,
        ProjectStatus::FOR_APPROVAL,
        ProjectStatus::APPROVED,
        ProjectStatus::FOR_IMPLEMENTATION,
        ProjectStatus::ONGOING_IMPLEMENTATION,
        ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
        ProjectStatus::FOR_PAYMENT,
        ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT,
        ProjectStatus::FOR_LIQUIDATION,
        ProjectStatus::PARTIALLY_LIQUIDATED,
        ProjectStatus::COMPLETED,
    ];

    /**
     * Per province, the Implementation Mode for each of the 13 statuses.
     * Deliberately uneven and distinct per province (see class docblock).
     *
     * @var array<string, array<string, string>>
     */
    private const PROVINCE_MODE_PLAN = [
        // Direct Administration 9 / Through ACP 4.
        'Albay' => [
            'ongoing_profiling' => 'direct_administration',
            'tssd_evaluation' => 'direct_administration',
            'for_compliance' => 'direct_administration',
            'for_approval' => 'direct_administration',
            'approved' => 'direct_administration',
            'for_implementation' => 'direct_administration',
            'ongoing_implementation' => 'direct_administration',
            'for_submission_of_post_docs' => 'direct_administration',
            'for_payment' => 'direct_administration',
            'for_release_of_check_to_proponent' => 'through_acp',
            'for_liquidation' => 'through_acp',
            'partially_liquidated' => 'through_acp',
            'completed' => 'through_acp',
        ],
        // Direct Administration 3 / Through ACP 10.
        'Camarines Norte' => [
            'ongoing_profiling' => 'through_acp',
            'tssd_evaluation' => 'through_acp',
            'for_compliance' => 'through_acp',
            'for_approval' => 'through_acp',
            'approved' => 'direct_administration',
            'for_implementation' => 'through_acp',
            'ongoing_implementation' => 'through_acp',
            'for_submission_of_post_docs' => 'direct_administration',
            'for_payment' => 'through_acp',
            'for_release_of_check_to_proponent' => 'through_acp',
            'for_liquidation' => 'through_acp',
            'partially_liquidated' => 'through_acp',
            'completed' => 'direct_administration',
        ],
        // Direct Administration 8 / Through ACP 5.
        'Camarines Sur' => [
            'ongoing_profiling' => 'direct_administration',
            'tssd_evaluation' => 'direct_administration',
            'for_compliance' => 'direct_administration',
            'for_approval' => 'direct_administration',
            'approved' => 'direct_administration',
            'for_implementation' => 'direct_administration',
            'ongoing_implementation' => 'direct_administration',
            'for_submission_of_post_docs' => 'direct_administration',
            'for_payment' => 'through_acp',
            'for_release_of_check_to_proponent' => 'through_acp',
            'for_liquidation' => 'through_acp',
            'partially_liquidated' => 'through_acp',
            'completed' => 'through_acp',
        ],
        // Direct Administration 4 / Through ACP 9.
        'Catanduanes' => [
            'ongoing_profiling' => 'direct_administration',
            'tssd_evaluation' => 'through_acp',
            'for_compliance' => 'through_acp',
            'for_approval' => 'through_acp',
            'approved' => 'direct_administration',
            'for_implementation' => 'through_acp',
            'ongoing_implementation' => 'through_acp',
            'for_submission_of_post_docs' => 'direct_administration',
            'for_payment' => 'through_acp',
            'for_release_of_check_to_proponent' => 'through_acp',
            'for_liquidation' => 'through_acp',
            'partially_liquidated' => 'through_acp',
            'completed' => 'direct_administration',
        ],
        // Direct Administration 10 / Through ACP 3.
        'Masbate' => [
            'ongoing_profiling' => 'direct_administration',
            'tssd_evaluation' => 'direct_administration',
            'for_compliance' => 'direct_administration',
            'for_approval' => 'direct_administration',
            'approved' => 'direct_administration',
            'for_implementation' => 'direct_administration',
            'ongoing_implementation' => 'direct_administration',
            'for_submission_of_post_docs' => 'direct_administration',
            'for_payment' => 'direct_administration',
            'for_release_of_check_to_proponent' => 'through_acp',
            'for_liquidation' => 'through_acp',
            'partially_liquidated' => 'through_acp',
            'completed' => 'direct_administration',
        ],
        // Direct Administration 2 / Through ACP 11.
        'Sorsogon' => [
            'ongoing_profiling' => 'through_acp',
            'tssd_evaluation' => 'through_acp',
            'for_compliance' => 'through_acp',
            'for_approval' => 'through_acp',
            'approved' => 'direct_administration',
            'for_implementation' => 'through_acp',
            'ongoing_implementation' => 'through_acp',
            'for_submission_of_post_docs' => 'direct_administration',
            'for_payment' => 'through_acp',
            'for_release_of_check_to_proponent' => 'through_acp',
            'for_liquidation' => 'through_acp',
            'partially_liquidated' => 'through_acp',
            'completed' => 'through_acp',
        ],
    ];

    private int $sequence = 0;

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException(
                'ProvinceProjectStatusCoverageSeeder is a development/test data seeder and is disabled in production.'
            );
        }

        // Guarantees the six Bicol provinces/municipalities/barangays and the
        // development Focal/Admin/TC accounts this seeder depends on exist.
        // Skipped when they're already present (e.g. DatabaseSeeder already
        // ran Fy2025TupadProjectSeeder) so this seeder doesn't reprint new
        // random development passwords and redo several seconds of work.
        if (Province::query()->where('is_active', true)->count() < count(self::PROVINCE_MODE_PLAN) || User::query()->count() === 0) {
            $this->call(Fy2025TupadProjectSeeder::class);
        }

        $actor = $this->resolveActor();
        $today = CarbonImmutable::now('Asia/Manila')->startOfDay();

        $provinces = Province::query()
            ->whereIn('name', array_keys(self::PROVINCE_MODE_PLAN))
            ->where('is_active', true)
            ->get()
            ->keyBy('name');

        if ($provinces->count() !== count(self::PROVINCE_MODE_PLAN)) {
            throw new RuntimeException('All six Bicol provinces must exist before ProvinceProjectStatusCoverageSeeder can run.');
        }

        DB::transaction(function () use ($provinces, $actor, $today): void {
            $this->resetPreviouslySeeded();

            foreach (self::PROVINCE_MODE_PLAN as $provinceName => $modePlan) {
                $province = $provinces[$provinceName];
                $municipalities = Municipality::query()
                    ->where('province_id', $province->id)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get();

                if ($municipalities->isEmpty()) {
                    throw new RuntimeException("Province {$provinceName} has no active municipalities to seed against.");
                }

                foreach (self::STATUS_ORDER as $index => $status) {
                    $mode = ImplementationMode::from($modePlan[$status->value]);

                    $this->seedProject(
                        province: $province,
                        municipalities: $municipalities,
                        sequenceInProvince: $index,
                        status: $status,
                        mode: $mode,
                        actor: $actor,
                        today: $today,
                    );
                }
            }
        });

        $totalDa = collect(self::PROVINCE_MODE_PLAN)
            ->flatten()
            ->filter(fn (string $mode): bool => $mode === ImplementationMode::DIRECT_ADMINISTRATION->value)
            ->count();

        $totalAcp = collect(self::PROVINCE_MODE_PLAN)
            ->flatten()
            ->filter(fn (string $mode): bool => $mode === ImplementationMode::THROUGH_ACP->value)
            ->count();

        $this->command?->info(sprintf(
            'Seeded %d projects across %d provinces (13 per province, one per status). Direct Administration: %d; Through ACP: %d.',
            count(self::PROVINCE_MODE_PLAN) * count(self::STATUS_ORDER),
            count(self::PROVINCE_MODE_PLAN),
            $totalDa,
            $totalAcp,
        ));
    }

    private function resolveActor(): User
    {
        $actor = User::query()
            ->where('is_active', true)
            ->whereIn('role', [UserRole::FOCAL->value, UserRole::ADMIN->value])
            ->orderByRaw('CASE WHEN role = ? THEN 0 ELSE 1 END', [UserRole::FOCAL->value])
            ->orderBy('id')
            ->first();

        if (! $actor) {
            throw new RuntimeException(
                'ProvinceProjectStatusCoverageSeeder requires an active Focal or Administrator user.'
            );
        }

        return $actor;
    }

    private function resetPreviouslySeeded(): void
    {
        $adlIds = Adl::query()->where('batch', self::ADL_BATCH)->pluck('id');

        if ($adlIds->isEmpty()) {
            return;
        }

        $allocationIds = AdlAllocation::query()->whereIn('adl_id', $adlIds)->pluck('id');

        Project::query()->whereIn('adl_allocation_id', $allocationIds)->delete();
        AdlAllocation::query()->whereIn('id', $allocationIds)->delete();
        Adl::query()->whereIn('id', $adlIds)->delete();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Municipality>  $municipalities
     */
    private function seedProject(
        Province $province,
        \Illuminate\Support\Collection $municipalities,
        int $sequenceInProvince,
        ProjectStatus $status,
        ImplementationMode $mode,
        User $actor,
        CarbonImmutable $today,
    ): void {
        $this->sequence++;

        $municipality = $municipalities[$sequenceInProvince % $municipalities->count()];
        $barangays = Barangay::query()
            ->where('municipality_id', $municipality->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($barangays->isEmpty()) {
            throw new RuntimeException("Municipality {$municipality->name} ({$province->name}) has no active barangays to seed against.");
        }

        $barangay = $barangays[$sequenceInProvince % $barangays->count()];

        $beneficiariesTotal = 35 + ($sequenceInProvince * 4);
        $femaleRatio = [0.40, 0.45, 0.50, 0.55, 0.60][$sequenceInProvince % 5];
        $beneficiariesFemale = (int) min($beneficiariesTotal, round($beneficiariesTotal * $femaleRatio));

        $numberOfDays = [10, 15, 20, 25, 30][$sequenceInProvince % 5];
        $term = ProjectTerm::fromDays($numberOfDays);

        $wageRate = '400.00';
        $wagesTotal = $this->money($wageRate * $numberOfDays * $beneficiariesTotal);

        $ppeUnitAmount = '850.00';
        $ppeTotal = $this->money($ppeUnitAmount * $beneficiariesTotal);

        $insuranceRate = '50.00';
        $insuranceTotal = $this->money($insuranceRate * $beneficiariesTotal);

        $totalProjectCost = $this->money(
            (float) $wagesTotal + (float) $ppeTotal + (float) $insuranceTotal
        );

        $serviceFee = $mode === ImplementationMode::DIRECT_ADMINISTRATION
            ? $this->money($beneficiariesTotal * 20)
            : '0.00';

        $dateReceived = $today->subDays(20 + (array_search($status, self::STATUS_ORDER, true) * 15));

        $adl = Adl::query()->create([
            'adl_number' => sprintf('SC-%s-%04d', $province->code, $this->sequence),
            'grants' => $totalProjectCost,
            'admin_cost' => $serviceFee,
            'total' => $this->money((float) $totalProjectCost + (float) $serviceFee),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
            'date_received' => $dateReceived->toDateString(),
            'batch' => self::ADL_BATCH,
            'tranche' => $status->label(),
            'sponsor_reference' => self::SEED_MARKER,
        ]);

        $allocation = AdlAllocation::query()->create([
            'adl_id' => $adl->id,
            'fund_sponsor' => 'RMF',
            'partner' => $mode === ImplementationMode::DIRECT_ADMINISTRATION ? 'Unfunded' : 'ACP Partner Organization',
            'location' => "{$barangay->name}, {$municipality->name}, {$province->name}",
            'province' => $province->name,
            'district' => $municipality->district,
            'municipality' => $municipality->name,
            'amount' => $totalProjectCost,
            'grant_amount' => $totalProjectCost,
            'admin_cost_amount' => $serviceFee,
            'total_amount' => $this->money((float) $totalProjectCost + (float) $serviceFee),
            'remarks' => self::SEED_MARKER.' '.$status->label(),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $project = Project::query()->create([
            'adl_allocation_id' => $allocation->id,
            'date_received' => $dateReceived->toDateString(),
            'project_title' => sprintf(
                '%s Community Livelihood and Cleanup Program - %s',
                $status->label(),
                $barangay->name,
            ),
            'nature_of_work' => 'Street and Sidewalk Sweeping, Cleaning of Public Facilities, and Community Gardening',
            'fund_sponsor' => 'RMF',
            'partner' => $mode === ImplementationMode::DIRECT_ADMINISTRATION ? 'Unfunded' : 'ACP Partner Organization',
            'program' => 'TUPAD',
            'project_series' => sprintf('SC-%s-%04d', $province->code, $this->sequence),
            'project_series_remarks' => self::SEED_MARKER,
            'tevs_date_verified' => null,
            'tevs_remarks' => null,
            'province' => $province->name,
            'district' => $municipality->district,
            'municipality' => $municipality->name,
            'barangay' => $barangay->name,
            'income_class' => $municipality->income_class,
            'province_id' => $province->id,
            'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id,
            'implementation_mode' => $mode,
            'number_of_days' => $numberOfDays,
            'term' => $term,
            'intervention_focus' => null,
            'beneficiaries_total' => $beneficiariesTotal,
            'beneficiaries_female' => $beneficiariesFemale,
            'wage_rate' => $wageRate,
            'wages_total' => $wagesTotal,
            'ppe_total' => $ppeTotal,
            'insurance_rate' => $insuranceRate,
            'insurance_beneficiaries' => $beneficiariesTotal,
            'insurance_total' => $insuranceTotal,
            'total_project_cost' => $totalProjectCost,
            'status' => $status,
            'remarks' => sprintf(
                '%s Seeded directly at %s status (%s) to demonstrate full workflow coverage.',
                self::SEED_MARKER,
                $status->label(),
                $mode->label(),
            ),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        app(ProjectLocationCanonicalService::class)->createSingleCanonicalLocation(
            $project,
            $province,
            $municipality,
            $barangay,
        );

        ProjectMonitoringDetail::query()->create([
            'project_id' => $project->id,
            'project_series' => $project->project_series,
            'proponent' => "DOLE {$province->name} Provincial Office",
            'receipt_month' => strtoupper($dateReceived->format('F')),
            'receipt_datetime' => $dateReceived->setTime(9, 0)->toDateTimeString(),
            'process_cycle_days' => null,
            'monitoring_remarks' => self::SEED_MARKER,
            'updated_by' => $actor->id,
        ]);

        $project->ppeItems()->create([
            'ppe_type' => PpeType::NON_HAZARDOUS,
            'product' => 'Work Gloves, Boots, and Raincoat',
            'beneficiary_count' => $beneficiariesTotal,
            'quantity' => 1,
            'unit_amount' => $ppeUnitAmount,
            'total_amount' => $ppeTotal,
        ]);

        $history = $project->statusHistory()->whereNull('from_status')->latest('id')->first();
        $history?->update([
            'changed_by' => $actor->id,
            'remarks' => sprintf('%s Project record created directly at %s.', self::SEED_MARKER, $status->label()),
        ]);

        $this->buildWorkflowRecords($project, $status, $mode, $actor, $today);
    }

    private function buildWorkflowRecords(
        Project $project,
        ProjectStatus $status,
        ImplementationMode $mode,
        User $actor,
        CarbonImmutable $today,
    ): void {
        match ($status) {
            ProjectStatus::ONGOING_PROFILING, ProjectStatus::TSSD_EVALUATION => null,

            ProjectStatus::FOR_COMPLIANCE => $this->addEvaluation(
                $project,
                $actor,
                'for_compliance',
                $today->subDays(25),
                findings: 'Submitted attendance sheets do not match the encoded number of beneficiaries.',
                requiredDocuments: 'Revised and signed attendance sheets for all encoded beneficiaries.',
            ),

            ProjectStatus::FOR_APPROVAL => $this->addEvaluation(
                $project,
                $actor,
                'for_approval',
                $today->subDays(30),
            ),

            ProjectStatus::APPROVED => $this->buildApproved($project, $actor, $today),

            ProjectStatus::FOR_IMPLEMENTATION => $this->buildForImplementation($project, $mode, $actor, $today),

            ProjectStatus::ONGOING_IMPLEMENTATION => $this->buildOngoingImplementation($project, $mode, $actor, $today),

            ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS => $this->buildForSubmissionOfPostDocs($project, $actor, $today),

            ProjectStatus::FOR_PAYMENT => $this->buildForPayment($project, $mode, $actor, $today),

            ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT => $this->buildForReleaseOfCheck($project, $actor, $today),

            ProjectStatus::FOR_LIQUIDATION => $this->buildForLiquidation($project, $actor, $today),

            ProjectStatus::PARTIALLY_LIQUIDATED => $this->buildPartiallyLiquidated($project, $actor, $today),

            ProjectStatus::COMPLETED => $this->buildCompleted($project, $mode, $actor, $today),
        };
    }

    private function buildApproved(Project $project, User $actor, CarbonImmutable $today): void
    {
        $this->addEvaluation($project, $actor, 'for_approval', $today->subDays(40));
        $this->addApproval($project, $actor, $today->subDays(35));
    }

    private function buildForImplementation(Project $project, ImplementationMode $mode, User $actor, CarbonImmutable $today): void
    {
        $this->addEvaluation($project, $actor, 'for_approval', $today->subDays(50));
        $this->addApproval($project, $actor, $today->subDays(45));

        if ($mode === ImplementationMode::DIRECT_ADMINISTRATION) {
            $this->addDaPreparation($project, $actor, $today->subDays(40));

            return;
        }

        $this->addAcpPayment($project, $actor, $today->subDays(40));
        $this->addAcpCheckRelease($project, $actor, $today->subDays(37), $today->subDays(35));
    }

    private function buildOngoingImplementation(Project $project, ImplementationMode $mode, User $actor, CarbonImmutable $today): void
    {
        $this->addEvaluation($project, $actor, 'for_approval', $today->subDays(65));
        $this->addApproval($project, $actor, $today->subDays(60));

        if ($mode === ImplementationMode::DIRECT_ADMINISTRATION) {
            $this->addDaPreparation($project, $actor, $today->subDays(55));
            $this->addImplementation($project, $actor, $today->subDays(7), $today->addDays(14));

            return;
        }

        $this->addAcpPayment($project, $actor, $today->subDays(55));
        $this->addAcpCheckRelease($project, $actor, $today->subDays(52), $today->subDays(50));
        $this->addImplementation($project, $actor, $today->subDays(7), $today->addDays(14));
    }

    private function buildForSubmissionOfPostDocs(Project $project, User $actor, CarbonImmutable $today): void
    {
        $this->addEvaluation($project, $actor, 'for_approval', $today->subDays(85));
        $this->addApproval($project, $actor, $today->subDays(80));
        $this->addDaPreparation($project, $actor, $today->subDays(75));
        $this->addImplementation($project, $actor, $today->subDays(40), $today->subDays(5));
    }

    private function buildForPayment(Project $project, ImplementationMode $mode, User $actor, CarbonImmutable $today): void
    {
        if ($mode === ImplementationMode::DIRECT_ADMINISTRATION) {
            $this->addEvaluation($project, $actor, 'for_approval', $today->subDays(105));
            $this->addApproval($project, $actor, $today->subDays(100));
            $this->addDaPreparation($project, $actor, $today->subDays(95));
            $this->addImplementation($project, $actor, $today->subDays(60), $today->subDays(25));
            $this->addPostDocuments($project, $actor, $today->subDays(20), $today->subDays(15));
            $this->addObligation($project, $actor, $today->subDays(10));

            return;
        }

        // Through ACP For Payment is reached the moment approval completes
        // (ProjectApprovalController); no ACP payment record exists yet.
        $this->addEvaluation($project, $actor, 'for_approval', $today->subDays(55));
        $this->addApproval($project, $actor, $today->subDays(50));
    }

    private function buildForReleaseOfCheck(Project $project, User $actor, CarbonImmutable $today): void
    {
        $this->addEvaluation($project, $actor, 'for_approval', $today->subDays(65));
        $this->addApproval($project, $actor, $today->subDays(60));
        $this->addAcpPayment($project, $actor, $today->subDays(50));
    }

    private function buildForLiquidation(Project $project, User $actor, CarbonImmutable $today): void
    {
        $this->addEvaluation($project, $actor, 'for_approval', $today->subDays(125));
        $this->addApproval($project, $actor, $today->subDays(120));
        $this->addAcpPayment($project, $actor, $today->subDays(110));
        $this->addAcpCheckRelease($project, $actor, $today->subDays(105), $today->subDays(100));
        $this->addImplementation($project, $actor, $today->subDays(90), $today->subDays(20));
    }

    private function buildPartiallyLiquidated(Project $project, User $actor, CarbonImmutable $today): void
    {
        $this->addEvaluation($project, $actor, 'for_approval', $today->subDays(125));
        $this->addApproval($project, $actor, $today->subDays(120));
        $this->addAcpPayment($project, $actor, $today->subDays(110));
        $this->addAcpCheckRelease($project, $actor, $today->subDays(105), $today->subDays(100));
        $this->addImplementation($project, $actor, $today->subDays(90), $today->subDays(20));

        // Just over half liquidated so the project is genuinely partial, not complete.
        $partialAmount = $this->money((float) $project->total_project_cost * 0.55);
        $this->addAcpLiquidation($project, $actor, $today->subDays(15), $partialAmount);
    }

    private function buildCompleted(Project $project, ImplementationMode $mode, User $actor, CarbonImmutable $today): void
    {
        $this->addEvaluation($project, $actor, 'for_approval', $today->subDays(155));
        $this->addApproval($project, $actor, $today->subDays(150));

        if ($mode === ImplementationMode::DIRECT_ADMINISTRATION) {
            $this->addDaPreparation($project, $actor, $today->subDays(145));
            $this->addImplementation($project, $actor, $today->subDays(140), $today->subDays(100));
            $this->addPostDocuments($project, $actor, $today->subDays(95), $today->subDays(90));
            $obligation = $this->addObligation($project, $actor, $today->subDays(85));
            $this->addDisbursement($obligation, $actor, $today->subDays(80));

            return;
        }

        $this->addAcpPayment($project, $actor, $today->subDays(140));
        $this->addAcpCheckRelease($project, $actor, $today->subDays(135), $today->subDays(130));
        $this->addImplementation($project, $actor, $today->subDays(125), $today->subDays(60));
        $this->addAcpLiquidation($project, $actor, $today->subDays(50), (string) $project->total_project_cost);
    }

    private function addEvaluation(
        Project $project,
        User $actor,
        string $result,
        CarbonImmutable $evaluatedAt,
        ?string $findings = null,
        ?string $requiredDocuments = null,
    ): void {
        $project->evaluations()->create([
            'findings' => $result === 'for_compliance' ? $findings : null,
            'required_documents' => $result === 'for_compliance' ? $requiredDocuments : null,
            'remarks' => self::SEED_MARKER,
            'result' => $result,
            'evaluated_by' => $actor->id,
            'evaluated_at' => $evaluatedAt->setTime(10, 0)->toDateTimeString(),
        ]);
    }

    private function addApproval(Project $project, User $actor, CarbonImmutable $approvalDate): void
    {
        if ($project->approval()->exists()) {
            return;
        }

        $projectCode = app(ProjectCodeGenerator::class)->generate($project, $approvalDate);

        $project->approval()->create([
            'approval_date' => $approvalDate->toDateString(),
            'project_code' => $projectCode,
            'remarks' => self::SEED_MARKER,
            'approved_by' => $actor->id,
            'approved_at' => $approvalDate->setTime(11, 0)->toDateTimeString(),
        ]);
    }

    private function addDaPreparation(Project $project, User $actor, CarbonImmutable $date): void
    {
        $project->insuranceEnrollment()->create([
            'date_enrolled' => $date->toDateString(),
            'beneficiary_count' => $project->beneficiaries_total,
            'amount' => $project->insurance_total,
            'payment_mode' => 'voucher',
            'or_number' => null,
            'policy_number' => null,
            'remarks' => self::SEED_MARKER,
            'recorded_by' => $actor->id,
        ]);

        $project->ppeDeliveries()->create([
            'delivery_receipt_date' => $date->addDay()->toDateString(),
            'ppe_provided' => 'Work Gloves, Boots, and Raincoat',
            'inventory_reference' => null,
            'remarks' => self::SEED_MARKER,
            'recorded_by' => $actor->id,
        ]);

        $project->noticeToProceed()->create([
            'date_issued' => $date->addDay()->setTime(9, 0)->toDateTimeString(),
            'date_released' => $date->addDay()->setTime(14, 0)->toDateTimeString(),
            'remarks' => self::SEED_MARKER,
            'recorded_by' => $actor->id,
        ]);

        $project->orientation()->create([
            'orientation_date' => $date->addDays(2)->toDateString(),
            'beneficiaries_oriented' => $project->beneficiaries_total,
            'venue' => 'Barangay Multi-Purpose Hall',
            'oriented_by' => 'DOLE Field Office',
            'alkansssya_conducted' => true,
            'yakap_conducted' => true,
            'remarks' => self::SEED_MARKER,
            'recorded_by' => $actor->id,
        ]);
    }

    private function addImplementation(Project $project, User $actor, CarbonImmutable $start, CarbonImmutable $end): void
    {
        if ($project->implementation()->exists()) {
            return;
        }

        $project->implementation()->create([
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'remarks' => self::SEED_MARKER,
            'recorded_by' => $actor->id,
        ]);
    }

    private function addAcpPayment(Project $project, User $actor, CarbonImmutable $date): void
    {
        if ($project->acpPayment()->exists()) {
            return;
        }

        $project->acpPayment()->create([
            'amount' => $project->total_project_cost,
            'payment_date' => $date->toDateString(),
            'payee' => $project->partner ?: 'ACP Partner Organization',
            'payment_reference' => sprintf('ACPPAY-%04d', $this->sequence),
            'remarks' => self::SEED_MARKER,
            'recorded_by' => $actor->id,
        ]);
    }

    private function addAcpCheckRelease(Project $project, User $actor, CarbonImmutable $checkDate, CarbonImmutable $releasedDate): void
    {
        if ($project->acpCheckRelease()->exists()) {
            return;
        }

        $payment = $project->acpPayment()->firstOrFail();

        $project->acpCheckRelease()->create([
            'check_number' => sprintf('CHK-%04d', $this->sequence),
            'check_date' => $checkDate->toDateString(),
            'amount' => $payment->amount,
            'released_date' => $releasedDate->toDateString(),
            'released_to' => $project->partner ?: 'ACP Partner Organization',
            'remarks' => self::SEED_MARKER,
            'recorded_by' => $actor->id,
        ]);
    }

    private function addPostDocuments(Project $project, User $actor, CarbonImmutable $received, CarbonImmutable $forwarded): void
    {
        $project->postDocuments()->create([
            'date_received' => $received->toDateString(),
            'document_type' => 'Attendance Sheets and Accomplishment Report',
            'attachment_path' => null,
            'date_forwarded_to_imsd' => $forwarded->toDateString(),
            'remarks' => self::SEED_MARKER,
            'recorded_by' => $actor->id,
        ]);
    }

    private function addObligation(Project $project, User $actor, CarbonImmutable $date): ProjectObligation
    {
        return $project->obligations()->create([
            'tranche_number' => 1,
            'adl_number' => $project->allocation->adl->adl_number,
            'fund_sponsor' => $project->fund_sponsor ?: 'Not specified',
            'partner' => $project->partner ?: 'Not specified',
            'project_location' => $project->full_location,
            'term' => $project->term->label(),
            'beneficiaries_total' => $project->beneficiaries_total,
            'beneficiaries_female' => $project->beneficiaries_female,
            'amount' => $project->wages_total,
            'obligation_date' => $date->toDateString(),
            'month' => $date->format('F Y'),
            'payee' => $project->partner ?: 'DOLE Field Office',
            'remarks' => self::SEED_MARKER,
            'recorded_by' => $actor->id,
        ]);
    }

    private function addDisbursement(ProjectObligation $obligation, User $actor, CarbonImmutable $date): void
    {
        $obligation->disbursements()->create([
            'amount' => $obligation->amount,
            'date_disbursed' => $date->toDateString(),
            'ldap_check_number' => sprintf('LDAP-%04d', $this->sequence),
            'remarks' => self::SEED_MARKER,
            'recorded_by' => $actor->id,
        ]);
    }

    private function addAcpLiquidation(Project $project, User $actor, CarbonImmutable $date, string $amount): void
    {
        $project->acpLiquidations()->create([
            'liquidation_date' => $date->toDateString(),
            'amount' => $this->money((float) $amount),
            'liquidation_reference' => sprintf('LIQ-%04d', $this->sequence),
            'remarks' => self::SEED_MARKER,
            'recorded_by' => $actor->id,
        ]);
    }

    private function money(float|string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
