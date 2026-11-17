<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Validator;
use App\Data\JobRepository;
use App\Data\MemberRepository;
use App\Data\SchoolRepository;
use App\Data\SchoolSettingsRepository;
use App\Data\TimeslotRepository;
use App\Engine\GaParams;
use App\Services\GaDefaults;
use App\Services\PeriodGenerator;

/**
 * The school itself: creating another school, switching between schools,
 * the profile and teaching week (Setup Guide step 1), the logo, school
 * settings (public link, default GA parameters), transferring ownership and
 * deleting the school.
 */
final class SchoolController extends Controller
{
    private const LOGO_MAX_BYTES = 512 * 1024;
    private const LOGO_TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    private const PROFILE_LABELS = [
        'name' => 'School name', 'school_type' => 'Type of school', 'department_label' => 'Department (optional)',
        'country' => 'Country', 'timezone' => 'Time zone', 'day_start' => 'Day starts at', 'day_end' => 'Day ends at',
        'period_minutes' => 'Period length (minutes)',
    ];

    /** Create another school (signed-in users, also those in no school yet). */
    public function showNew(array $errors = [], array $old = []): void
    {
        $this->render('schools/new', [
            'title' => 'Create a school',
            'errors' => $errors,
            'old' => $old + ['school_name' => '', 'school_type' => 'university', 'department_label' => ''],
            'types' => SchoolRepository::TYPES,
        ]);
    }

    public function create(): void
    {
        $in = array_map(fn ($k) => trim((string) $this->app->request->input($k, '')), ['school_name' => 'school_name',
            'school_type' => 'school_type', 'department_label' => 'department_label']);
        $errors = Validator::validate($in, [
            'school_name' => 'required|maxlen:150',
            'school_type' => 'required|in:' . implode(',', array_keys(SchoolRepository::TYPES)),
            'department_label' => 'maxlen:150',
        ], ['school_name' => 'School name', 'school_type' => 'Type of school', 'department_label' => 'Department']);
        if ($errors) {
            http_response_code(422);
            $this->showNew($errors, $in);
            return;
        }
        $id = (new SchoolRepository($this->app->db()))->create([
            'name' => $in['school_name'], 'school_type' => $in['school_type'], 'department_label' => $in['department_label'],
        ], (int) $this->app->auth()->id());
        $this->app->switchSchool($id);
        $this->app->log('school.create', 'school', $id);
        $this->app->flash->success("{$in['school_name']} is ready. Follow the Setup Guide to build its first timetable.");
        $this->redirect('setup');
    }

    /** Switch the current school (top bar). Only schools the user belongs to. */
    public function switch(): void
    {
        if (!$this->app->switchSchool((int) $this->app->request->input('school_id', 0))) {
            throw new HttpException(404, 'That school does not exist or you are not a member of it.');
        }
        $this->app->flash->success('You are now working in ' . $this->school()->name() . '.');
        $this->redirect('dashboard');
    }

    /** School profile and teaching week, with the period grid (Setup Guide step 1). */
    public function profile(array $errors = [], array $old = []): void
    {
        $school = $this->school();
        $settings = new SchoolSettingsRepository($this->app->db(), $school);
        $values = $old ?: [
            'name' => $school->school['name'], 'school_type' => $school->school['school_type'],
            'department_label' => (string) $school->school['department_label'], 'country' => $school->school['country'],
            'timezone' => $school->school['timezone'], 'day_start' => $settings->get('day_start'),
            'day_end' => $settings->get('day_end'), 'period_minutes' => $settings->get('period_minutes'),
        ];
        $this->render('schools/profile', [
            'title' => 'School profile and periods',
            'values' => $values,
            'errors' => $errors,
            'labels' => self::PROFILE_LABELS,
            'types' => SchoolRepository::TYPES,
            'canEdit' => $school->can('admin'),
            'grid' => (new TimeslotRepository($this->app->db(), $school))->grid(),
            'profileSaved' => $settings->get('profile_saved_at', '') !== '',
            'logoUrl' => $school->school['logo_path'] ? url('school/logo', ['s' => $school->slug(), 'v' => substr(md5((string) $school->school['logo_path']), 0, 8)]) : null,
        ]);
    }

