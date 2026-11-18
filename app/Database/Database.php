<?php

declare(strict_types=1);

namespace App\Database;

use InvalidArgumentException;
use PDO;

final class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $config = require dirname(__DIR__, 2) . '/config/database.php';
        $port = filter_var(
            $config['port'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 65535]]
        );

        foreach (['host', 'database', 'username'] as $key) {
            if (!isset($config[$key]) || $config[$key] === '') {
                throw new InvalidArgumentException("Database configuration '{$key}' is required.");
            }
        }

        if ($port === false) {
            throw new InvalidArgumentException('Database configuration port must be between 1 and 65535.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['host'],
            $port,
            $config['database']
        );

        self::$connection = new PDO($dsn, $config['username'], $config['password'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$connection;
    }
}
