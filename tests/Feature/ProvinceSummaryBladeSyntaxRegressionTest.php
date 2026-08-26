<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProvinceSummaryBladeSyntaxRegressionTest extends TestCase
{
    #[Test]
    public function province_summary_page_header_uses_valid_computed_blade_component_attributes(): void
    {
        $view =
            file_get_contents(
                resource_path(
                    'views/projects/province-summary.blade.php'
                )
            );

        $this->assertStringContainsString(
            '$summaryPageTitle = $isSingleProjectSummary',
            $view
        );

        $this->assertStringContainsString(
            '$summaryPageDescription = $isSingleProjectSummary',
            $view
        );

        $this->assertStringContainsString(
            ':title="$summaryPageTitle"',
            $view
        );

        $this->assertStringContainsString(
            ':description="$summaryPageDescription"',
            $view
        );

        $this->assertStringNotContainsString(
            ':description="$province->name . \' Project Summary',
            $view
        );
    }
}
