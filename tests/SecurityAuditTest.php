<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Config;
use App\Core\Crypto;
use App\Core\Mailer;
use App\Core\Request;

require_once __DIR__ . '/HttpClient.php';

/**
 * Fixes from the go-live security audit (docs/SECURITY_AUDIT.md), each
 * pinned by a test so it cannot quietly come undone.
 */
final class SecurityAuditTest extends TestCase
{
    private const PASSWORD = 'correct horse 1';

    private function signUp(HttpClient $http, string $email, array $extra = []): array
    {
        return $http->post('/index.php?r=auth/signup', $extra + [
            'full_name' => 'Audit Owner', 'email' => $email, 'password' => self::PASSWORD, 'password_confirm' => self::PASSWORD,
            'school_name' => 'Audit School ' . substr(md5($email), 0, 6), 'school_type' => 'college', 'department_label' => '',
        ], '/index.php?r=auth/signup');
    }

    public function testClientAddressComesFromForwardedForOnlyBehindATrustedProxy(): void
    {
        $server = ['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 203.0.113.7'];
        Config::set('trusted_proxies', []);
        $this->assertSame('10.0.0.5', (new Request([], [], $server))->ip(), 'No trusted proxy: the header is ignored.');
        Config::set('trusted_proxies', ['10.0.0.5']);
        $this->assertSame('203.0.113.7', (new Request([], [], $server))->ip(), 'Trusted proxy: the address it added (the last one).');
        $this->assertSame('10.0.0.9', (new Request([], [], ['REMOTE_ADDR' => '10.0.0.9'] + $server))->ip(), 'Other senders are not trusted.');
        $this->assertSame('10.0.0.5', (new Request([], [], ['HTTP_X_FORWARDED_FOR' => 'nonsense'] + $server))->ip(), 'A bad header is ignored.');
        Config::set('trusted_proxies', []);
    }

    public function testSmtpPasswordIsStoredEncrypted(): void
    {
        $stored = Crypto::encrypt('smtp secret');
        $this->assertFalse(str_contains($stored, 'smtp secret'));
        $this->assertSame('smtp secret', Mailer::smtpPassword($stored));
        $this->assertSame('old plain value', Mailer::smtpPassword('old plain value'), 'A value saved before the change still works.');
    }

    public function testHealthWarnsAboutAWeakDatabaseAccount(): void
    {
        $pdo = $this->migratedTestDb();
        $user = Config::get('db_user');
        $pass = Config::get('db_pass');
        try {
            Config::set('db_user', 'root');
            Config::set('db_pass', '');
            $check = array_column((new \App\Services\HealthService($pdo))->checks(), null, 'label')['Database account'];
            $this->assertSame('warning', $check['status']);
            $this->assertTrue(str_contains($check['detail'], 'connects as root') && str_contains($check['detail'], 'has no password'),
                $check['detail']);
        } finally {
            Config::set('db_user', $user);
            Config::set('db_pass', $pass);
        }
        $this->dropAllTables($pdo);
    }

    public function testBackupNeverPutsTheDatabasePasswordOnTheCommandLineOrInTheEnvironment(): void
    {
        $code = (string) file_get_contents(APP_ROOT . '/src/Services/BackupService.php');
        $this->assertFalse(str_contains($code, 'MYSQL_PWD'), 'The password is not passed in the environment.');
        $this->assertFalse((bool) preg_match("/'--password/", $code), 'The password is not on the command line.');
        $this->assertTrue(str_contains($code, '--defaults-extra-file=') && str_contains($code, '--no-tablespaces'),
            'Option file, and a dump that works without server-wide rights.');
    }

    public function testPagesSendHardenedHeadersAndUnknownPathsAre404(): void
    {
        $pdo = $this->migratedTestDb();
        HttpClient::startServer();
        $http = new HttpClient();
        $home = $http->get('/index.php?r=home');
        $headers = $home['headers'];
        $this->assertTrue(str_contains($headers['content-security-policy'] ?? '', "frame-ancestors 'none'"));
        $this->assertSame('DENY', $headers['x-frame-options'] ?? '');
        $this->assertSame('nosniff', $headers['x-content-type-options'] ?? '');
        $this->assertTrue(str_contains($headers['permissions-policy'] ?? '', 'camera=()'), 'Permissions-Policy is sent.');
        $this->assertFalse(isset($headers['x-powered-by']), 'The PHP version is not announced.');
        $this->assertSame(404, $http->get('/backup-old')['status'], 'An unknown path is a 404, not the home page.');
        $this->assertSame(404, $http->get('/s/does-not-exist')['status'], 'An unknown school is a 404 too.');
        $this->dropAllTables($pdo);
    }

    public function testPasswordChangeSignsOutOtherSessions(): void
    {
        $pdo = $this->migratedTestDb();
        HttpClient::startServer();
        $laptop = new HttpClient();
        $this->signUp($laptop, 'twice@example.edu');
        $phone = new HttpClient();
        $phone->login('twice@example.edu', self::PASSWORD);
        $this->assertSame(200, $phone->get('/index.php?r=dashboard')['status']);

        $laptop->post('/index.php?r=account/password', ['current_password' => self::PASSWORD, 'password' => 'brand new pass 2',
            'password_confirm' => 'brand new pass 2'], '/index.php?r=account/profile');
        $this->assertSame(200, $laptop->get('/index.php?r=dashboard')['status'], 'The browser that changed it stays signed in.');
        $this->assertSame(303, $phone->get('/index.php?r=dashboard')['status'], 'Every other session is signed out.');
        $this->dropAllTables($pdo);
    }

    public function testSignUpBotsAndInvitationFloodsAreRefused(): void
    {
        $pdo = $this->migratedTestDb();
        HttpClient::startServer();
        $bot = $this->signUp(new HttpClient(), 'bot@example.edu', ['website' => 'http://spam.example']);
        $this->assertSame(422, $bot['status'], 'The hidden honeypot field was filled in.');
        $this->assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM users WHERE email = 'bot@example.edu'")->fetchColumn());

        $owner = new HttpClient();
        $this->signUp($owner, 'inviter@example.edu');
        $statuses = [];
        for ($i = 0; $i < 4; $i++) {
            $statuses[] = $owner->post('/index.php?r=members/invite', ['email' => 'target@example.edu', 'role' => 'viewer'], '/index.php?r=members')['status'];
        }
        $this->assertSame([303, 303, 303, 422], $statuses, 'At most 3 invitations to one address per hour.');
        $this->dropAllTables($pdo);
    }

    public function testDeletingOrHandingOverASchoolNeedsThePassword(): void
    {
        $pdo = $this->migratedTestDb();
        HttpClient::startServer();
        $owner = new HttpClient();
        $this->signUp($owner, 'keeper@example.edu');
        $slug = (string) $pdo->query("SELECT s.slug FROM schools s JOIN school_members m ON m.school_id = s.id
            JOIN users u ON u.id = m.user_id WHERE u.email = 'keeper@example.edu'")->fetchColumn();
        $owner->post('/index.php?r=school/delete', ['confirm_slug' => $slug, 'current_password' => 'wrong password'], '/index.php?r=school/settings');
        $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM schools WHERE slug = " . $pdo->quote($slug))->fetchColumn(),
            'Not deleted with a wrong password.');
        $owner->post('/index.php?r=school/delete', ['confirm_slug' => $slug, 'current_password' => self::PASSWORD], '/index.php?r=school/settings');
        $this->assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM schools WHERE slug = " . $pdo->quote($slug))->fetchColumn(),
            'Deleted with the right password.');
        $this->dropAllTables($pdo);
    }
}
