<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Current-term congressional district representatives of the Bicol
     * Region. The focal account updates these names after each election.
     * Province / district are stored as the same labels ADL allocations use.
     */
    private const REPRESENTATIVES = [
        ['Albay', '1st District', 'Cielo Krisel Lagman'],
        ['Albay', '2nd District', 'Carlos A. Loria'],
        ['Albay', '3rd District', 'Adrian E. Salceda'],
        ['Camarines Norte', '1st District', 'Josefina B. Tallado'],
        ['Camarines Norte', '2nd District', 'Rosemarie C. Panotes'],
        ['Camarines Sur', '1st District', 'Tsuyoshi Anthony G. Horibata'],
        ['Camarines Sur', '2nd District', 'Vincenzo Renato Luigi R. Villafuerte'],
        ['Camarines Sur', '3rd District', 'Nelson S. Legacion'],
        ['Camarines Sur', '4th District', 'Arnulf Bryan Fuentebella'],
        ['Camarines Sur', '5th District', 'Miguel Luis R. Villafuerte'],
        ['Catanduanes', 'Lone District', 'Eulogio R. Rodriguez'],
        ['Masbate', '1st District', 'Antonio T. Kho'],
        ['Masbate', '2nd District', 'Olga T. Kho'],
        ['Masbate', '3rd District', 'Wilton T. Kho'],
        ['Sorsogon', '1st District', 'Marie Bernadette G. Escudero'],
        ['Sorsogon', '2nd District', 'Manuel Fortes Jr.'],
    ];

    public function up(): void
    {
        Schema::create('district_representatives', function (Blueprint $table): void {
            $table->id();
            $table->string('province', 100);
            $table->string('district', 50);
            $table->string('representative')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['province', 'district'],
                'district_representatives_province_district_unique'
            );
        });

        $now = now();

        DB::table('district_representatives')->insert(
            collect(self::REPRESENTATIVES)
                ->map(fn (array $row, int $index): array => [
                    'province' => $row[0],
                    'district' => $row[1],
                    'representative' => $row[2],
                    'sort_order' => $index + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all()
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('district_representatives');
    }
};
