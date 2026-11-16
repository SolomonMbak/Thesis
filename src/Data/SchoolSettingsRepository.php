<?php
declare(strict_types=1);

namespace App\Data;

use App\Core\SchoolContext;
use PDO;

/**
 * Per-school key/value settings (school_settings): day times, period length,
 * default GA parameters and small flags such as profile_saved_at.
 * Platform-wide keys stay in the settings table (SettingsRepository).
 */
final class SchoolSettingsRepository
{
    /** Values every new school starts with (BUILD_SPEC sections 1 and 8.7). */
    public const DEFAULTS = [
        'day_start' => '08:00',
        'day_end' => '18:00',
        'period_minutes' => '60',
        'ga_population' => '100',
        'ga_max_generations' => '2000',
        'ga_stall_generations' => '100',
        'ga_tournament_size' => '3',
        'ga_crossover_rate' => '0.8',
        'ga_mutation_rate' => '0.05',
        'ga_elite_count' => '2',
        'ga_greedy_share' => '0.2',
        'ga_max_repair_moves' => '30',
        'ga_log_every' => '1',
    ];

    private ?array $cache = null;

    public function __construct(private PDO $pdo, private SchoolContext $school)
    {
    }

    public function get(string $name, ?string $default = null): ?string
    {
        return $this->all()[$name] ?? $default ?? (self::DEFAULTS[$name] ?? null);
    }

    /** @return array<string,string> */
    public function all(): array
    {
        if ($this->cache === null) {
            $stmt = $this->pdo->prepare('SELECT name, value FROM school_settings WHERE school_id = ?');
            $stmt->execute([$this->school->id]);
            $this->cache = array_map('strval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));
        }
        return $this->cache;
    }

    public function set(string $name, string $value): void
    {
        $this->pdo->prepare(
            'INSERT INTO school_settings (school_id, name, value) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)'
        )->execute([$this->school->id, $name, $value]);
        $this->cache = null;
    }

    public function delete(string $name): void
    {
        $this->pdo->prepare('DELETE FROM school_settings WHERE school_id = ? AND name = ?')->execute([$this->school->id, $name]);
        $this->cache = null;
    }
}
