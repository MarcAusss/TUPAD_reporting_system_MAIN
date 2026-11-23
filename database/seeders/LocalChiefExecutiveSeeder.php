<?php

namespace Database\Seeders;

use App\Models\Municipality;
use App\Models\Province;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Local Chief Executives of the Bicol Region: the Governor of each province
 * and the Mayor of each city / municipality, from the "Directory of Local
 * Chief Executives (LCEs) in the Bicol Region" sheet. Names are stored
 * without the "HON." honorific because the ADL allocation form prefixes them
 * with "Mayor" / "Governor".
 *
 *   php artisan db:seed --class=LocalChiefExecutiveSeeder   loads the whole directory (overwrites)
 *   php artisan db:seed                                       fills blank names only (Focal edits are kept)
 *
 * Focal keeps the names current after each election on the Mayors &
 * Governors page.
 */
final class LocalChiefExecutiveSeeder extends Seeder
{
    /** @var array<string, array{governor: string, mayors: array<string, string>}> */
    private const DIRECTORY = [
        'Albay' => [
            'governor' => 'Noel E. Rosal',
            'mayors' => [
                'Bacacay' => 'Daniel Jose R. Bombales',
                'Malilipot' => 'Cenon B. Volante',
                'Malinao' => 'Sheryl P. Capus-Bilo',
                'Sto. Domingo' => 'Nomar V. Banda',
                'Tabaco City' => 'Reynaldo B. Bragais',
                'Tiwi' => 'Jose Morel Climaco',
                'Daraga' => 'Victor U. Perete',
                'Legazpi City' => 'Hisham B. Ismail',
                'Manito' => 'Jerry D. Arizapa',
                'Rapu-Rapu' => 'Romel A. Galicia',
                'Camalig' => 'Carlos Irwin G. Baldo, Jr.',
                'Guinobatan' => 'Ann Y. Ongjoco',
                'Jovellar' => 'Jorem L. Arcangel',
                'Libon' => 'Markgregor Edward C. Sayson',
                'Ligao City' => 'Fernando V. Gonzalez',
                'Oas' => 'John Kenneth M. Trinidad',
                'Pioduran' => 'Evangeline C. Arandia',
                'Polangui' => 'Jesciel Richard G. Salceda',
            ],
        ],
        'Camarines Norte' => [
            'governor' => 'Ricarte S. Padilla',
            'mayors' => [
                'Capalonga' => 'Luz E. Ricasio',
                'Jose Panganiban' => 'Ariel M. Non',
                'Labo' => 'Severino H. Francisco Jr.',
                'Paracale' => 'Romeo Y. Moreno',
                'Santa Elena' => 'Bernardina E. Borja',
                'Basud' => 'Shunn Frederick Davoco',
                'Daet' => 'Rossano C. Valencia',
                'Mercedes' => 'Alexander Pajarillo',
                'San Vicente' => 'Mariano E. Palma',
                'San Lorenzo Ruiz' => 'Sheila A. Delos Santos',
                'Talisay' => 'Donovan A. Mancenido',
                'Vinzons' => 'Agnes D. Ang',
            ],
        ],
        'Camarines Sur' => [
            'governor' => 'Luis Raymund F. Villafuerte',
            'mayors' => [
                'Cabusao' => 'Jose Gil A. Aguilar',
                'Del Gallego' => 'Melanie A. Garcia',
                'Lupi' => 'Christopher V. Jacinto',
                'Ragay' => 'Benchie G. Horibata',
                'Sipocot' => 'Tomas A. Bocago',
                'Gainza' => 'Glenn Romeo M. Gontang',
                'Libmanan' => 'Edelson M. Marfil',
                'Milaor' => 'Rubilyn Euste-De Dios',
                'Minalabac' => 'James Colin D. Lizardo',
                'Pamplona' => 'Manuel O. Agustin',
                'Pasacao' => 'Niño A. Tayco',
                'San Fernando' => 'Betty Anne Kristine Danabar-Fulgentes',
                'Bombon' => 'James Paul A. Ranin',
                'Calabanga' => 'Philip B. Dumalasa',
                'Camaligan' => 'Diano SJ. Ibardaloza, Jr.',
                'Canaman' => 'Nelson M. Legaspi',
                'Magarao' => 'Atty. Ma. Maruja A. Señar',
                'Naga' => 'Maria Leonor Gerona Robredo',
                'Ocampo' => 'Ronald Allan J. Go',
                'Pili' => 'Marivic Solano',
                'Caramoan' => 'Marilyn Socorro H. Co',
                'Garchitorena' => 'Nelson Dianela Bueza',
                'Goa' => 'Raquel S. Lim',
                'Lagonoy' => 'Dyego Luiz H. Pilapil',
                'Presentacion' => 'Jimmy V. Deleña',
                'Sagñay' => 'John Vincent B. Fuentebella',
                'San Jose' => 'Jerold B. Peña',
                'Siruma' => 'Carina Ramos-Polinga',
                'Tigaon' => 'Eugene Adrian B. Fuentebella',
                'Tinambac' => 'Edward Albert P. Prades',
                'Baao' => 'Jeffrey S. Besinio',
                'Balatan' => 'Ar. Domingo T. Abliter',
                'Bato' => 'Enric Dancalan',
                'Buhi' => 'Rey P. Lacoste',
                'Bula' => 'Manuel A. Ibasco, Jr.',
                'Nabua' => 'Atty. Fernando D. Simbulan',
                'Iriga City' => 'Wilfredo Rex C. Oliva',
            ],
        ],
        'Catanduanes' => [
            'governor' => 'Patrick Alain T. Azanza, Ph.D.',
            'mayors' => [
                'Bagamanoc' => 'Vincent V. Villaluna',
                'Baras' => 'Jose Paolo P. Teves, III',
                'Bato' => 'Juan T. Rodulfo',
                'Caramoran' => 'Glenda V. Aguilar',
                'Gigmoto' => 'Vicente J. Tayam, Jr., C.E.',
                'Pandan' => 'Honesto C. Tabligan, II',
                'Panganiban' => 'Cesar J. Robles',
                'San Andres' => 'Aly T. Romano',
                'San Miguel' => 'Antonio T. Teves',
                'Viga' => 'Jennifer Tuplano',
                'Virac' => 'Atty. Sinforoso M. Sarmiento, Jr.',
            ],
        ],
        'Masbate' => [
            'governor' => 'Ricardo T. Kho',
            'mayors' => [
                'Batuan' => 'Marco M. Cam',
                'Claveria' => 'Karen M. Ballesteros',
                'Monreal' => 'Glenda L. Villahermosa',
                'San Fernando' => 'Maria Vida E. Bravo',
                'San Jacinto' => 'Francisco P. Altarejos, M.D.',
                'San Pascual' => 'Zacarina A. Lazaro',
                'Aroroy' => 'Arvin R. Virtucio',
                'Baleno' => 'Marites C. Dela Rosa',
                'Balud' => 'Rodolfo O. Estrella, Jr.',
                'Mandaon' => 'Kristine Salve E. Hao-Kho',
                'Masbate City' => 'Olga T. Kho',
                'Milagros' => 'Natividad Isabel R. Magbalon',
                'Mobo' => 'Raymund Osmundo N. Salvacion',
                'Cataingan' => 'Alvin C. Adoptante',
                'Cawayan' => 'Edgar S. Condor',
                'Dimasalang' => 'Atty. Michael Demph Du Naga',
                'Esperanza' => 'Jeffrey Justin T. Talisic',
                'Palanas' => 'Roscelle A. Eramiz',
                'Pio V. Corpuz' => 'Mark R. Antonio',
                'Placer' => 'Eusobio P. Dumoran, Jr.',
                'Uson' => 'Felipe U. Sanchez',
            ],
        ],
        'Sorsogon' => [
            'governor' => 'Edwin "Boboy" B. Hamor',
            'mayors' => [
                'Casiguran' => 'Maria Minez R. Hamor',
                'Castilla' => 'Isagani B. Mendoza',
                'Donsol' => 'Ralph Walter R. Lubiano',
                'Magallanes' => 'Maria Elena Ramona M. Ragragio',
                'Pilar' => 'Dennis A. Sy-Reyes',
                'Sorsogon City' => 'Ma. Ester E. Hamor',
                'Barcelona' => 'Atty. Cynthia G. Falcotelo-Fortes',
                'Bulan' => 'Romeo A. Gordola',
                'Bulusan' => 'Wennie Rafallo-Romano',
                'Gubat' => 'Ronnel U. Lim',
                'Irosin' => 'Catalina Grace B. Hamor-Yu',
                'Juban' => 'Rogel B. Fulleros',
                'Matnog' => 'Robert Lee Rodrigueza',
                'Prieto Diaz' => 'Romeo D. Domasian',
                'Sta. Magdalena' => 'Mark Jewery G. Lozano',
            ],
        ],
    ];

