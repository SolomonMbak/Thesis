<?php
declare(strict_types=1);

namespace App\Data;

use App\Core\Auth;
use PDO;

/**
 * User accounts. Users are platform-wide (one person can belong to several
 * schools); what they may do in a school is in school_members.
 */
final class UserRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([strtolower(trim($email))]);
        return $stmt->fetch() ?: null;
    }

    public function create(string $fullName, string $email, string $password, bool $verified = false, bool $platformAdmin = false): int
    {
        $this->pdo->prepare(
            'INSERT INTO users (full_name, email, password_hash, is_platform_admin, email_verified_at) VALUES (?, ?, ?, ?, ?)'
        )->execute([trim($fullName), strtolower(trim($email)), Auth::hashPassword($password), $platformAdmin ? 1 : 0,
            $verified ? date('Y-m-d H:i:s') : null]);
        return (int) $this->pdo->lastInsertId();
    }

    public function setPassword(int $id, string $password): void
    {
        $this->pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([Auth::hashPassword($password), $id]);
        $user = $this->find($id);
        if ($user) {
            // A new password also clears failed sign-in attempts, so the user is not still locked out.
            $this->pdo->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([$user['email']]);
        }
    }

    public function updateProfile(int $id, string $fullName): void
    {
        $this->pdo->prepare('UPDATE users SET full_name = ? WHERE id = ?')->execute([trim($fullName), $id]);
    }

    public function setTheme(int $id, string $theme): void
    {
        if (!in_array($theme, ['system', 'light', 'dark'], true)) {
            throw new \InvalidArgumentException('Unknown theme.');
        }
        $this->pdo->prepare('UPDATE users SET theme = ? WHERE id = ?')->execute([$theme, $id]);
    }

    public function markVerified(int $id): void
    {
        $this->pdo->prepare('UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?')->execute([$id]);
    }

    public function setLastSchool(int $id, int $schoolId): void
    {
        $this->pdo->prepare('UPDATE users SET last_school_id = ? WHERE id = ?')->execute([$schoolId, $id]);
    }

    public function setPlatformAdmin(int $id, bool $on): void
    {
        $this->pdo->prepare('UPDATE users SET is_platform_admin = ? WHERE id = ?')->execute([$on ? 1 : 0, $id]);
    }
}
