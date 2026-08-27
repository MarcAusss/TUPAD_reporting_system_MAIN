<?php

namespace Database\Seeders;

use App\Enums\ImplementationMode;
use App\Enums\PpeType;
use App\Enums\ProjectDraftStatus;
use App\Enums\ProjectStatus;
use App\Enums\ProjectTerm;
use App\Models\Adl;
use App\Models\Project;
use App\Models\ProjectDraft;
use App\Models\User;
use Illuminate\Database\Seeder;

class DevelopmentDataSeeder extends Seeder
{
    public function run(): void
    {
        $tc = User::where('username', 'tc')->firstOrFail();
        $gip = User::where('username', 'gip')->firstOrFail();
        $focal = User::where('username', 'focal')->firstOrFail();

        $adl = Adl::updateOrCreate(
            ['adl_number' => 'ADL-2026-001'],
            [
                'grants' => 8000000,
                'admin_cost' => 240000,
                'total' => 8000000,
                'created_by' => $focal->id,
                'updated_by' => null,
            ]
        );

        $adl->realignments()->firstOrCreate(
            ['reference_number' => 'REALIGN-2026-001'],
            [
                'amount' => -500000,
                'realignment_date' => '2026-08-15',
                'reason' => 'Sample reduction of available program funds.',
                'created_by' => $focal->id,
            ]
        );

        $viracAllocation = $adl->allocations()->firstOrCreate(
            ['partner' => 'LGU Virac'],
            [
                'fund_sponsor' => 'DOLE',
                'location' => 'Virac, Catanduanes',
                'amount' => 2500000,
                'remarks' => 'Development seed allocation.',
                'created_by' => $focal->id,
            ]
        );

        $sanAndresAllocation = $adl->allocations()->firstOrCreate(
            ['partner' => 'LGU San Andres'],
            [
                'fund_sponsor' => 'DOLE',
                'location' => 'San Andres, Catanduanes',
                'amount' => 2000000,
                'remarks' => 'Development seed allocation.',
                'created_by' => $focal->id,
            ]
        );

        $barasAllocation = $adl->allocations()->firstOrCreate(
            ['partner' => 'LGU Baras'],
            [
                'fund_sponsor' => 'DOLE',
                'location' => 'Baras, Catanduanes',
                'amount' => 1500000,
                'remarks' => 'Development seed allocation.',
                'created_by' => $focal->id,
            ]
        );

        $projectOne = Project::firstOrCreate(
            ['project_title' => 'Community Clean-Up and Rehabilitation - Virac'],
            [
                'adl_allocation_id' => $viracAllocation->id,
                'date_received' => '2026-08-18',
                'nature_of_work' => 'Community cleaning and rehabilitation activities.',
                'province' => 'Catanduanes',
                'district' => 'Lone District',
                'municipality' => 'Virac',
                'barangay' => 'Mabini',
                'income_class' => null,
                'implementation_mode' => ImplementationMode::DIRECT_ADMINISTRATION,
                'number_of_days' => 20,
                'term' => ProjectTerm::SHORT_TERM,
                'beneficiaries_total' => 100,
                'beneficiaries_female' => 45,
                'wage_rate' => 455,
                'wages_total' => 910000,
                'ppe_total' => 5000,
                'insurance_rate' => 50,
                'insurance_total' => 5000,
                'total_project_cost' => 920000,
                'status' => ProjectStatus::ONGOING_PROFILING,
                'remarks' => 'Development sample project.',
                'created_by' => $tc->id,
            ]
        );

        $projectOne->ppeItems()->firstOrCreate(
            ['product' => 'Protective Gloves'],
            [
                'ppe_type' => PpeType::NON_HAZARDOUS,
                'beneficiary_count' => 100,
                'unit_amount' => 50,
                'total_amount' => 5000,
            ]
        );

        $projectTwo = Project::firstOrCreate(
            ['project_title' => 'Roadside Clearing Project - San Andres'],
            [
                'adl_allocation_id' => $sanAndresAllocation->id,
                'date_received' => '2026-08-10',
                'nature_of_work' => 'Roadside clearing and community maintenance.',
                'province' => 'Catanduanes',
                'district' => 'Lone District',
                'municipality' => 'San Andres',
                'barangay' => 'Belmonte',
                'income_class' => null,
                'implementation_mode' => ImplementationMode::DIRECT_ADMINISTRATION,
                'number_of_days' => 20,
                'term' => ProjectTerm::SHORT_TERM,
                'beneficiaries_total' => 80,
                'beneficiaries_female' => 35,
                'wage_rate' => 455,
                'wages_total' => 728000,
                'ppe_total' => 4000,
                'insurance_rate' => 50,
                'insurance_total' => 4000,
                'total_project_cost' => 736000,
                'status' => ProjectStatus::APPROVED,
                'created_by' => $tc->id,
            ]
        );

        $projectTwo->ppeItems()->firstOrCreate(
            ['product' => 'Protective Gloves'],
            [
                'ppe_type' => PpeType::NON_HAZARDOUS,
                'beneficiary_count' => 80,
                'unit_amount' => 50,
                'total_amount' => 4000,
            ]
        );

        $projectTwo->approval()->firstOrCreate(
            ['project_id' => $projectTwo->id],
            [
                'approval_date' => '2026-08-17',
                'project_code' => 'TUPAD-2026-001',
                'remarks' => 'Development approval record.',
                'approved_by' => $tc->id,
                'approved_at' => '2026-08-17 10:00:00',
            ]
        );

        $projectThree = Project::firstOrCreate(
            ['project_title' => 'Coastal Rehabilitation Project - Baras'],
            [
                'adl_allocation_id' => $barasAllocation->id,
                'date_received' => '2026-07-20',
                'nature_of_work' => 'Coastal cleanup and rehabilitation.',
                'province' => 'Catanduanes',
                'district' => 'Lone District',
                'municipality' => 'Baras',
                'barangay' => 'Poblacion',
                'income_class' => null,
                'implementation_mode' => ImplementationMode::DIRECT_ADMINISTRATION,
                'number_of_days' => 20,
                'term' => ProjectTerm::SHORT_TERM,
                'beneficiaries_total' => 50,
                'beneficiaries_female' => 20,
                'wage_rate' => 455,
                'wages_total' => 455000,
                'ppe_total' => 2500,
                'insurance_rate' => 50,
                'insurance_total' => 2500,
                'total_project_cost' => 460000,
                'status' => ProjectStatus::FOR_PAYMENT,
                'created_by' => $tc->id,
            ]
        );

        $projectThree->ppeItems()->firstOrCreate(
            ['product' => 'Protective Gloves'],
            [
                'ppe_type' => PpeType::NON_HAZARDOUS,
                'beneficiary_count' => 50,
                'unit_amount' => 50,
                'total_amount' => 2500,
            ]
        );

        $projectThree->approval()->firstOrCreate(
            ['project_id' => $projectThree->id],
            [
                'approval_date' => '2026-07-25',
                'project_code' => 'TUPAD-2026-002',
                'remarks' => 'Development approval record.',
                'approved_by' => $tc->id,
                'approved_at' => '2026-07-25 10:00:00',
            ]
        );

        $projectThree->postDocuments()->firstOrCreate(
            ['document_type' => 'Accomplishment Report'],
            [
                'date_received' => '2026-08-18',
                'attachment_path' => null,
                'remarks' => 'Development post-document.',
                'date_forwarded_to_imsd' => '2026-08-19',
                'recorded_by' => $tc->id,
            ]
        );

        $draft = ProjectDraft::firstOrCreate(
            ['project_title' => 'GIP Draft Community Project - Virac'],
            [
                'encoded_by' => $gip->id,
                'assigned_tc_id' => $tc->id,
                'adl_allocation_id' => $viracAllocation->id,
                'date_received' => '2026-08-20',
                'nature_of_work' => 'Draft community maintenance activity.',
                'province' => 'Catanduanes',
                'district' => 'Lone District',
                'municipality' => 'Virac',
                'barangay' => 'San Isidro Village',
                'income_class' => null,
                'implementation_mode' => ImplementationMode::DIRECT_ADMINISTRATION,
                'number_of_days' => 20,
                'term' => ProjectTerm::SHORT_TERM,
                'beneficiaries_total' => 40,
                'beneficiaries_female' => 18,
                'wage_rate' => 455,
                'wages_total' => 364000,
                'ppe_total' => 2000,
                'insurance_rate' => 50,
                'insurance_total' => 2000,
                'total_project_cost' => 368000,
                'status' => ProjectDraftStatus::PENDING_TC_REVIEW,
                'remarks' => 'Development GIP draft.',
                'submitted_at' => now(),
            ]
        );

        $draft->ppeItems()->firstOrCreate(
            ['product' => 'Protective Gloves'],
            [
                'ppe_type' => PpeType::NON_HAZARDOUS,
                'beneficiary_count' => 40,
                'unit_amount' => 50,
                'total_amount' => 2000,
            ]
        );
    }
}
