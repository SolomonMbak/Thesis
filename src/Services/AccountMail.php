<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Mailer;
use App\Data\SettingsRepository;
use App\Data\TokenRepository;
use PDO;

/**
 * The emails the system sends about accounts: verify your email, reset your
 * password and join a school. Links carry a one-time token; only its hash is
 * stored (TokenRepository, InvitationRepository).
 */
final class AccountMail
{
    private SettingsRepository $settings;

    public function __construct(private PDO $pdo)
    {
        $this->settings = new SettingsRepository($pdo);
    }

    public function sendVerification(array $user): bool
    {
        $token = (new TokenRepository($this->pdo))->issue((int) $user['id'], 'verify_email');
        $link = mail_url(url('auth/verify', ['token' => $token]));
        if ($link === null) {
            return false;
        }
        return $this->send($user['email'], 'Please confirm your email address', [
            "Hello {$user['full_name']},",
            "Please confirm that this is your email address by opening this link:\n{$link}",
            'The link works for 7 days. If you did not create an account, you can ignore this email.',
        ]);
    }

    public function sendPasswordReset(array $user): bool
    {
        $token = (new TokenRepository($this->pdo))->issue((int) $user['id'], 'reset_password');
        $link = mail_url(url('auth/reset', ['token' => $token]));
        if ($link === null) {
            return false;
        }
        return $this->send($user['email'], 'Reset your password', [
            "Hello {$user['full_name']},",
            "Someone asked to reset the password of your account. To choose a new password, open this link:\n{$link}",
            'The link works for 1 hour and only once. If you did not ask for this, ignore this email: your password stays the same.',
        ]);
    }

    public function sendInvitation(string $email, string $token, string $schoolName, string $roleLabel, string $inviterName): bool
    {
        $link = mail_url(url('invite/accept', ['token' => $token]));
        if ($link === null) {
            return false;
        }
        return $this->send($email, "You are invited to join {$schoolName}", [
            'Hello,',
            "{$inviterName} has invited you to join {$schoolName} on {$this->platformName()} as {$roleLabel}.",
            "To accept, open this link:\n{$link}",
            'If you do not have an account yet, you will choose a password when you accept. The invitation works for 7 days.',
        ]);
    }

    public function sendTest(string $email): bool
    {
        return $this->send($email, 'Test email', [
            'This is a test email from ' . $this->platformName() . '.',
            'If you can read it, sending email works.',
        ]);
    }

    private function send(string $to, string $subject, array $paragraphs): bool
    {
        $paragraphs[] = '-- ' . $this->platformName();
        return (new Mailer($this->settings))->send($to, $subject . ' | ' . $this->platformName(), implode("\n\n", $paragraphs));
    }

    private function platformName(): string
    {
        return (string) $this->settings->get('platform_name', 'Timetable');
    }
}
