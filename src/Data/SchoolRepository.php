<?php
declare(strict_types=1);

namespace App\Data;

use App\Core\SchoolContext;
use App\Services\PeriodGenerator;
use PDO;

/**
 * Schools (tenants). Creating a school sets up everything a new workspace
 * needs: owner membership, default weights and settings, the 50 default
 * periods (Friday 12:00 to 14:00 blocked) and one active semester.
 */
final class SchoolRepository
{
    public const TYPES = [
        'university' => 'University',
        'polytechnic' => 'Polytechnic',
        'college' => 'College of education or college',
        'secondary' => 'Secondary school',
        'other' => 'Other',
    ];

    /** Tables that hold a school's data, children first, so deleting in this order respects foreign keys. */
    private const TENANT_TABLES = ['timetable_entries', 'ga_progress', 'ga_jobs', 'feasibility_checks', 'class_sessions',
        'courses', 'lecturer_availability', 'lecturers', 'student_groups', 'rooms', 'timeslots', 'school_closures', 'semesters',
        'constraint_weights', 'school_settings', 'audit_log', 'help_progress', 'invitations', 'school_members'];

    public function __construct(private PDO $pdo)
    {
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM schools WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM schools WHERE slug = ?');
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Create a school owned by $ownerId and return its id.
     * @param array{name:string, school_type?:string, department_label?:?string, country?:string, timezone?:string, slug?:string} $info
     */
    public function create(array $info, int $ownerId): int
    {
        $own = !$this->pdo->inTransaction();
        if ($own) {
            $this->pdo->beginTransaction();
        }
        try {
            $id = $this->insertWithDefaults($info, $ownerId);
            if ($own) {
                $this->pdo->commit();
            }
        } catch (\Throwable $ex) {
            if ($own) {
                $this->pdo->rollBack();
            }
            throw $ex;
        }
        return $id;
    }

    private function insertWithDefaults(array $info, int $ownerId): int
    {
        $slug = $this->uniqueSlug(self::slugify($info['slug'] ?? $info['name']));
        $this->pdo->prepare(
            'INSERT INTO schools (name, slug, school_type, department_label, country, timezone) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            trim($info['name']), $slug, $info['school_type'] ?? 'university',
            ($info['department_label'] ?? '') !== '' ? trim((string) $info['department_label']) : null,
            $info['country'] ?? 'Nigeria', $info['timezone'] ?? 'Africa/Lagos',
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $school = SchoolContext::system($this->find($id));

        (new MemberRepository($this->pdo))->add($id, $ownerId, 'owner');
        (new WeightRepository($this->pdo, $school))->insertDefaults();
        $settings = new SchoolSettingsRepository($this->pdo, $school);
        foreach (SchoolSettingsRepository::DEFAULTS as $name => $value) {
            $settings->set($name, $value);
        }
        (new PeriodGenerator($this->pdo, $school))->generate(
            SchoolSettingsRepository::DEFAULTS['day_start'],
            SchoolSettingsRepository::DEFAULTS['day_end'],
            (int) SchoolSettingsRepository::DEFAULTS['period_minutes']
        );
        (new SemesterRepository($this->pdo, $school))->create(SemesterRepository::currentSessionLabel(), 1);
        (new UserRepository($this->pdo))->setLastSchool($ownerId, $id);
        return $id;
    }

    /** Owner or admin edits the school profile. */
    public function updateProfile(int $id, array $info): void
    {
        $this->pdo->prepare(
            'UPDATE schools SET name = ?, school_type = ?, department_label = ?, country = ?, timezone = ? WHERE id = ?'
        )->execute([
            trim($info['name']), $info['school_type'],
            trim((string) $info['department_label']) !== '' ? trim((string) $info['department_label']) : null,
            trim($info['country']), $info['timezone'], $id,
        ]);
    }

    /** Change the public link name. Returns false if another school already uses it. */
    public function changeSlug(int $id, string $slug): bool
    {
        $slug = self::slugify($slug);
        $other = $this->findBySlug($slug);
        if ($other && (int) $other['id'] !== $id) {
            return false;
        }
        $this->pdo->prepare('UPDATE schools SET slug = ? WHERE id = ?')->execute([$slug, $id]);
        return true;
    }

    public function setLogo(int $id, ?string $path): void
    {
        $this->pdo->prepare('UPDATE schools SET logo_path = ? WHERE id = ?')->execute([$path, $id]);
    }

    public function setStatus(int $id, string $status): void
    {
        if (!in_array($status, ['active', 'suspended'], true)) {
            throw new \InvalidArgumentException('Unknown status.');
        }
        $this->pdo->prepare('UPDATE schools SET status = ? WHERE id = ?')->execute([$status, $id]);
    }

    public function markSetupComplete(int $id): void
    {
        $this->pdo->prepare('UPDATE schools SET setup_completed_at = COALESCE(setup_completed_at, NOW()) WHERE id = ?')->execute([$id]);
    }

    /** Platform admin list: every school with member and run counts (no school data). */
    public function listForPlatform(): array
    {
        return $this->pdo->query(
            "SELECT s.*,
                    (SELECT COUNT(*) FROM school_members m WHERE m.school_id = s.id) AS members,
                    (SELECT u.email FROM school_members m JOIN users u ON u.id = m.user_id
                      WHERE m.school_id = s.id AND m.role = 'owner' LIMIT 1) AS owner_email,
                    (SELECT COUNT(*) FROM ga_jobs j WHERE j.school_id = s.id) AS runs,
                    (SELECT MAX(j.created_at) FROM ga_jobs j WHERE j.school_id = s.id) AS last_run_at
             FROM schools s ORDER BY s.name"
        )->fetchAll();
    }

    /** Delete a school and all its data. Uploaded files are removed too. */
    public function delete(int $id): void
    {
        $this->pdo->beginTransaction();
        try {
            foreach (self::TENANT_TABLES as $table) {
                $this->pdo->prepare("DELETE FROM {$table} WHERE school_id = ?")->execute([$id]);
            }
            $this->pdo->prepare('UPDATE users SET last_school_id = NULL WHERE last_school_id = ?')->execute([$id]);
            $this->pdo->prepare('DELETE FROM schools WHERE id = ?')->execute([$id]);
            $this->pdo->commit();
        } catch (\Throwable $ex) {
            $this->pdo->rollBack();
            throw $ex;
        }
        self::removeDirectory(self::uploadDir($id));
    }

    /** storage/uploads/{school_id}/ (never inside the web root). */
    public static function uploadDir(int $schoolId): string
    {
        return APP_ROOT . '/storage/uploads/' . $schoolId;
    }

    /** "Demo University!" => "demo-university" (letters, digits and dashes, at most 50 characters). */
    public static function slugify(string $text): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $text), '-'));
        $slug = trim(substr($slug, 0, 50), '-');
        return $slug !== '' ? $slug : 'school';
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        for ($n = 2; $this->findBySlug($slug) !== null; $n++) {
            $slug = substr($base, 0, 46) . '-' . $n;
        }
        return $slug;
    }

    private static function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $file) {
            if ($file !== '.' && $file !== '..') {
                @unlink($dir . '/' . $file);
            }
        }
        @rmdir($dir);
    }
}