    public function run(bool $onlyBlanks = false): void
    {
        $governors = 0;
        $mayors = 0;
        $unmatched = [];

        foreach (self::DIRECTORY as $provinceName => $entry) {
            $province = Province::query()->where('name', $provinceName)->first();

            if ($province === null) {
                $unmatched[] = 'Province of '.$provinceName;

                continue;
            }

            if (! $onlyBlanks || blank($province->governor)) {
                $province->forceFill(['governor' => $entry['governor'], 'governor_updated_at' => now()])->save();
                $governors++;
            }

            $municipalities = Municipality::query()
                ->where('province_id', $province->id)
                ->get()
                ->keyBy(fn (Municipality $municipality): string => self::key($municipality->name));

            foreach ($entry['mayors'] as $town => $mayor) {
                $municipality = $municipalities->get(self::key($town));

                if ($municipality === null) {
                    $unmatched[] = $town.', '.$provinceName;

                    continue;
                }

                if (! $onlyBlanks || blank($municipality->mayor)) {
                    $municipality->forceFill(['mayor' => $mayor, 'mayor_updated_at' => now()])->save();
                    $mayors++;
                }
            }
        }

        $this->command?->info(sprintf('Local Chief Executives: %d governor(s) and %d mayor(s) %s.', $governors, $mayors, $onlyBlanks ? 'filled in' : 'loaded'));

        if ($unmatched !== []) {
            $this->command?->warn('Not found in the location references: '.implode('; ', $unmatched));
        }
    }

    /**
     * Matches the directory's town names to the references: "Tabaco City" =
     * "City of Tabaco", "Sto. Domingo" = "Santo Domingo", "Sagñay" = "Sagnay".
     */
    private static function key(string $name): string
    {
        $key = Str::lower(Str::ascii($name));
        $key = preg_replace('/^city of\s+|\s+city$/', '', $key) ?? $key;
        $key = preg_replace('/\bsto\.?\s*/', 'santo ', $key) ?? $key;
        $key = preg_replace('/\bsta\.?\s*/', 'santa ', $key) ?? $key;

        return preg_replace('/[^a-z]/', '', $key) ?? $key;
    }
}
