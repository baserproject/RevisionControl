<?php
// created by BcInstaller
return [
    'Datasources.default' => [
        'className' => 'Cake\\Database\\Connection',
        'driver' => 'Cake\\Database\\Driver\\Mysql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => '3306',
        'username' => env('DB_USER', 'root'),
        'password' => env('DB_PWD', 'root'),
        'database' => env('DB_NAME', 'revision_control'),
        'prefix' => '',
        'schema' => '',
        'persistent' => '',
        'encoding' => 'utf8mb4',
        'log' => filter_var(env('SQL_LOG', false), FILTER_VALIDATE_BOOLEAN)
    ],
    'Datasources.test' => [
        'className' => 'Cake\\Database\\Connection',
        'driver' => 'Cake\\Database\\Driver\\Mysql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => '3306',
        'username' => env('DB_USER', 'root'),
        'password' => env('DB_PWD', 'root'),
        'database' => env('DB_TEST_NAME', 'test_revision_control'),
        'prefix' => '',
        'schema' => '',
        'persistent' => '',
        'encoding' => 'utf8mb4',
        'log' => filter_var(env('SQL_LOG', false), FILTER_VALIDATE_BOOLEAN)
    ]
];