    public function saveProfile(): void
    {
        $school = $this->school();
        $in = [];
        foreach (array_keys(self::PROFILE_LABELS) as $key) {
            $in[$key] = trim((string) $this->app->request->input($key, ''));
        }
        $errors = Validator::validate($in, [
            'name' => 'required|maxlen:150',
            'school_type' => 'required|in:' . implode(',', array_keys(SchoolRepository::TYPES)),
            'department_label' => 'maxlen:150',
            'country' => 'required|maxlen:60',
            'timezone' => 'required|maxlen:40',
            'day_start' => 'required|time',
            'day_end' => 'required|time',
            'period_minutes' => 'required|int|min:15|max:240',
        ], self::PROFILE_LABELS);
        if (!isset($errors['timezone']) && !in_array($in['timezone'], \DateTimeZone::listIdentifiers(), true)) {
            $errors['timezone'] = 'Choose a time zone from the list, for example Africa/Lagos.';
        }
        if (!$errors) {
            try {
                PeriodGenerator::dayPlan($in['day_start'], $in['day_end'], (int) $in['period_minutes']);
            } catch (\InvalidArgumentException $ex) {
                $errors['day_end'] = $ex->getMessage();
            }
        }
        if ($errors) {
            http_response_code(422);
            $this->profile($errors, $in);
            return;
        }

        $settings = new SchoolSettingsRepository($this->app->db(), $school);
        $timesChanged = $settings->get('day_start') !== $in['day_start'] || $settings->get('day_end') !== $in['day_end']
            || $settings->get('period_minutes') !== $in['period_minutes'];
        if ($timesChanged) {
            try {
                $result = (new PeriodGenerator($this->app->db(), $school))->generate($in['day_start'], $in['day_end'], (int) $in['period_minutes']);
                $this->app->flash->info("The teaching week was rebuilt: {$result['created']} periods added, {$result['removed']} removed.");
            } catch (\RuntimeException $ex) {
                http_response_code(422);
                $this->profile(['day_end' => $ex->getMessage()], $in);
                return;
            }
            foreach (['day_start', 'day_end', 'period_minutes'] as $key) {
                $settings->set($key, $in[$key]);
            }
        }
        (new SchoolRepository($this->app->db()))->updateProfile($school->id, $in);
        $settings->set('profile_saved_at', date('Y-m-d H:i:s'));
        $this->app->log('school.profile', 'school', $school->id, $in);
        $this->app->flash->success('School profile saved.');
        $this->redirect('school/profile');
    }

    /** Upload or replace the logo. Stored in storage/uploads/{school_id}/, never in the web root. */
    public function uploadLogo(): void
    {
        $school = $this->school();
        $file = $this->app->request->file('logo');
        $problem = $this->checkLogo($file);
        if ($problem !== null) {
            $this->app->flash->error($problem);
            $this->redirect('school/profile');
        }
        $extension = self::LOGO_TYPES[(new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name'])];
        $dir = SchoolRepository::uploadDir($school->id);
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            $this->app->flash->error('The logo could not be saved. Ask the platform administrator to make storage/ writable.');
            $this->redirect('school/profile');
        }
        foreach (glob($dir . '/logo.*') ?: [] as $old) {
            @unlink($old);
        }
        $name = 'logo.' . $extension;
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            $this->app->flash->error('The logo could not be saved. Please try again.');
            $this->redirect('school/profile');
        }
        (new SchoolRepository($this->app->db()))->setLogo($school->id, $name);
        $this->app->log('school.logo', 'school', $school->id);
        $this->app->flash->success('Logo saved. It appears in the top bar and on the public timetable.');
        $this->redirect('school/profile');
    }

    public function removeLogo(): void
    {
        $school = $this->school();
        foreach (glob(SchoolRepository::uploadDir($school->id) . '/logo.*') ?: [] as $old) {
            @unlink($old);
        }
        (new SchoolRepository($this->app->db()))->setLogo($school->id, null);
        $this->app->flash->success('Logo removed.');
        $this->redirect('school/profile');
    }

