<?php

/*
|--------------------------------------------------------------------------
| Import des données NADAM (CENAL / NAZ)
|--------------------------------------------------------------------------
|
| Fichiers GeoJSON déposés par SCP dans NADAM_DATA_SRC (disque « nadam »),
| traités par la commande `nadam:parse`.
|
*/

return [

    // id de la ligne automatic_network correspondant au réseau NADAM dans Radenviro
    'automatic_network_id' => (int) env('NADAM_AUTOMATIC_NETWORK_ID', 1),

    // valeur attendue de odl_network_name (contrôle de cohérence)
    'network_name' => 'NADAM',

    // seuls les fichiers dont le nom correspond sont traités (les autres sont ignorés)
    'file_pattern' => '/^NAZ-\d{8}-\d{6}\.json$/',

    // un fichier modifié il y a moins de N secondes est considéré en cours de transfert SCP
    'min_file_age_seconds' => (int) env('NADAM_MIN_FILE_AGE', 60),

    // conservation des fichiers traités (dossier processed/) avant purge
    'keep_processed_days' => (int) env('NADAM_KEEP_PROCESSED_DAYS', 7),

    // unité unique acceptée pour « value »
    'value_unit' => 'nSv/h',

    // average_type NAZ => average_type Radenviro ; tout autre type est rejeté
    'average_types' => [
        'Stündlich' => 'hourly',
    ],

    // taille des lots d'upsert
    'chunk_size' => 500,
];
