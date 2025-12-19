<?php
declare(strict_types=1);

/**
 * Simple PDO connection helper.
 *
 * Configure via environment variables:
 * - MANGO_DB_HOST (default: 127.0.0.1)
 * - MANGO_DB_NAME (default: Mango_db)
 * - MANGO_DB_USER (default: root)
 * - MANGO_DB_PASS (default: empty)
 */
function mango_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('MANGO_DB_HOST') ?: '127.0.0.1';
    $db   = getenv('MANGO_DB_NAME') ?: 'Mango_db';
    $user = getenv('MANGO_DB_USER') ?: 'root';
    $pass = getenv('MANGO_DB_PASS') ?: '';

    $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}
