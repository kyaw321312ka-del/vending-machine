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

$getValue = static function (string $key, ?string $default = null) use ($env): ?string {
    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }

    if (isset($_ENV[$key])) {
        return (string) $_ENV[$key];
    }

    if (isset($_SERVER[$key])) {
        return (string) $_SERVER[$key];
    }

    return array_key_exists($key, $env) ? (string) $env[$key] : $default;
};

return [
    'host' => $getValue('DB_HOST', '127.0.0.1'),
    'port' => $getValue('DB_PORT', '3306'),
    'database' => $getValue('DB_DATABASE'),
    'username' => $getValue('DB_USERNAME'),
    'password' => $getValue('DB_PASSWORD', ''),
];
