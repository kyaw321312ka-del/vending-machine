<?php

$rootPath = dirname(__DIR__);
$envPath = $rootPath . '/.env';
$env = [];

if (is_file($envPath)) {
    $env = parse_ini_file($envPath, false, INI_SCANNER_RAW);
    if ($env === false) {
        throw new RuntimeException('Unable to parse the .env file.');
    }
}

$secret = getenv('JWT_SECRET');
if ($secret === false && isset($_ENV['JWT_SECRET'])) {
    $secret = (string) $_ENV['JWT_SECRET'];
}
if ($secret === false && isset($_SERVER['JWT_SECRET'])) {
    $secret = (string) $_SERVER['JWT_SECRET'];
}
if ($secret === false) {
    $secret = (string) ($env['JWT_SECRET'] ?? '');
}

return [
    'secret' => $secret,
    'issuer' => 'vending-machine',
];