    /**
     * Serve a school's logo (public, like the public timetable). The file is
     * found from the school record, never from a path in the request.
     */
    public function logo(): void
    {
        $school = (new SchoolRepository($this->app->db()))->findBySlug((string) $this->app->request->query('s', ''));
        $path = $school && $school['status'] === 'active' && $school['logo_path']
            ? SchoolRepository::uploadDir((int) $school['id']) . '/' . basename((string) $school['logo_path']) : null;
        if ($path === null || !is_file($path)) {
            throw new HttpException(404, 'There is no logo here.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!isset(self::LOGO_TYPES[$mime])) {
            throw new HttpException(404, 'There is no logo here.');
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: public, max-age=86400');
        readfile($path);
    }

    /** School settings: public link name, default GA parameters, old runs and the danger zone. */
    public function settings(array $errors = [], array $old = []): void
    {
        $school = $this->school();
        $members = (new MemberRepository($this->app->db()))->list($school);
        $this->render('schools/settings', [
            'title' => 'School settings',
            'ga' => ($old['ga'] ?? null) ?: GaDefaults::load(new SchoolSettingsRepository($this->app->db(), $school)),
            'slug' => $old['slug'] ?? $school->slug(),
            'errors' => $errors,
            'isOwner' => $school->can('owner'),
            'transferTo' => array_values(array_filter($members, fn ($m) => $m['role'] !== 'owner')),
            'jobCounts' => (new JobRepository($this->app->db(), $school))->countsByStatus(),
        ]);
    }

    public function saveGa(): void
    {
        $input = array_intersect_key($this->app->request->allInput(), GaDefaults::SETTING_KEYS);
        $errors = GaParams::validate($input);
        if ($errors) {
            http_response_code(422);
            $this->settings($errors, ['ga' => $input]);
            return;
        }
        GaDefaults::save(new SchoolSettingsRepository($this->app->db(), $this->school()), $input);
        $this->app->log('settings.ga_defaults', null, null, $input);
        $this->app->flash->success('Default search settings saved. New runs start with them.');
        $this->redirect('school/settings');
    }

    public function saveSlug(): void
    {
        $school = $this->school();
        $slug = SchoolRepository::slugify((string) $this->app->request->input('slug', ''));
        if (!(new SchoolRepository($this->app->db()))->changeSlug($school->id, $slug)) {
            http_response_code(422);
            $this->settings(['slug' => "The link name '{$slug}' is already used by another school. Try adding your town, for example {$slug}-lagos."], ['slug' => $slug]);
            return;
        }
        $this->app->log('school.slug', 'school', $school->id, ['from' => $school->slug(), 'to' => $slug]);
        $this->app->flash->success('Public link changed. Old links that used the previous name no longer work, so share the new one.');
        $this->redirect('school/settings');
    }

    public function deleteOldJobs(): void
    {
        $days = (int) $this->app->request->input('days', 0);
        if ($days < 1 || $days > 3650) {
            $this->app->flash->error('Enter a number of days from 1 to 3650.');
            $this->redirect('school/settings');
        }
        $count = (new JobRepository($this->app->db(), $this->school()))->deleteOld($days, $this->app->request->input('include_manual') === '1');
        $this->app->log('job.delete_old', null, null, ['days' => $days, 'deleted' => $count]);
        $this->app->flash->success("{$count} old run(s) deleted, with their progress and timetables. Published runs are always kept.");
        $this->redirect('school/settings');
    }

    /** Owner only: hand the school to another member. */
    public function transfer(): void
    {
        $school = $this->school();
        $to = (new MemberRepository($this->app->db()))->find($school, (int) $this->app->request->input('user_id', 0));
        if ($to === null || $to['role'] === 'owner') {
            $this->app->flash->error('Choose a member of this school.');
            $this->redirect('school/settings');
        }
        $this->requirePassword();
        (new MemberRepository($this->app->db()))->transferOwnership($school, (int) $school->userId, (int) $to['id']);
        $this->app->log('school.transfer', 'user', (int) $to['id']);
        $this->app->flash->success("{$to['full_name']} is now the owner. You stay in the school as an administrator.");
        $this->redirect('dashboard');
    }

    /** Owner only: delete the school and every record in it, after typing its link name. */
    public function delete(): void
    {
        $school = $this->school();
        if (trim((string) $this->app->request->input('confirm_slug', '')) !== $school->slug()) {
            $this->app->flash->error("To delete the school, type its link name ({$school->slug()}) exactly.");
            $this->redirect('school/settings');
        }
        $this->requirePassword();
        if ((new JobRepository($this->app->db(), $school))->active() !== null) {
            $this->app->flash->error('A timetable run is waiting or running. Stop it first, then delete the school.');
            $this->redirect('school/settings');
        }
        (new SchoolRepository($this->app->db()))->delete($school->id);
        $this->app->audit()->log($this->app->auth()->id(), 'school.delete', 'school', $school->id, ['name' => $school->name()], $this->app->request->ip());
        $this->app->flash->success($school->name() . ' and all its data were deleted.');
        $this->redirect('dashboard');
    }

    /**
     * Actions that cannot be undone ask for the password again, so a
     * forgotten open session or a stolen session cookie is not enough.
     */
    private function requirePassword(): void
    {
        $user = $this->app->auth()->user();
        if (!password_verify((string) $this->app->request->input('current_password', ''), (string) $user['password_hash'])) {
            $this->app->log('school.reauth_failed', 'user', (int) $user['id']);
            $this->app->flash->error('Your password was not correct, so nothing was changed.');
            $this->redirect('school/settings');
        }
    }

    private function checkLogo(?array $file): ?string
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return 'Choose an image file for the logo.';
        }
        if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > self::LOGO_MAX_BYTES || !is_uploaded_file((string) $file['tmp_name'])) {
            return 'The logo must be a PNG, JPEG, GIF or WebP image smaller than 512 KB.';
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!isset(self::LOGO_TYPES[$mime]) || !in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) {
            return 'The logo must be a PNG, JPEG, GIF or WebP image. SVG files are not accepted because they can contain scripts.';
        }
        return null;
    }
}
