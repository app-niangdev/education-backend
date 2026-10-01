<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Diffuseur par defaut
    |--------------------------------------------------------------------------
    |
    | Reverb en production : le serveur WebSocket tourne sur la machine de
    | l'application, les echanges entre les familles et l'ecole ne transitent
    | par aucun tiers. « log » reste le defaut de secours, pour qu'un
    | environnement sans WebSocket configure n'echoue pas a l'envoi d'un
    | message : l'evenement part dans les journaux, le message est enregistre.
    |
    */

    'default' => env('BROADCAST_CONNECTION', 'log'),

    'connections' => [

        'reverb' => [
            'driver' => 'reverb',
            'key'    => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host'   => env('REVERB_HOST'),
                'port'   => env('REVERB_PORT', 443),
                'scheme' => env('REVERB_SCHEME', 'https'),
                'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
            ],
            'client_options' => [
                // Le serveur applicatif et Reverb se parlent en local : sans
                // borne, une panne de Reverb ferait pendre la requete HTTP du
                // tuteur qui envoie son message.
                'timeout' => 5,
            ],
        ],

        'pusher' => [
            'driver'  => 'pusher',
            'key'     => env('PUSHER_APP_KEY'),
            'secret'  => env('PUSHER_APP_SECRET'),
            'app_id'  => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'host'    => env('PUSHER_HOST') ?: 'api-'.env('PUSHER_APP_CLUSTER', 'mt1').'.pusher.com',
                'port'    => env('PUSHER_PORT', 443),
                'scheme'  => env('PUSHER_SCHEME', 'https'),
                'useTLS'  => env('PUSHER_SCHEME', 'https') === 'https',
            ],
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
