<?php
declare(strict_types=1);

namespace App\Data;

use PDO;

/**
 * Key/value application settings stored in the settings table.
 * Values are loaded once per request.
 */
final class SettingsRepository
{
    private ?array $cache = null;

    public function __construct(private PDO $pdo)
    {
    }

    public function get(string $name, ?string $default = null): ?string
    {
        return $this->all()[$name] ?? $default;
    }

    /** @return array<string,string> */
    public function all(): array
    {
        if ($this->cache === null) {
            $rows = $this->pdo->query('SELECT name, value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
            $this->cache = array_map('strval', $rows);
        }
        return $this->cache;
    }

    public function set(string $name, string $value): void
    {
        $this->pdo->prepare(
            'INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)'
        )->execute([$name, $value]);
        $this->cache = null;
    }
}
