<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Region V mapping foundation
    |--------------------------------------------------------------------------
    |
    | The existing location reference tables already store PSGC correspondence
    | codes in their `code` columns.  Do not create duplicate mapping tables.
    |
    */
    'region' => [
        'code' => '050000000',
        'name' => 'Bicol Region',
    ],

    'public_path' => 'geojson/bicol',

    'boundary_source' => [
        'provider' => 'faeldon/philippines-json-maps',
        'source_basis' => 'PSGC administrative boundaries, 31 December 2023',
        'resolution' => 'medres',
        'region_url' => 'https://raw.githubusercontent.com/faeldon/philippines-json-maps/master/2023/geojson/regions/medres/provdists-region-500000000.0.01.json',
        'municipality_url_pattern' => 'https://raw.githubusercontent.com/faeldon/philippines-json-maps/master/2023/geojson/provdists/medres/municities-provdist-%s.0.01.json',
        'barangay_url_pattern' => 'https://raw.githubusercontent.com/faeldon/philippines-json-maps/master/2023/geojson/municities/medres/bgysubmuns-municity-%s.0.01.json',
        'attribution' => 'Administrative boundary GeoJSON from faeldon/philippines-json-maps; source shapefiles use PSGC data and Philippine administrative boundaries. Validate against the application PSGC reference tables before use.',
    ],

    /*
    | The source filenames omit the leading zero used by the 9-digit PSGC
    | correspondence code.  `source_code` is therefore a file identifier only;
    | all application joins use the canonical `psgc_code` value.
    */
    'provinces' => [
        '050500000' => ['name' => 'Albay', 'slug' => 'albay', 'source_code' => '500500000'],
        '051600000' => ['name' => 'Camarines Norte', 'slug' => 'camarines-norte', 'source_code' => '501600000'],
        '051700000' => ['name' => 'Camarines Sur', 'slug' => 'camarines-sur', 'source_code' => '501700000'],
        '052000000' => ['name' => 'Catanduanes', 'slug' => 'catanduanes', 'source_code' => '502000000'],
        '054100000' => ['name' => 'Masbate', 'slug' => 'masbate', 'source_code' => '504100000'],
        '056200000' => ['name' => 'Sorsogon', 'slug' => 'sorsogon', 'source_code' => '506200000'],
    ],
];
