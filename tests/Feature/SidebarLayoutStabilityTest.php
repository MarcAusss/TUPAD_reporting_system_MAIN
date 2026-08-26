<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SidebarLayoutStabilityTest extends TestCase
{
    #[Test]
    public function desktop_sidebar_has_explicit_stable_width_and_full_width_children(): void
    {
        $layout =
            file_get_contents(
                resource_path(
                    'views/layouts/app.blade.php'
                )
            );

        $css =
            file_get_contents(
                resource_path(
                    'css/app.css'
                )
            );

        $this->assertStringContainsString(
            'tupad-sidebar-header',
            $layout
        );

        $this->assertStringContainsString(
            'tupad-sidebar-scroll',
            $layout
        );

        $this->assertStringContainsString(
            'tupad-sidebar-footer',
            $layout
        );

        $this->assertStringContainsString(
            'tupad-main-shell',
            $layout
        );

        $this->assertStringContainsString(
            'width: 252px !important;',
            $css
        );

        $this->assertStringContainsString(
            'align-items: stretch !important;',
            $css
        );

        $this->assertStringContainsString(
            'overflow-x: hidden !important;',
            $css
        );

        $this->assertStringContainsString(
            'padding-left: 252px !important;',
            $css
        );
    }
}
