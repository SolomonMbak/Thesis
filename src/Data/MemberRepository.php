<?php
declare(strict_types=1);

namespace App\Data;

use App\Core\SchoolContext;
use PDO;

/**
 * Who belongs to which school, and in which role.
 *
 * The lookups by user (membership, memberships) are used to resolve the
 * current school and build the school switcher; everything else is scoped
 * to the context's school.
 */
final class MemberRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** The school row plus the user's role, if the user belongs to that school. */
    public function membership(int $userId, int $schoolId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*, m.role FROM school_members m JOIN schools s ON s.id = m.school_id WHERE m.user_id = ? AND m.school_id = ?'
        );
        $stmt->execute([$userId, $schoolId]);
        return $stmt->fetch() ?: null;
    }

    /** Every school the user belongs to, for the switcher (active schools first). */
    public function memberships(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT s.*, m.role FROM school_members m JOIN schools s ON s.id = m.school_id
             WHERE m.user_id = ? ORDER BY s.status = 'active' DESC, s.name"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function add(int $schoolId, int $userId, string $role): void
    {
        $this->pdo->prepare(
            'INSERT INTO school_members (school_id, user_id, role) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE role = IF(role = \'owner\', role, VALUES(role))'
        )->execute([$schoolId, $userId, $role]);
    }

    /** Members of the context's school with their account details. */
    public function list(SchoolContext $school): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.id, u.full_name, u.email, u.is_active, u.email_verified_at, u.last_login_at, u.totp_enabled_at, u.must_change_password,
                    m.role, m.joined_at
             FROM school_members m JOIN users u ON u.id = m.user_id
             WHERE m.school_id = ? ORDER BY FIELD(m.role, 'owner', 'admin', 'officer', 'viewer'), u.full_name"
        );
        $stmt->execute([$school->id]);
        return $stmt->fetchAll();
    }

    /** One member of the context's school, or null (also for users of other schools). */
    public function find(SchoolContext $school, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.full_name, u.email, m.role FROM school_members m JOIN users u ON u.id = m.user_id
             WHERE m.school_id = ? AND m.user_id = ?'
        );
        $stmt->execute([$school->id, $userId]);
        return $stmt->fetch() ?: null;
    }

    public function setRole(SchoolContext $school, int $userId, string $role): void
    {
        $this->pdo->prepare('UPDATE school_members SET role = ? WHERE school_id = ? AND user_id = ?')
            ->execute([$role, $school->id, $userId]);
    }

    public function remove(SchoolContext $school, int $userId): void
    {
        $this->pdo->prepare("DELETE FROM school_members WHERE school_id = ? AND user_id = ? AND role <> 'owner'")
            ->execute([$school->id, $userId]);
    }

    /** The owner hands the school to another member, who becomes owner; the old owner becomes an admin. */
    public function transferOwnership(SchoolContext $school, int $fromUserId, int $toUserId): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("UPDATE school_members SET role = 'admin' WHERE school_id = ? AND user_id = ? AND role = 'owner'")
                ->execute([$school->id, $fromUserId]);
            $this->pdo->prepare("UPDATE school_members SET role = 'owner' WHERE school_id = ? AND user_id = ?")
                ->execute([$school->id, $toUserId]);
            $this->pdo->commit();
        } catch (\Throwable $ex) {
            $this->pdo->rollBack();
            throw $ex;
        }
    }

    public function count(SchoolContext $school): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM school_members WHERE school_id = ?');
        $stmt->execute([$school->id]);
        return (int) $stmt->fetchColumn();
    }
}
