<?php
declare(strict_types=1);

namespace App\Core;

/**
 * The school a request (or a background run) is working in, and the user's
 * role there. Resolved once per request from the session and the user's
 * membership (BUILD_SPEC 13.1), never from a form field or URL.
 *
 * Every repository and service that reads or writes tenant data takes one
 * of these and adds "school_id = ?" with $context->id to every query.
 */
final class SchoolContext
{
    /** Higher rank = more rights. "system" is a background process (run_ga, worker, seed). */
    public const RANKS = ['viewer' => 1, 'officer' => 2, 'admin' => 3, 'owner' => 4, 'system' => 9];

    public const ROLE_LABELS = [
        'owner' => 'Owner',
        'admin' => 'Administrator',
        'officer' => 'Timetable officer',
        'viewer' => 'Viewer (read only)',
    ];

    public readonly int $id;

    /**
     * @param array       $school the schools row
     * @param string      $role   owner, admin, officer, viewer or system
     * @param int|null    $userId the signed-in user (null for background processes)
     */
    public function __construct(public readonly array $school, public readonly string $role, public readonly ?int $userId = null)
    {
        if (!isset(self::RANKS[$role])) {
            throw new \InvalidArgumentException("Unknown role {$role}");
        }
        $this->id = (int) $school['id'];
    }

    /** Context for command line work on one school, with full rights. */
    public static function system(array $school): self
    {
        return new self($school, 'system');
    }

    /** True when the role is at least $role (owner > admin > officer > viewer). */
    public function can(string $role): bool
    {
        return self::RANKS[$this->role] >= (self::RANKS[$role] ?? PHP_INT_MAX);
    }

    public function name(): string
    {
        return (string) $this->school['name'];
    }

    /** "Demo University, Department of Computer Science" */
    public function fullName(): string
    {
        $label = trim((string) ($this->school['department_label'] ?? ''));
        return $this->name() . ($label !== '' ? ', ' . $label : '');
    }

    public function slug(): string
    {
        return (string) $this->school['slug'];
    }

    public function isActive(): bool
    {
        return ($this->school['status'] ?? 'active') === 'active';
    }

    public function roleLabel(): string
    {
        return self::ROLE_LABELS[$this->role] ?? $this->role;
    }
}
