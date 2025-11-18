<?php

use Illuminate\Support\Str;

return [

    'default' => env('DB_CONNECTION', 'users_main'),

    'connections' => [

        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE_USERS_MAIN'), // ← same DB
            'username' => env('DB_USERNAME_MAIN'),
            'password' => env('DB_PASSWORD_MAIN'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ],


        'users_main' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST'),
            'port' => env('DB_PORT'),
            'database' => env('DB_DATABASE_USERS_MAIN'),
            'username' => env('DB_USERNAME_MAIN'),
            'password' => env('DB_PASSWORD_MAIN'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ],

        'wlka' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE_WLKA'),
            'username' => env('DB_USERNAME_WLKA'),
            'password' => env('DB_PASSWORD_WLKA'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ],

    ],

    'migrations' => [
        'table' => 'migrations',
    ],

    'redis' => [
        'client' => env('REDIS_CLIENT', 'phpredis'),

        'default' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],
    ],

];
