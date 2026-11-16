<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Mailer;
use App\Data\SettingsRepository;
use PDO;

require_once __DIR__ . '/HttpClient.php';

/**
 * Sign-up, email verification, invitations, roles and password reset,
 * through the real pages (BUILD_SPEC section 2). In development, emails go
 * to storage/logs/mail.log, which the tests read to follow the links.
 */
final class AccountsTest extends TestCase
{
    private function freshDb(): PDO
    {
        $pdo = $this->migratedTestDb();
        @unlink(Mailer::logFile());
        HttpClient::startServer();
        return $pdo;
    }

    /** The last link in mail.log that contains $route. */
    private function mailedLink(string $route): string
    {
        $log = (string) @file_get_contents(Mailer::logFile());
        preg_match_all('#https?://[^\s]+' . preg_quote($route, '#') . '[^\s]*#', $log, $m);
        $this->assertTrue(!empty($m[0]), "No emailed link for {$route}.");
        $link = end($m[0]);
        return (string) preg_replace('#^https?://[^/]+#', '', $link);
    }

    private function signUp(HttpClient $http, string $email): array
    {
        return $http->post('/index.php?r=auth/signup', [
            'full_name' => 'Ada Owner', 'email' => $email, 'password' => 'correct horse 1', 'password_confirm' => 'correct horse 1',
            'school_name' => 'Sunrise Polytechnic', 'school_type' => 'polytechnic', 'department_label' => 'Computing',
        ], '/index.php?r=auth/signup');
    }

