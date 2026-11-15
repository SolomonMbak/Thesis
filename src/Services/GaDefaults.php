<?php
declare(strict_types=1);

namespace App\Services;

use App\Data\SettingsRepository;

/**
 * Default GA parameters stored in settings (keys ga_*), converted to and
 * from the ga_jobs column names used by GaParams.
 */
final class GaDefaults
{
    /** ga_jobs column => settings key */
    public const SETTING_KEYS = [
        'population_size' => 'ga_population',
        'max_generations' => 'ga_max_generations',
        'stall_generations' => 'ga_stall_generations',
        'tournament_size' => 'ga_tournament_size',
        'crossover_rate' => 'ga_crossover_rate',
        'mutation_rate' => 'ga_mutation_rate',
        'elite_count' => 'ga_elite_count',
        'greedy_share' => 'ga_greedy_share',
        'max_repair_moves' => 'ga_max_repair_moves',
        'log_every' => 'ga_log_every',
    ];

    /** Spec defaults (BUILD_SPEC 8.7), used if a setting is missing. */
    public const FALLBACK = [
        'population_size' => '100', 'max_generations' => '2000', 'stall_generations' => '100',
        'tournament_size' => '3', 'crossover_rate' => '0.8', 'mutation_rate' => '0.05',
        'elite_count' => '2', 'greedy_share' => '0.2', 'max_repair_moves' => '30', 'log_every' => '1',
    ];

    public static function load(SettingsRepository $settings): array
    {
        $values = [];
        foreach (self::SETTING_KEYS as $column => $key) {
            $values[$column] = $settings->get($key, self::FALLBACK[$column]);
        }
        return $values + ['repair_enabled' => 1];
    }

    public static function save(SettingsRepository $settings, array $values): void
    {
        foreach (self::SETTING_KEYS as $column => $key) {
            $settings->set($key, trim((string) $values[$column]));
        }
    }
}
