<?php
declare(strict_types=1);

namespace App\Data;

use App\Core\SchoolContext;
use PDO;

/**
 * Invitations to join a school (BUILD_SPEC section 2). Valid for 7 days.
 * As with user tokens, only the hash of the token is stored.
 */
final class InvitationRepository
{
    public const VALID_DAYS = 7;
    public const ROLES = ['admin', 'officer', 'viewer'];

    public function __construct(private PDO $pdo)
    {
    }

    /** Create an invitation and return the raw token for the link. A pending one for the same email is replaced. */
    public function create(SchoolContext $school, string $email, string $role, int $invitedBy): string
    {
        if (!in_array($role, self::ROLES, true)) {
            throw new \InvalidArgumentException('Unknown role.');
        }
        $email = strtolower(trim($email));
        $this->pdo->prepare('DELETE FROM invitations WHERE school_id = ? AND email = ? AND accepted_at IS NULL')
            ->execute([$school->id, $email]);
        $raw = bin2hex(random_bytes(32));
        $this->pdo->prepare(
            'INSERT INTO invitations (school_id, email, role, token_hash, invited_by, expires_at)
             VALUES (?, ?, ?, ?, ?, NOW() + INTERVAL ? DAY)'
        )->execute([$school->id, $email, $role, TokenRepository::hash($raw), $invitedBy, self::VALID_DAYS]);
        return $raw;
    }

    /** Pending (not accepted, not expired) invitations of the school. */
    public function pending(SchoolContext $school): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT i.*, u.full_name AS invited_by_name FROM invitations i LEFT JOIN users u ON u.id = i.invited_by
             WHERE i.school_id = ? AND i.accepted_at IS NULL AND i.expires_at > NOW() ORDER BY i.id DESC'
        );
        $stmt->execute([$school->id]);
        return $stmt->fetchAll();
    }

    public function revoke(SchoolContext $school, int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM invitations WHERE id = ? AND school_id = ? AND accepted_at IS NULL');
        $stmt->execute([$id, $school->id]);
        return $stmt->rowCount() > 0;
    }

    /** A valid invitation by raw token, with the school's name and status. */
    public function findValid(string $raw): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $raw)) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT i.*, s.name AS school_name, s.status AS school_status FROM invitations i JOIN schools s ON s.id = i.school_id
             WHERE i.token_hash = ? AND i.accepted_at IS NULL AND i.expires_at > NOW()'
        );
        $stmt->execute([TokenRepository::hash($raw)]);
        return $stmt->fetch() ?: null;
    }

    /** Mark accepted. Returns false if it was accepted already. */
    public function accept(int $id): bool
    {
        $stmt = $this->pdo->prepare('UPDATE invitations SET accepted_at = NOW() WHERE id = ? AND accepted_at IS NULL');
        $stmt->execute([$id]);
        return $stmt->rowCount() === 1;
    }
}
