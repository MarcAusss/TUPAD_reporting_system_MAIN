<?php

namespace Tests\Feature;

use Tests\TestCase;

class SystemLogoConsistencyTest extends TestCase
{
    public function test_internal_system_identity_uses_main_logo_asset_consistently(): void
    {
        $paths = [
            resource_path('views/layouts/app.blade.php'),
            resource_path('views/layouts/error.blade.php'),
            resource_path('views/auth/login.blade.php'),
            resource_path('views/auth/required-password-change.blade.php'),
            resource_path('views/executive-dashboard/presentation.blade.php'),
        ];

        foreach ($paths as $path) {
            $source = file_get_contents($path);

            $this->assertStringContainsString("images/mainlogo.jpg", $source, basename($path).' must use the official system logo.');
            $this->assertStringContainsString('object-contain', $source, basename($path).' must fit the portrait logo without cropping.');
        }
    }

    public function test_executive_presentation_no_longer_uses_generic_four_square_brand_mark(): void
    {
        $source = file_get_contents(resource_path('views/executive-dashboard/presentation.blade.php'));

        $this->assertStringNotContainsString('grid-cols-2 gap-1 rounded-xl bg-[#063b86]', $source);
        $this->assertStringNotContainsString('<span class="rounded-sm bg-white"></span>', $source);
    }

    public function test_login_no_longer_uses_letter_t_placeholder_logo(): void
    {
        $source = file_get_contents(resource_path('views/auth/login.blade.php'));

        $this->assertStringNotContainsString('bg-slate-900 text-xl font-bold text-white', $source);
    }
}