    public function testSignUpCreatesACompleteSchoolAndOpensTheSetupGuide(): void
    {
        $pdo = $this->freshDb();
        $http = new HttpClient();
        $response = $this->signUp($http, 'ada@sunrise.example');
        $this->assertSame(303, $response['status']);
        $this->assertTrue(str_contains($response['headers']['location'] ?? '', 'r=setup'), 'Sign-up opens the Setup Guide.');

        $school = $pdo->query("SELECT * FROM schools WHERE slug = 'sunrise-polytechnic'")->fetch();
        $this->assertTrue($school !== false, 'The school gets a slug from its name.');
        $id = (int) $school['id'];
        $count = fn (string $sql) => (int) $pdo->query($sql)->fetchColumn();
        $this->assertSame(50, $count("SELECT COUNT(*) FROM timeslots WHERE school_id = {$id}"), '50 default periods.');
        $this->assertSame(2, $count("SELECT SUM(is_blocked) FROM timeslots WHERE school_id = {$id}"), 'Friday 12:00 to 14:00 blocked.');
        $this->assertSame(6, $count("SELECT COUNT(*) FROM constraint_weights WHERE school_id = {$id}"));
        $this->assertSame(1, $count("SELECT COUNT(*) FROM semesters WHERE school_id = {$id} AND is_active = 1"));
        $this->assertSame('owner', (string) $pdo->query("SELECT m.role FROM school_members m JOIN users u ON u.id = m.user_id
            WHERE u.email = 'ada@sunrise.example' AND m.school_id = {$id}")->fetchColumn());

        $setup = $http->get('/index.php?r=setup');
        $this->assertSame(200, $setup['status']);
        $this->assertTrue(str_contains($setup['body'], 'School profile and teaching week'));
        $this->assertTrue(str_contains($setup['body'], 'Please confirm your email address'), 'Unverified users see the reminder.');

        // The emailed link verifies the address.
        $verify = $http->get($this->mailedLink('auth/verify'));
        $this->assertSame(303, $verify['status']);
        $this->assertTrue($pdo->query("SELECT email_verified_at FROM users WHERE email = 'ada@sunrise.example'")->fetchColumn() !== null);
        $this->assertSame(200, $this->signUp(new HttpClient(), 'ada@sunrise.example')['status'] === 422 ? 200 : 0, 'The same email cannot sign up twice.');
        $this->dropAllTables($pdo);
    }

    public function testUnverifiedOwnerMustVerifyAfterSevenDays(): void
    {
        $pdo = $this->freshDb();
        $http = new HttpClient();
        $this->signUp($http, 'late@sunrise.example');
        $pdo->exec("UPDATE users SET created_at = NOW() - INTERVAL 8 DAY WHERE email = 'late@sunrise.example'");
        $page = $http->get('/index.php?r=rooms');
        $this->assertSame(303, $page['status']);
        $this->assertTrue(str_contains($page['headers']['location'] ?? '', 'auth/verify-required'));
        $this->assertSame(200, $http->get('/index.php?r=auth/verify-required')['status']);
        $this->dropAllTables($pdo);
    }

    public function testInvitationCreatesAMemberWithTheChosenRole(): void
    {
        $pdo = $this->freshDb();
        $owner = new HttpClient();
        $this->signUp($owner, 'boss@sunrise.example');
        $sent = $owner->post('/index.php?r=members/invite', ['email' => 'viewer@sunrise.example', 'role' => 'viewer']);
        $this->assertSame(303, $sent['status']);

        $guest = new HttpClient();
        $link = $this->mailedLink('invite/accept');
        $this->assertSame(200, $guest->get($link)['status']);
        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);
        $accepted = $guest->post('/index.php?r=invite/accept', ['token' => $query['token'], 'full_name' => 'Val Viewer',
            'password' => 'viewer pass 12', 'password_confirm' => 'viewer pass 12'], $link);
        $this->assertSame(303, $accepted['status']);
        $this->assertSame('viewer', (string) $pdo->query("SELECT m.role FROM school_members m JOIN users u ON u.id = m.user_id
            WHERE u.email = 'viewer@sunrise.example'")->fetchColumn());

        // A viewer reads timetables but cannot change data or manage the school.
        $this->assertSame(200, $guest->get('/index.php?r=timetables')['status']);
        $this->assertSame(403, $guest->get('/index.php?r=rooms')['status']);
        $this->assertSame(403, $guest->get('/index.php?r=members')['status']);
        $this->assertSame(403, $guest->post('/index.php?r=rooms/store', ['code' => 'X1', 'name' => 'X', 'capacity' => 10, 'room_type' => 'lecture'])['status']);
        // The link works only once.
        $again = (new HttpClient())->get($link);
        $this->assertTrue(str_contains($again['body'], 'This invitation does not work'));
        $this->dropAllTables($pdo);
    }

    public function testPasswordResetByEmailLink(): void
    {
        $pdo = $this->freshDb();
        $this->signUp(new HttpClient(), 'forgetful@sunrise.example');
        $http = new HttpClient();
        $answer = $http->post('/index.php?r=auth/forgot', ['email' => 'forgetful@sunrise.example'], '/index.php?r=auth/forgot');
        $unknown = (new HttpClient())->post('/index.php?r=auth/forgot', ['email' => 'nobody@sunrise.example'], '/index.php?r=auth/forgot');
        $this->assertTrue(str_contains($answer['body'], 'If an account uses'));
        $this->assertTrue(str_contains($unknown['body'], 'If an account uses'), 'The answer must not reveal which emails exist.');

        $link = $this->mailedLink('auth/reset');
        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);
        $reset = $http->post('/index.php?r=auth/reset', ['token' => $query['token'], 'password' => 'brand new pass 9',
            'password_confirm' => 'brand new pass 9'], $link);
        $this->assertSame(303, $reset['status']);
        (new HttpClient())->login('forgetful@sunrise.example', 'brand new pass 9');
        $used = $http->post('/index.php?r=auth/reset', ['token' => $query['token'], 'password' => 'another pass 99',
            'password_confirm' => 'another pass 99'], '/index.php?r=help');
        $this->assertSame(422, $used['status'], 'A reset link works only once.');
        $this->dropAllTables($pdo);
    }

    public function testSignUpCanBeClosed(): void
    {
        $pdo = $this->freshDb();
        (new SettingsRepository($pdo))->set('signup_open', '0');
        $http = new HttpClient();
        $this->assertTrue(str_contains($http->get('/index.php?r=auth/signup')['body'], 'Sign-up is by invitation'));
        $this->signUp($http, 'closed@sunrise.example');
        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
        $this->dropAllTables($pdo);
    }

    public function testMailerLogsInDevelopmentAndStripsHeaderInjection(): void
    {
        $pdo = $this->migratedTestDb();
        @unlink(Mailer::logFile());
        $sent = (new Mailer(new SettingsRepository($pdo)))->send('someone@example.edu', "Hello\r\nBcc: evil@example.com", 'Body');
        $this->assertTrue($sent);
        $log = (string) file_get_contents(Mailer::logFile());
        $this->assertTrue(str_contains($log, 'Subject: Hello Bcc: evil@example.com'), 'Line breaks in the subject are removed.');
        $this->assertFalse((new Mailer(new SettingsRepository($pdo)))->send("bad\n@example.edu", 'x', 'y'));
        $this->dropAllTables($pdo);
    }
}
