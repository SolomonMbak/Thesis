<?php
/**
 * The search (GA) parameter fields with plain-English hints, used by School
 * settings (defaults) and Generate (one run).
 * @var array $values
 * @var array $errors
 */
use App\Engine\GaParams;

$hints = [
    'population_size' => 'How many candidate timetables are improved together. More can find better timetables but takes longer. Usually 100.',
    'max_generations' => 'The most rounds the search may run. It usually stops much earlier, once it stops improving.',
    'stall_generations' => 'Once there are no clashes, stop after this many rounds without any improvement.',
    'tournament_size' => 'How many timetables compete to become a parent. Bigger favours the best ones more strongly. Usually 3.',
    'crossover_rate' => 'Chance (0 to 1) that two parent timetables are mixed. Usually 0.8.',
    'mutation_rate' => 'Chance (0 to 1) that each class is moved at random, which keeps the search exploring. Usually 0.05.',
    'elite_count' => 'How many of the best timetables pass unchanged to the next round, so the best never gets worse. Usually 2.',
    'greedy_share' => 'Part (0 to 1) of the first timetables that are built by placing classes in the first free slot. Usually 0.2.',
    'max_repair_moves' => 'How many clashing classes the repair step may move in each timetable. Usually 30.',
    'log_every' => 'Save a progress point every N rounds. Use 5 on hosts that limit long-running processes.',
];
?>
<div class="form-grid">
  <?php foreach (GaParams::FIELDS as $key => [$label, $type, $min, $max]): ?>
    <?= $this->partial('partials/field', [
        'name' => $key, 'label' => $label, 'type' => 'number', 'value' => $values[$key] ?? '',
        'errors' => $errors, 'step' => $type === 'float' ? '0.001' : '1', 'min' => $min, 'max' => $max,
        'help' => $hints[$key] ?? null,
    ]) ?>
  <?php endforeach; ?>
</div>
