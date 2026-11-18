<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

try {
    $appEnvironment = getenv('APP_ENV');
    if ($appEnvironment === false) {
        $envPath = dirname(__DIR__) . '/.env';
        $environment = is_file($envPath)
            ? parse_ini_file($envPath, false, INI_SCANNER_RAW)
            : [];
        if ($environment === false) {
            throw new RuntimeException('Unable to parse the .env file.');
        }
        $appEnvironment = (string) ($environment['APP_ENV'] ?? 'local');
    }

    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
    $secureCookie = $isHttps || strtolower($appEnvironment) === 'production';

    if (ini_set('session.use_strict_mode', '1') === false) {
        throw new RuntimeException('Unable to enable strict session mode.');
    }

    if (!session_set_cookie_params([
        'httponly' => true,
        'secure' => $secureCookie,
        'samesite' => 'Lax',
        'path' => '/',
    ])) {
        throw new RuntimeException('Unable to configure session cookies.');
    }

    if (session_status() !== PHP_SESSION_ACTIVE && !session_start()) {
        throw new RuntimeException('Unable to start the session.');
    }

    (new App\Bootstrap\Application())->run();
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred.',
    ], JSON_THROW_ON_ERROR);
}
