<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Validator;
use App\Data\SettingsRepository;
use App\Engine\GaParams;
use App\Services\Environment;
use App\Services\GaDefaults;
use App\Services\PeriodGenerator;

/**
 * Menu area 2: constraint weights, default GA parameters and (for admins)
 * institution, day times and how background runs are started.
 */
final class SettingsController extends Controller
{
    private const GENERAL_LABELS = [
        'institution_name' => 'Institution name',
        'department_name' => 'Department name',
        'day_start' => 'Day starts at',
        'day_end' => 'Day ends at',
        'period_minutes' => 'Period length (minutes)',
        'launch_mode' => 'How timetable runs are started',
        'php_cli_path' => 'PHP command line path',
    ];

    public function index(array $errors = [], array $old = []): void
    {
        $settings = new SettingsRepository($this->app->db());
        $weights = $this->app->db()->query('SELECT * FROM constraint_weights ORDER BY constraint_type, code')->fetchAll();
        $general = [];
        foreach (array_keys(self::GENERAL_LABELS) as $key) {
            $general[$key] = $settings->get($key, '');
        }
        $this->render('settings/index', [
            'title' => 'Constraints and settings',
            'weights' => $weights,
            'ga' => ($old['ga'] ?? null) ?: GaDefaults::load($settings),
            'general' => ($old['general'] ?? null) ?: $general,
            'labels' => self::GENERAL_LABELS,
            'errors' => $errors,
            'canExec' => Environment::canExec(),
            'guessedCli' => Environment::guessPhpCli(),
        ]);
    }

    public function saveWeights(): void
    {
        $input = (array) $this->app->request->input('weight', []);
        $pdo = $this->app->db();
        $rows = $pdo->query('SELECT code, description, weight FROM constraint_weights')->fetchAll();
        $errors = [];
        $changes = [];
        foreach ($rows as $row) {
            $value = trim((string) ($input[$row['code']] ?? ''));
            $min = $row['code'] === 'H' ? 1 : 0;
            if (!preg_match('/^\d{1,7}$/', $value) || (int) $value < $min) {
                $errors['weight_' . $row['code']] = "Weight for {$row['code']} must be a whole number from {$min} to 9999999.";
                continue;
            }
            if ((int) $value !== (int) $row['weight']) {
                $changes[$row['code']] = ['from' => (int) $row['weight'], 'to' => (int) $value];
            }
        }
        if ($errors) {
            http_response_code(422);
            $this->index($errors);
            return;
        }
        $stmt = $pdo->prepare('UPDATE constraint_weights SET weight = ? WHERE code = ?');
        foreach ($changes as $code => $change) {
            $stmt->execute([$change['to'], $code]);
        }
        $this->app->log('settings.weights', null, null, $changes);
        $this->app->flash->success($changes ? 'Constraint weights saved. New runs will use them.' : 'No weights were changed.');
        $this->redirect('settings');
    }

    public function saveGa(): void
    {
        $input = array_intersect_key($this->app->request->allInput(), GaDefaults::SETTING_KEYS);
        $errors = GaParams::validate($input);
        if ($errors) {
            http_response_code(422);
            $this->index($errors, ['ga' => $input]);
            return;
        }
        GaDefaults::save(new SettingsRepository($this->app->db()), $input);
        $this->app->log('settings.ga_defaults', null, null, $input);
        $this->app->flash->success('Default GA parameters saved.');
        $this->redirect('settings');
    }

    public function saveGeneral(): void
    {
        $this->requireAdmin();
        $input = array_map('trim', array_map('strval', array_intersect_key(
            $this->app->request->allInput(),
            self::GENERAL_LABELS
        )));
        $input += array_fill_keys(array_keys(self::GENERAL_LABELS), '');
        $errors = Validator::validate($input, [
            'institution_name' => 'required|maxlen:150',
            'department_name' => 'required|maxlen:150',
            'day_start' => 'required|time',
            'day_end' => 'required|time',
            'period_minutes' => 'required|int|min:15|max:240',
            'launch_mode' => 'required|in:exec,queue',
            'php_cli_path' => 'maxlen:255',
        ], self::GENERAL_LABELS);
        if (!$errors) {
            try {
                PeriodGenerator::dayPlan($input['day_start'], $input['day_end'], (int) $input['period_minutes']);
            } catch (\InvalidArgumentException $ex) {
                $errors['day_end'] = $ex->getMessage();
            }
        }
        if ($input['php_cli_path'] !== '' && preg_match('/[;&|`$<>\n"\']/', $input['php_cli_path'])) {
            $errors['php_cli_path'] = 'The PHP path may not contain shell characters such as ; & | $ or quotes.';
        }
        if ($errors) {
            http_response_code(422);
            $this->index($errors, ['general' => $input]);
            return;
        }

        $settings = new SettingsRepository($this->app->db());
        $timesChanged = $settings->get('day_start') !== $input['day_start']
            || $settings->get('day_end') !== $input['day_end']
            || $settings->get('period_minutes') !== $input['period_minutes'];
        foreach ($input as $key => $value) {
            $settings->set($key, $value);
        }
        $this->app->log('settings.general', null, null, $input);
        $this->app->flash->success('Settings saved.');
        if ($timesChanged) {
            $this->app->flash->info('The day times changed. Open Periods and generate the periods again to apply them.');
        }
        $this->redirect('settings');
    }
}
