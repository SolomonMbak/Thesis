<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Validator;
use App\Data\SchoolRepository;
use App\Data\SettingsRepository;
use App\Services\AccountMail;
use App\Services\Environment;

/**
 * Platform administration (BUILD_SPEC section 2): the list of schools,
 * suspending and reactivating a school, and platform settings (name,
 * sign-up, job concurrency, how runs start, email). A platform admin does
 * not see inside a school unless they are also a member of it.
 */
final class PlatformController extends Controller
{
    private const LABELS = [
        'platform_name' => 'Platform name',
        'signup_open' => 'Public sign-up',
        'max_concurrent_jobs' => 'Maximum runs at the same time',
        'launch_mode' => 'How timetable runs are started',
        'php_cli_path' => 'PHP command line path',
        'smtp_host' => 'SMTP host',
        'smtp_port' => 'SMTP port',
        'smtp_encryption' => 'Encryption',
        'smtp_username' => 'SMTP user name',
        'smtp_password' => 'SMTP password',
        'smtp_from_email' => 'From address',
        'smtp_from_name' => 'From name',
    ];

    public function schools(): void
    {
        $this->render('platform/schools', [
            'title' => 'Schools',
            'schools' => (new SchoolRepository($this->app->db()))->listForPlatform(),
        ]);
    }

    public function setStatus(): void
    {
        $repo = new SchoolRepository($this->app->db());
        $school = $repo->find((int) $this->app->request->input('id', 0));
        $status = (string) $this->app->request->input('status', '');
        if ($school === null || !in_array($status, ['active', 'suspended'], true)) {
            $this->app->flash->error('That school does not exist.');
            $this->redirect('platform/schools');
        }
        $repo->setStatus((int) $school['id'], $status);
        $this->app->audit()->log($this->app->auth()->id(), 'platform.school_' . $status, 'school', (int) $school['id'], [], $this->app->request->ip());
        $this->app->flash->success($status === 'suspended'
            ? "{$school['name']} is suspended. Its members cannot open it and its public page shows a neutral message."
            : "{$school['name']} is active again.");
        $this->redirect('platform/schools');
    }

    public function settings(array $errors = [], array $old = []): void
    {
        $settings = new SettingsRepository($this->app->db());
        $values = [];
        foreach (array_keys(self::LABELS) as $key) {
            $values[$key] = $old[$key] ?? (string) $settings->get($key, '');
        }
        $this->render('platform/settings', [
            'title' => 'Platform settings',
            'values' => $values,
            'labels' => self::LABELS,
            'errors' => $errors,
            'canExec' => Environment::canExec(),
            'guessedCli' => Environment::guessPhpCli(),
            'passwordSet' => (string) $settings->get('smtp_password', '') !== '',
        ]);
    }

    public function saveSettings(): void
    {
        $in = [];
        foreach (array_keys(self::LABELS) as $key) {
            $in[$key] = trim((string) $this->app->request->input($key, ''));
        }
        $in['signup_open'] = $in['signup_open'] === '1' ? '1' : '0';
        $errors = Validator::validate($in, [
            'platform_name' => 'required|maxlen:60',
            'max_concurrent_jobs' => 'required|int|min:1|max:16',
            'launch_mode' => 'required|in:exec,queue',
            'php_cli_path' => 'maxlen:255',
            'smtp_host' => 'maxlen:255',
            'smtp_port' => 'int|min:1|max:65535',
            'smtp_encryption' => 'in:tls,ssl,none',
            'smtp_username' => 'maxlen:255',
            'smtp_from_email' => 'email|maxlen:120',
            'smtp_from_name' => 'maxlen:80',
        ], self::LABELS);
        if ($in['php_cli_path'] !== '' && preg_match('/[;&|`$<>\n"\']/', $in['php_cli_path'])) {
            $errors['php_cli_path'] = 'The PHP path may not contain shell characters such as ; & | $ or quotes.';
        }
        if ($errors) {
            http_response_code(422);
            $this->settings($errors, $in);
            return;
        }
        $settings = new SettingsRepository($this->app->db());
        foreach ($in as $key => $value) {
            // A blank password box keeps the saved password (it is never shown back).
            if ($key === 'smtp_password' && $value === '') {
                continue;
            }
            $settings->set($key, $value);
        }
        $logged = $in;
        unset($logged['smtp_password']);
        $this->app->audit()->log($this->app->auth()->id(), 'platform.settings', null, null, $logged, $this->app->request->ip());
        $this->app->flash->success('Platform settings saved.');
        $this->redirect('platform/settings');
    }

    public function testEmail(): void
    {
        $email = (string) $this->app->auth()->user()['email'];
        if ((new AccountMail($this->app->db()))->sendTest($email)) {
            $this->app->flash->success("A test email was sent to {$email}. If it does not arrive within a few minutes, check the spam folder and the SMTP settings."
                . (\App\Core\Config::get('app_env') === 'development' ? ' (Development mode: it was written to storage/logs/mail.log instead.)' : ''));
        } else {
            $this->app->flash->error('The test email could not be sent. Check the SMTP settings and storage/logs for the error.');
        }
        $this->redirect('platform/settings');
    }
}
