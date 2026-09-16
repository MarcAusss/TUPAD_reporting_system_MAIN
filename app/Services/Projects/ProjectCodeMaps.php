<?php

namespace App\Services\Projects;

use RuntimeException;

/**
 * Fixed DOLE Regional Office V TUPAD project-code abbreviations.
 *
 * These tables are system-controlled reference data: there is no
 * user-facing UI to add, edit, or select them. Municipality codes are
 * nested by province because a small number of municipality names repeat
 * across provinces with different assigned codes (e.g. "Bato" exists in
 * both Camarines Sur and Catanduanes; "San Fernando" exists in both
 * Camarines Sur and Masbate).
 */
class ProjectCodeMaps
{
    private const PROVINCE_CODES = [
        'Albay' => 'APO',
        'Camarines Norte' => 'CNPO',
        'Camarines Sur' => 'CSPO',
        'Catanduanes' => 'CPO',
        'Masbate' => 'MPO',
        'Sorsogon' => 'SPO',
    ];

    private const MUNICIPALITY_CODES = [
        'Albay' => [
            'Bacacay' => 'BAC',
            'Camalig' => 'CMG',
            'City of Legazpi' => 'LEGC',
            'City of Ligao' => 'LIGC',
            'City of Tabaco' => 'TABC',
            'Daraga' => 'DRG',
            'Guinobatan' => 'GNBTN',
            'Jovellar' => 'JOV',
            'Libon' => 'LIB',
            'Malilipot' => 'MLLPT',
            'Malinao' => 'MLNAO',
            'Manito' => 'MNTO',
            'Oas' => 'OAS',
            'Pio Duran' => 'PIO',
            'Polangui' => 'POL',
            'Rapu-Rapu' => 'RAPU',
            'Santo Domingo' => 'SDA',
            'Tiwi' => 'TIWI',
        ],

        'Camarines Norte' => [
            'Basud' => 'BSD',
            'Capalonga' => 'CAP',
            'Daet' => 'DET',
            'Jose Panganiban' => 'JPN',
            'Labo' => 'LAB',
            'Mercedes' => 'MER',
            'Paracale' => 'PAR',
            'San Lorenzo Ruiz' => 'SLR',
            'San Vicente' => 'SVT',
            'Santa Elena' => 'STE',
            'Talisay' => 'TLI',
            'Vinzons' => 'VNZ',
        ],

        'Camarines Sur' => [
            'Baao' => 'BAAO',
            'Balatan' => 'BLAT',
            'Bato' => 'BATO',
            'Bombon' => 'BMBO',
            'Buhi' => 'BUHI',
            'Bula' => 'BULA',
            'Cabusao' => 'CABU',
            'Calabanga' => 'CLAB',
            'Camaligan' => 'CMAL',
            'Canaman' => 'CANA',
            'Caramoan' => 'CARA',
            'City of Iriga' => 'IRGA',
            'City of Naga' => 'NAGA',
            'Del Gallego' => 'DGAL',
            'Gainza' => 'GAZA',
            'Garchitorena' => 'CARC',
            'Goa' => 'GOA',
            'Lagonoy' => 'LAGY',
            'Libmanan' => 'LBMA',
            'Lupi' => 'LUPI',
            'Magarao' => 'MGRO',
            'Milaor' => 'MILR',
            'Minalabac' => 'MNAL',
            'Nabua' => 'NBUA',
            'Ocampo' => 'OCMP',
            'Pamplona' => 'PMPL',
            'Pasacao' => 'PSCO',
            'Pili' => 'PILI',
            'Presentacion' => 'PRST',
            'Ragay' => 'RAGY',
            'Sagñay' => 'SAGY',
            'San Fernando' => 'SFER',
            'San Jose' => 'SJOS',
            'Sipocot' => 'SIPO',
            'Siruma' => 'SRUM',
            'Tigaon' => 'TIGN',
            'Tinambac' => 'TINB',
        ],

        'Catanduanes' => [
            'Bagamanoc' => 'BAG',
            'Baras' => 'BAR',
            'Bato' => 'BAT',
            'Caramoran' => 'CAR',
            'Gigmoto' => 'GIG',
            'Pandan' => 'PAN',
            'Panganiban' => 'PANG',
            'San Andres' => 'SA',
            'San Miguel' => 'SM',
            'Viga' => 'VIG',
            'Virac' => 'VIR',
        ],

        'Masbate' => [
            'Aroroy' => 'ARY',
            'Baleno' => 'BAL',
            'Balud' => 'BUD',
            'Batuan' => 'BAT',
            'Cataingan' => 'CAT',
            'Cawayan' => 'CAW',
            'City of Masbate' => 'MAS',
            'Claveria' => 'CLV',
            'Dimasalang' => 'DIM',
            'Esperanza' => 'ESP',
            'Mandaon' => 'MND',
            'Milagros' => 'MIL',
            'Mobo' => 'MOB',
            'Monreal' => 'MON',
            'Palanas' => 'PAL',
            'Pio v. Corpuz' => 'PVC',
            'Placer' => 'PLC',
            'San Fernando' => 'SFR',
            'San Jacinto' => 'SJ',
            'San Pascual' => 'SP',
            'Uson' => 'USN',
        ],

        'Sorsogon' => [
            'Barcelona' => 'BRC',
            'Bulan' => 'BLN',
            'Bulusan' => 'BLS',
            'Casiguran' => 'CSG',
            'Castilla' => 'CST',
            'City of Sorsogon' => 'SRC',
            'Donsol' => 'DNL',
            'Gubat' => 'GBT',
            'Irosin' => 'IRS',
            'Juban' => 'JBN',
            'Magallanes' => 'MGL',
            'Matnog' => 'MTG',
            'Pilar' => 'PLR',
            'Prieto Diaz' => 'PRD',
            'Santa Magdalena' => 'STM',
        ],
    ];

    public static function provinceCode(string $provinceName): string
    {
        return self::PROVINCE_CODES[$provinceName]
            ?? throw new RuntimeException(
                "No Project Code province abbreviation is configured for \"{$provinceName}\"."
            );
    }

    public static function municipalityCode(
        string $provinceName,
        string $municipalityName,
    ): string {
        return self::MUNICIPALITY_CODES[$provinceName][$municipalityName]
            ?? throw new RuntimeException(
                "No Project Code municipality abbreviation is configured for \"{$municipalityName}\" in \"{$provinceName}\"."
            );
    }
}
