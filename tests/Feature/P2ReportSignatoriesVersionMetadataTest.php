<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Exports\PdfTableWriter;
use App\Services\Exports\XlsxTableWriter;
use App\Services\Reports\ReportDocumentControlService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class P2ReportSignatoriesVersionMetadataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tupad_reports.document.version' => '2.4',
            'tupad_reports.document.revision' => '7',
            'tupad_reports.document.classification' => 'Internal Test Report',
            'tupad_reports.document.reference_prefix' => 'DOLE-RO5-TEST',
            'tupad_reports.signatories.prepared_by' => [
                'label' => 'Prepared by',
                'name' => 'Prepared Officer',
                'position' => 'Program Staff',
                'office' => 'TSSD',
            ],
            'tupad_reports.signatories.reviewed_by' => [
                'label' => 'Reviewed by',
                'name' => 'Review Officer',
                'position' => 'Reviewer',
                'office' => 'TSSD',
            ],
            'tupad_reports.signatories.approved_by' => [
                'label' => 'Approved by',
                'name' => 'Approving Officer',
                'position' => 'Authorized Signatory',
                'office' => 'DOLE RO V',
            ],
        ]);
    }

    public function test_document_control_service_applies_version_reference_generator_and_configured_signatories(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
            'name' => 'Report Administrator',
        ]);

        $report = app(ReportDocumentControlService::class)->apply(
            $this->sampleReport(),
            $admin,
        );

        $this->assertSame('2.4', $report['document_control']['version']);
        $this->assertSame('7', $report['document_control']['revision']);
        $this->assertSame('v2.4 · Rev 7', $report['document_control']['version_label']);
        $this->assertSame('Report Administrator', $report['document_control']['generated_by']);
        $this->assertSame('Internal Test Report', $report['document_control']['classification']);
        $this->assertStringStartsWith('DOLE-RO5-TEST-', $report['document_control']['reference']);
        $this->assertSame('Prepared Officer', $report['signatories'][0]['name']);
        $this->assertSame('Review Officer', $report['signatories'][1]['name']);
        $this->assertSame('Approving Officer', $report['signatories'][2]['name']);
    }

    public function test_general_and_periodic_print_outputs_show_document_control_and_signatory_blocks(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
            'name' => 'Report Administrator',
        ]);

        $this->actingAs($admin)
            ->get(route('reports.print', [
                'report_type' => 'physical_financial',
                'group_by' => 'overall',
            ]))
            ->assertOk()
            ->assertSee('v2.4 · Rev 7')
            ->assertSee('Report Administrator')
            ->assertSee('Prepared by')
            ->assertSee('Prepared Officer')
            ->assertSee('Reviewed by')
            ->assertSee('Review Officer')
            ->assertSee('Approved by')
            ->assertSee('Approving Officer')
            ->assertSee('Document Version')
            ->assertSee('Internal Test Report');

        $this->actingAs($admin)
            ->get(route('reports.periodic.print', [
                'form' => 'sprs',
                'fiscal_year' => 2026,
                'month' => 8,
            ]))
            ->assertOk()
            ->assertSee('v2.4 · Rev 7')
            ->assertSee('Report Administrator')
            ->assertSee('Prepared Officer')
            ->assertSee('Approving Officer');
    }

    public function test_pdf_and_excel_exports_embed_document_control_and_signatory_identity(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
            'name' => 'Report Administrator',
        ]);

        $report = app(ReportDocumentControlService::class)->apply(
            $this->sampleReport(),
            $admin,
        );

        $pdf = app(PdfTableWriter::class)->render($report);
        $this->assertStringContainsString('Prepared by', $pdf);
        $this->assertStringContainsString('Prepared Officer', $pdf);
        $this->assertStringContainsString('Version:', $pdf);
        $this->assertStringContainsString('Report Administrator', $pdf);

        $xlsxPath = app(XlsxTableWriter::class)->write($report);
        $xlsx = file_get_contents($xlsxPath);
        @unlink($xlsxPath);

        $this->assertIsString($xlsx);
        $this->assertStringContainsString('Document Reference', $xlsx);
        $this->assertStringContainsString('Document Version', $xlsx);
        $this->assertStringContainsString('Prepared Officer', $xlsx);
        $this->assertStringContainsString('Approving Officer', $xlsx);
        $this->assertStringContainsString('Report Administrator', $xlsx);
    }

    private function sampleReport(): array
    {
        return [
            'title' => 'Metadata Test Report',
            'official_title' => 'Metadata Test Report',
            'official_kicker' => 'TUPAD Reporting System',
            'official_code' => 'METADATA TEST REPORT',
            'official_period' => 'September 2026',
            'columns' => [
                [
                    'key' => 'label',
                    'label' => 'Label',
                    'format' => 'text',
                    'align' => 'left',
                ],
            ],
            'rows' => new Collection([
                ['label' => 'Sample row'],
            ]),
            'display_rows' => new Collection([
                ['label' => 'Sample row'],
            ]),
            'summary_cards' => [],
            'criteria' => ['Scope' => 'All records'],
            'warning' => null,
            'generated_at' => CarbonImmutable::parse(
                '2026-09-06 12:00:00',
                'Asia/Manila',
            ),
            'file_base_name' => 'metadata-test-report',
        ];
    }
}
