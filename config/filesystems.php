<?php

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [

        'logo_etablissement' => [
            'driver' => 'local',
            'root' => storage_path('app/public/logo_etablissement'),
            'url' => env('APP_URL').'/storage/logo_etablissement',
            'visibility' => 'public',
        ],

        'users' => [
            'driver' => 'local',
            'root' => storage_path('app/public/users'),
            'url' => env('APP_URL').'/storage/users',
            'visibility' => 'public',
        ],

        // Justificatifs d'absence scannes (certificats medicaux, mots des
        // parents). Aligne sur les autres disques du projet, mais ce sont des
        // donnees sensibles : a basculer en disque prive servi par une route
        // authentifiee des que possible.
        'justificatifs' => [
            'driver' => 'local',
            'root' => storage_path('app/public/justificatifs'),
            'url' => env('APP_URL').'/storage/justificatifs',
            'visibility' => 'public',
        ],

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
