<?php

namespace Tests\Feature;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Project;
use App\Models\ProjectPpeDelivery;
use App\Models\ProjectPpeItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PpeDeliveryReceiptBatchTest extends TestCase
{
    use RefreshDatabase;

    private User $tc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
        ]);
    }

    private function createProject(array $overrides = []): Project
    {
        $adl = Adl::create([
            'adl_number' => 'ADL-PPEB-'.uniqid(),
            'grants' => 1000000,
            'admin_cost' => 0,
            'total' => 1000000,
            'created_by' => $this->tc->id,
        ]);

        $allocation = AdlAllocation::create([
            'adl_id' => $adl->id,
            'location' => 'Albay',
            'amount' => 1000000,
            'created_by' => $this->tc->id,
        ]);

        return Project::create(array_merge([
            'adl_allocation_id' => $allocation->id,
            'date_received' => '2026-08-01',
            'project_title' => 'PPE Delivery Batch Test Project',
            'nature_of_work' => 'Community clean-up',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Albay',
            'project_series' => 'Regular TUPAD 2026',
            'tevs_date_verified' => '2026-08-01',
            'province' => 'Albay',
            'district' => '2nd District',
            'municipality' => 'Legazpi City',
            'barangay' => 'Rawis',
            'implementation_mode' => ImplementationMode::DIRECT_ADMINISTRATION,
            'number_of_days' => 20,
            'term' => 'short_term',
            'beneficiaries_total' => 50,
            'beneficiaries_female' => 25,
            'insurance_beneficiaries' => 50,
            'wage_rate' => 455,
            'wages_total' => 455000,
            'ppe_total' => 0,
            'insurance_rate' => 50,
            'insurance_total' => 2500,
            'total_project_cost' => 457500,
            'status' => ProjectStatus::APPROVED,
            'created_by' => $this->tc->id,
        ], $overrides));
    }

    private function addPpeItem(Project $project, string $product, int $beneficiaryCount, string $unitAmount = '100.00'): ProjectPpeItem
    {
        return ProjectPpeItem::create([
            'project_id' => $project->id,
            'ppe_type' => 'non_hazardous',
            'product' => $product,
            'beneficiary_count' => $beneficiaryCount,
            'quantity' => 1,
            'unit_amount' => $unitAmount,
            'total_amount' => number_format($beneficiaryCount * (float) $unitAmount, 2, '.', ''),
        ]);
    }

    public function test_submitting_multiple_receipts_in_one_request_creates_all_of_them(): void
    {
        $project = $this->createProject();
        $gloves = $this->addPpeItem($project, 'Gloves', 20);
        $boots = $this->addPpeItem($project, 'Rubber Boots', 15);

        $response = $this->actingAs($this->tc)->post(
            route('projects.implementation.ppe', $project),
            [
                'deliveries' => [
                    [
                        'delivery_receipt_date' => '2026-08-10',
                        'remarks' => 'First batch',
                        'items' => [
                            $gloves->id => ['ppe_item_id' => $gloves->id, 'quantity' => 10],
                        ],
                    ],
                    [
                        'delivery_receipt_date' => '2026-08-15',
                        'remarks' => 'Second batch',
                        'items' => [
                            $gloves->id => ['ppe_item_id' => $gloves->id, 'quantity' => 10],
                            $boots->id => ['ppe_item_id' => $boots->id, 'quantity' => 15],
                        ],
                    ],
                ],
            ],
        );

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();

        $this->assertSame(2, ProjectPpeDelivery::query()->where('project_id', $project->id)->count());
        $this->assertSame(0, $gloves->fresh()->remainingDeliverableQuantity());
        $this->assertSame(0, $boots->fresh()->remainingDeliverableQuantity());

        $this->assertDatabaseHas('project_ppe_delivery_items', [
            'ppe_item_id' => $gloves->id,
            'quantity' => 10,
        ]);
        $this->assertDatabaseHas('project_ppe_delivery_items', [
            'ppe_item_id' => $boots->id,
            'quantity' => 15,
        ]);
    }

    public function test_cumulative_quantities_across_receipts_cannot_exceed_remaining(): void
    {
        $project = $this->createProject();
        $gloves = $this->addPpeItem($project, 'Gloves', 20);

        $response = $this->actingAs($this->tc)->post(
            route('projects.implementation.ppe', $project),
            [
                'deliveries' => [
                    [
                        'delivery_receipt_date' => '2026-08-10',
                        'items' => [
                            $gloves->id => ['ppe_item_id' => $gloves->id, 'quantity' => 15],
                        ],
                    ],
                    [
                        'delivery_receipt_date' => '2026-08-15',
                        'items' => [
                            // 15 + 10 = 25, exceeds the planned 20.
                            $gloves->id => ['ppe_item_id' => $gloves->id, 'quantity' => 10],
                        ],
                    ],
                ],
            ],
        );

        $response->assertSessionHasErrors('deliveries.1.items.'.$gloves->id.'.quantity');
        $this->assertSame(0, ProjectPpeDelivery::query()->where('project_id', $project->id)->count());
    }

    public function test_a_single_receipt_still_works_via_the_deliveries_array_shape(): void
    {
        $project = $this->createProject();
        $gloves = $this->addPpeItem($project, 'Gloves', 20);

        $this->actingAs($this->tc)->post(
            route('projects.implementation.ppe', $project),
            [
                'deliveries' => [
                    [
                        'delivery_receipt_date' => '2026-08-10',
                        'items' => [
                            $gloves->id => ['ppe_item_id' => $gloves->id, 'quantity' => 5],
                        ],
                    ],
                ],
            ],
        )->assertSessionDoesntHaveErrors();

        $this->assertSame(1, ProjectPpeDelivery::query()->where('project_id', $project->id)->count());
        $this->assertSame(15, $gloves->fresh()->remainingDeliverableQuantity());
    }

    public function test_project_with_no_ppe_items_does_not_require_items(): void
    {
        $project = $this->createProject();

        $this->actingAs($this->tc)->post(
            route('projects.implementation.ppe', $project),
            [
                'deliveries' => [
                    [
                        'delivery_receipt_date' => '2026-08-10',
                        'remarks' => 'No PPE required for this project.',
                    ],
                ],
            ],
        )->assertSessionDoesntHaveErrors();

        $this->assertSame(1, ProjectPpeDelivery::query()->where('project_id', $project->id)->count());
    }

    public function test_a_zero_quantity_is_accepted_for_an_item_not_actually_delivered_in_this_receipt(): void
    {
        $project = $this->createProject();
        $gloves = $this->addPpeItem($project, 'Gloves', 20);
        $boots = $this->addPpeItem($project, 'Rubber Boots', 15);

        $response = $this->actingAs($this->tc)->post(
            route('projects.implementation.ppe', $project),
            [
                'deliveries' => [
                    [
                        'delivery_receipt_date' => '2026-08-10',
                        'items' => [
                            $gloves->id => ['ppe_item_id' => $gloves->id, 'quantity' => 5],
                            // Boots were selected but none were actually in this receipt.
                            $boots->id => ['ppe_item_id' => $boots->id, 'quantity' => 0],
                        ],
                    ],
                ],
            ],
        );

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame(1, ProjectPpeDelivery::query()->where('project_id', $project->id)->count());
        $this->assertSame(15, $gloves->fresh()->remainingDeliverableQuantity());
        $this->assertSame(15, $boots->fresh()->remainingDeliverableQuantity());

        $this->assertDatabaseHas('project_ppe_delivery_items', [
            'ppe_item_id' => $boots->id,
            'quantity' => 0,
        ]);
    }

    public function test_a_receipt_with_no_items_selected_is_rejected_when_project_has_planned_items(): void
    {
        $project = $this->createProject();
        $this->addPpeItem($project, 'Gloves', 20);

        $response = $this->actingAs($this->tc)->post(
            route('projects.implementation.ppe', $project),
            [
                'deliveries' => [
                    [
                        'delivery_receipt_date' => '2026-08-10',
                        'items' => [],
                    ],
                ],
            ],
        );

        $response->assertSessionHasErrors('deliveries.0.items');
        $this->assertSame(0, ProjectPpeDelivery::query()->where('project_id', $project->id)->count());
    }

    public function test_ineligible_project_status_is_still_blocked(): void
    {
        $project = $this->createProject(['status' => ProjectStatus::FOR_PAYMENT]);
        $gloves = $this->addPpeItem($project, 'Gloves', 20);

        $response = $this->actingAs($this->tc)->post(
            route('projects.implementation.ppe', $project),
            [
                'deliveries' => [
                    [
                        'delivery_receipt_date' => '2026-08-10',
                        'items' => [
                            $gloves->id => ['ppe_item_id' => $gloves->id, 'quantity' => 5],
                        ],
                    ],
                ],
            ],
        );

        $response->assertForbidden();
        $this->assertSame(0, ProjectPpeDelivery::query()->where('project_id', $project->id)->count());
    }
}
