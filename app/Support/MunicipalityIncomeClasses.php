<?php

namespace App\Support;

/**
 * Municipal income classification reference.
 *
 * INITIAL is the baseline list supplied by the regional office (New Income
 * Class sheet). It seeds empty municipalities only; afterwards the Focal
 * account maintains the values from the Income Class management page.
 */
final class MunicipalityIncomeClasses
{
    /** @var list<string> */
    public const OPTIONS = [
        '1st Class',
        '2nd Class',
        '3rd Class',
        '4th Class',
        '5th Class',
        '6th Class',
        'Component City',
        'Independent Component City',
        'Highly Urbanized City',
    ];

    /**
     * Province => [municipality name as stored in `municipalities.name` => class].
     * Gainza (Camarines Sur) had no class in the source sheet and is left unset.
     *
     * @var array<string, array<string, string>>
     */
    public const INITIAL = [
        'Albay' => [
            'Bacacay' => '1st Class',
            'Malilipot' => '1st Class',
            'Malinao' => '2nd Class',
            'Santo Domingo' => '2nd Class',
            'City of Tabaco' => 'Component City',
            'Tiwi' => '1st Class',
            'Daraga' => '1st Class',
            'City of Legazpi' => 'Component City',
            'Manito' => '2nd Class',
            'Rapu-Rapu' => '2nd Class',
            'Camalig' => '1st Class',
            'Guinobatan' => '1st Class',
            'Jovellar' => '4th Class',
            'Libon' => '1st Class',
            'City of Ligao' => 'Component City',
            'Oas' => '1st Class',
            'Pio Duran' => '1st Class',
            'Polangui' => '1st Class',
        ],
        'Camarines Norte' => [
            'Capalonga' => '1st Class',
            'Jose Panganiban' => '1st Class',
            'Labo' => '1st Class',
            'Paracale' => '1st Class',
            'Santa Elena' => '1st Class',
            'Basud' => '1st Class',
            'Daet' => '1st Class',
            'Mercedes' => '1st Class',
            'San Vicente' => '4th Class',
            'San Lorenzo Ruiz' => '5th Class',
            'Talisay' => '4th Class',
            'Vinzons' => '2nd Class',
        ],
        'Camarines Sur' => [
            'Cabusao' => '5th Class',
            'Del Gallego' => '4th Class',
            'Lupi' => '3rd Class',
            'Ragay' => '1st Class',
            'Sipocot' => '5th Class',
            'Libmanan' => '1st Class',
            'Milaor' => '4th Class',
            'Minalabac' => '3rd Class',
            'Pamplona' => '4th Class',
            'Pasacao' => '3rd Class',
            'San Fernando' => '4th Class',
            'Bombon' => '5th Class',
            'Calabanga' => '1st Class',
            'Camaligan' => '5th Class',
            'Canaman' => '4th Class',
            'Magarao' => '4th Class',
            'City of Naga' => 'Component City',
            'Ocampo' => '3rd Class',
            'Pili' => '1st Class',
            'Caramoan' => '2nd Class',
            'Garchitorena' => '4th Class',
            'Goa' => '2nd Class',
            'Lagonoy' => '2nd Class',
            'Presentacion' => '4th Class',
            'Sagñay' => '4th Class',
            'San Jose' => '4th Class',
            'Siruma' => '4th Class',
            'Tigaon' => '3rd Class',
            'Tinambac' => '1st Class',
            'Baao' => '3rd Class',
            'Balatan' => '4th Class',
            'Bato' => '3rd Class',
            'Buhi' => '1st Class',
            'Bula' => '2nd Class',
            'Nabua' => '1st Class',
            'City of Iriga' => 'Component City',
        ],
        'Catanduanes' => [
            'Bagamanoc' => '4th Class',
            'Baras' => '4th Class',
            'Bato' => '4th Class',
            'Caramoran' => '2nd Class',
            'Gigmoto' => '4th Class',
            'Pandan' => '4th Class',
            'Panganiban' => '4th Class',
            'San Andres' => '2nd Class',
            'San Miguel' => '4th Class',
            'Viga' => '3rd Class',
            'Virac' => '1st Class',
        ],
        'Masbate' => [
            'Batuan' => '5th Class',
            'Claveria' => '3rd Class',
            'Monreal' => '4th Class',
            'San Fernando' => '5th Class',
            'San Jacinto' => '4th Class',
            'San Pascual' => '3rd Class',
            'Aroroy' => '1st Class',
            'Baleno' => '4th Class',
            'Balud' => '4th Class',
            'Mandaon' => '3rd Class',
            'City of Masbate' => 'Component City',
            'Milagros' => '1st Class',
            'Mobo' => '4th Class',
            'Cataingan' => '2nd Class',
            'Cawayan' => '2nd Class',
            'Dimasalang' => '4th Class',
            'Esperanza' => '5th Class',
            'Palanas' => '4th Class',
            'Pio v. Corpuz' => '4th Class',
            'Placer' => '2nd Class',
            'Uson' => '3rd Class',
        ],
        'Sorsogon' => [
            'Casiguran' => '3rd Class',
            'Castilla' => '1st Class',
            'Donsol' => '1st Class',
            'Magallanes' => '2nd Class',
            'Pilar' => '1st Class',
            'City of Sorsogon' => 'Component City',
            'Barcelona' => '4th Class',
            'Bulan' => '1st Class',
            'Bulusan' => '3rd Class',
            'Gubat' => '1st Class',
            'Irosin' => '1st Class',
            'Juban' => '2nd Class',
            'Matnog' => '1st Class',
            'Prieto Diaz' => '4th Class',
            'Santa Magdalena' => '4th Class',
        ],
    ];

    public static function initialFor(string $province, string $municipality): ?string
    {
        return self::INITIAL[$province][$municipality] ?? null;
    }

    /**
     * Fill municipalities that have no income class yet. Never overwrites a
     * value, so Focal edits survive re-running the migration or seeders.
     */
    public static function applyInitial(): int
    {
        $updated = 0;

        \App\Models\Municipality::query()
            ->with('province:id,name')
            ->whereNull('income_class')
            ->get()
            ->each(function (\App\Models\Municipality $municipality) use (&$updated): void {
                $class = self::initialFor((string) $municipality->province?->name, $municipality->name);

                if ($class === null) {
                    return;
                }

                $municipality->forceFill(['income_class' => $class])->saveQuietly();

                \App\Models\Project::query()
                    ->where('municipality_id', $municipality->id)
                    ->whereNull('income_class')
                    ->update(['income_class' => $class]);

                $updated++;
            });

        return $updated;
    }
}
