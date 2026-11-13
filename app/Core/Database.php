<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $host = self::requiredEnvironmentValue('DB_HOST');
        $port = self::requiredEnvironmentValue('DB_PORT');
        $database = self::requiredEnvironmentValue('DB_DATABASE');
        $username = self::requiredEnvironmentValue('DB_USERNAME');
        $password = self::environmentValue('DB_PASSWORD');

        if (!ctype_digit($port)) {
            throw new RuntimeException('DB_PORT must contain a numeric port.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $database
        );

        self::$connection = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$connection;
    }

    private static function requiredEnvironmentValue(string $name): string
    {
        $value = self::environmentValue($name);

        if ($value === '') {
            throw new RuntimeException(sprintf('The %s environment variable is required.', $name));
        }

        return $value;
    }

    private static function environmentValue(string $name): string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);

        return is_string($value) ? $value : '';
    }
}
