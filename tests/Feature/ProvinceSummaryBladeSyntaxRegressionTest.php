<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProvinceSummaryBladeSyntaxRegressionTest extends TestCase
{
    #[Test]
    public function province_summary_page_header_uses_valid_blade_component_attributes(): void
    {
        $view = file_get_contents(
            resource_path(
                'views/projects/province-summary.blade.php'
            )
        );

        $this->assertStringContainsString(
            ':title="$province->name . \' Province Summary\'"',
            $view
        );

        $this->assertStringContainsString(
            'description="{{ $province->name }} Project Summary',
            $view
        );

        $this->assertStringNotContainsString(
            ':description="$province->name . \' Project Summary',
            $view
        );
    }
}
