<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Crypto;
use App\Core\Totp;
use App\Data\RememberTokenRepository;
use App\Data\TwoFactorRepository;
use App\Data\UserRepository;
use PDO;

require_once __DIR__ . '/HttpClient.php';

/**
 * Login security (BUILD_SPEC 13.3): "Keep me signed in" for 30 days,
 * two-step sign-in with an authenticator app and recovery codes, and members
 * added by an administrator with a temporary password. Repository rules
 * first, then the real pages.
 */
final class LoginSecurityTest extends TestCase
{
    private const PASSWORD = 'correct horse 1';

    private function signUpOwner(HttpClient $http, string $email): void
    {
        $response = $http->post('/index.php?r=auth/signup', [
            'full_name' => 'Ada Owner', 'email' => $email, 'password' => self::PASSWORD, 'password_confirm' => self::PASSWORD,
            'school_name' => 'Login College ' . substr(md5($email), 0, 6), 'school_type' => 'college', 'department_label' => '',
        ], '/index.php?r=auth/signup');
        $this->assertSame(303, $response['status'], 'Sign-up failed.');
    }

    private function signIn(HttpClient $http, string $email, string $password = self::PASSWORD, bool $remember = false): array
    {
        return $http->post('/index.php?r=auth/login', ['email' => $email, 'password' => $password, 'remember' => $remember ? '1' : '0'],
            '/index.php?r=auth/login');
    }

    private static function location(array $response): string
    {
        return $response['headers']['location'] ?? '';
    }

    public function testRememberTokensRotateExpireAndCatchACopiedCookie(): void
    {
        $pdo = $this->migratedTestDb();
        $userId = (new UserRepository($pdo))->create('Remy', 'remy@example.edu', self::PASSWORD, true);
        $now = 1_900_000_000;
        $repo = new RememberTokenRepository($pdo, function () use (&$now) { return $now; });

        $token = $repo->issue($userId, 30, 'Mozilla/5.0 (Windows NT 10.0) Chrome/130', '203.0.113.9');
        $this->assertSame($now + 30 * 86400, $token['expires']);
        $this->assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM remember_tokens WHERE validator_hash = '"
            . explode('.', $token['cookie'])[1] . "'")->fetchColumn(), 'Only the hash of the secret half is stored.');

        $first = $repo->use($token['cookie']);
        $this->assertSame($userId, $first['user_id']);
        $this->assertTrue($first['cookie'] !== $token['cookie'], 'The secret half changes on every use.');
        $this->assertSame($token['expires'], $first['expires'], 'Using the cookie never extends it.');

        $now += 60;
        $parallel = $repo->use($token['cookie']);
        $this->assertSame($userId, $parallel['user_id'], 'The old value works for 2 minutes (requests sent together).');
        $this->assertSame(null, $parallel['cookie']);

        $now += 120;
        $this->assertSame(null, $repo->use($token['cookie']), 'After 2 minutes the old value is refused...');
        $this->assertSame(null, $repo->use((string) $first['cookie']), '...and the token is deleted, as the cookie may have been copied.');

        $other = $repo->issue($userId, 30, '', '');
        $now += 31 * 86400;
        $this->assertSame(null, $repo->use($other['cookie']), 'Expired after 30 days.');
        $this->assertSame(null, $repo->use('not-a-cookie'));
        $repo->issue($userId, 30, '', '');
        $repo->issue($userId, 30, '', '');
        $this->assertCount(2, $repo->forUser($userId));
        $this->assertSame(2, $repo->forgetUser($userId));
        $this->dropAllTables($pdo);
    }

    public function testTwoStepKeyIsEncryptedAndCodesWorkOnce(): void
    {
        $pdo = $this->migratedTestDb();
        $users = new UserRepository($pdo);
        $userId = $users->create('Tess', 'tess@example.edu', self::PASSWORD, true);
        $repo = new TwoFactorRepository($pdo);
        $secret = Totp::newSecret();
        $step = Totp::stepAt(time());
        $repo->enable($userId, $secret, $step);
        $user = $users->find($userId);
        $this->assertTrue(TwoFactorRepository::isOn($user));
        $this->assertFalse(str_contains((string) $user['totp_secret'], $secret), 'The key is not stored in plain text.');
        $this->assertSame($secret, $repo->secret($user));
        $this->assertSame(null, Crypto::decrypt(substr((string) $user['totp_secret'], 0, -4) . 'AAAA'), 'A changed value does not decrypt.');

        $this->assertFalse($repo->useStep($userId, $step), 'The step used to confirm the set-up cannot be used again.');
        $this->assertTrue($repo->useStep($userId, $step + 1));

        $codes = $repo->newRecoveryCodes($userId);
        $this->assertCount(10, $codes);
        $this->assertTrue((bool) preg_match('/^[a-z2-9]{5}-[a-z2-9]{5}$/', $codes[0]), $codes[0]);
        $this->assertTrue($repo->useRecoveryCode($userId, strtoupper(str_replace('-', ' ', $codes[0]))), 'Capitals and spaces are fine.');
        $this->assertFalse($repo->useRecoveryCode($userId, $codes[0]), 'A recovery code works once.');
        $this->assertSame(9, $repo->recoveryCodesLeft($userId));
        $repo->newRecoveryCodes($userId);
        $this->assertSame(10, $repo->recoveryCodesLeft($userId));
        $this->assertFalse($repo->useRecoveryCode($userId, $codes[1]), 'New codes replace the old ones.');
        $repo->disable($userId);
        $this->assertFalse(TwoFactorRepository::isOn($users->find($userId)));
        $this->assertSame(0, $repo->recoveryCodesLeft($userId));
        $this->dropAllTables($pdo);
    }

    public function testKeepMeSignedInSurvivesTheSessionUntilSignOut(): void
    {
        $pdo = $this->migratedTestDb();
        HttpClient::startServer();
        $this->signUpOwner(new HttpClient(), 'keep@example.edu');

        $plain = new HttpClient();
        $this->signIn($plain, 'keep@example.edu');
        $this->assertSame(null, $plain->cookie('tt_remember'), 'Not ticked: no cookie.');

        $http = new HttpClient();
        $this->assertTrue(str_contains($http->get('/index.php?r=auth/login')['body'], 'Keep me signed in for 30 days'));
        $this->assertSame(303, $this->signIn($http, 'keep@example.edu', self::PASSWORD, true)['status']);
        $cookie = (string) $http->cookie('tt_remember');
        $this->assertTrue((bool) preg_match('/^[a-f0-9]{24}\.[a-f0-9]{64}$/', $cookie), 'Ticked: a remember cookie.');

        // The session ends (idle timeout or browser closed): the cookie signs in again.
        $http->dropCookie('tt_session');
        $page = $http->get('/index.php?r=dashboard');
        $this->assertSame(200, $page['status'], 'Signed in again from the cookie.');
        $this->assertFalse(str_contains($page['body'], 'signed out after a period of inactivity'));
        $this->assertTrue($http->cookie('tt_remember') !== $cookie, 'The cookie was renewed.');
        $this->assertTrue(str_contains($http->get('/index.php?r=account/profile')['body'], 'Where you stay signed in'));
        $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'auth.restored'")->fetchColumn());

        // Signing out ends it for good.
        $http->post('/index.php?r=auth/logout', []);
        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM remember_tokens')->fetchColumn(), 'Sign-out deletes the token.');
        $stolen = new HttpClient();
        $stolen->setCookie('tt_remember', (string) $cookie);
        $this->assertSame(303, $stolen->get('/index.php?r=dashboard')['status'], 'An old cookie no longer signs in.');

        // A password reset ends every kept sign-in.
        $again = new HttpClient();
        $this->signIn($again, 'keep@example.edu', self::PASSWORD, true);
        $userId = (int) $pdo->query("SELECT id FROM users WHERE email = 'keep@example.edu'")->fetchColumn();
        $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM remember_tokens WHERE user_id = {$userId}")->fetchColumn());
        $again->post('/index.php?r=account/sign-out-everywhere', []);
        $this->assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM remember_tokens WHERE user_id = {$userId}")->fetchColumn(),
            'Sign out on all browsers.');
        $this->dropAllTables($pdo);
    }

    public function testTwoStepSignInThroughThePages(): void
    {
        $pdo = $this->migratedTestDb();
        HttpClient::startServer();
        $http = new HttpClient();
        $this->signUpOwner($http, 'two@example.edu');

        // Set up: the page shows a QR code and the key; a wrong code or password is refused.
        $setup = $http->get('/index.php?r=account/two-step');
        $this->assertSame(200, $setup['status']);
        $this->assertTrue(str_contains($setup['body'], '<svg class="qr"'), 'QR code shown.');
        preg_match('/data-setup-key>([A-Z2-7 ]+)</', $setup['body'], $m);
        $secret = str_replace(' ', '', $m[1] ?? '');
        $this->assertSame(32, strlen($secret), 'Set-up key shown for typing by hand.');
        $bad = $http->post('/index.php?r=account/two-step/enable', ['code' => '000000', 'password' => 'wrong password'], '/index.php?r=account/two-step');
        $this->assertSame(422, $bad['status']);
        $code = Totp::codeAt($secret, Totp::stepAt(time()));
        $ok = $http->post('/index.php?r=account/two-step/enable', ['code' => $code, 'password' => self::PASSWORD], '/index.php?r=account/two-step');
        $this->assertTrue(str_contains(self::location($ok), 'account/two-step/codes'));
        $codesPage = $http->get('/index.php?r=account/two-step/codes')['body'];
        preg_match_all('#<code>([a-z2-9]{5}-[a-z2-9]{5})</code>#', $codesPage, $c);
        $this->assertCount(10, $c[1], 'Ten recovery codes shown once.');
        $this->assertSame(303, $http->get('/index.php?r=account/two-step/codes')['status'], 'Not shown a second time.');
        $members = $http->get('/index.php?r=members')['body'];
        $this->assertTrue(str_contains($members, 'Two-step on'), 'Members list shows who uses two-step sign-in.');

        // Sign in: the password alone is not enough.
        $browser = new HttpClient();
        $first = $this->signIn($browser, 'two@example.edu', self::PASSWORD, true);
        $this->assertTrue(str_contains(self::location($first), 'auth/two-step'), 'Asked for the code.');
        $this->assertSame(303, $browser->get('/index.php?r=dashboard')['status'], 'Not signed in before the code.');
        $this->assertSame(null, $browser->cookie('tt_remember'), 'No remember cookie before the code.');
        $wrong = $browser->post('/index.php?r=auth/two-step', ['code' => '123456'], '/index.php?r=auth/two-step');
        $this->assertSame(422, $wrong['status']);
        $this->assertTrue(str_contains($wrong['body'], 'That code is not correct'));
        // The code used to confirm the set-up is refused (used once); the next one works.
        $next = Totp::codeAt($secret, Totp::stepAt(time()) + 1);
        $in = $browser->post('/index.php?r=auth/two-step', ['code' => $next], '/index.php?r=auth/two-step');
        $this->assertSame(303, $in['status']);
        $this->assertSame(200, $browser->get('/index.php?r=dashboard')['status'], 'Signed in after the code.');
        $this->assertTrue($browser->cookie('tt_remember') !== null, '"Keep me signed in" applies after the code.');

        // A recovery code works once.
        $lost = new HttpClient();
        $this->signIn($lost, 'two@example.edu');
        $this->assertSame(303, $lost->post('/index.php?r=auth/two-step', ['code' => strtoupper($c[1][0])], '/index.php?r=auth/two-step')['status']);
        $this->assertSame(200, $lost->get('/index.php?r=dashboard')['status']);
        $reuse = new HttpClient();
        $this->signIn($reuse, 'two@example.edu');
        $this->assertSame(422, $reuse->post('/index.php?r=auth/two-step', ['code' => $c[1][0]], '/index.php?r=auth/two-step')['status']);

        // Five wrong codes lock the second step for a while.
        for ($i = 0; $i < 4; $i++) {
            $reuse->post('/index.php?r=auth/two-step', ['code' => '000000'], '/index.php?r=auth/two-step');
        }
        $locked = $reuse->post('/index.php?r=auth/two-step', ['code' => Totp::codeAt($secret, Totp::stepAt(time()))], '/index.php?r=auth/two-step');
        $this->assertTrue(str_contains($locked['body'], 'Too many wrong codes'), 'Throttled after 5 failures.');

        // Turning it off needs the password.
        $this->assertSame(422, $http->post('/index.php?r=account/two-step/disable', ['password' => 'nope'], '/index.php?r=account/two-step')['status']);
        $http->post('/index.php?r=account/two-step/disable', ['password' => self::PASSWORD], '/index.php?r=account/two-step');
        $this->assertSame(null, $pdo->query("SELECT totp_enabled_at FROM users WHERE email = 'two@example.edu'")->fetchColumn());
        $this->dropAllTables($pdo);
    }

    public function testAdministratorAddsAMemberWithATemporaryPassword(): void
    {
        $pdo = $this->migratedTestDb();
        HttpClient::startServer();
        $owner = new HttpClient();
        $this->signUpOwner($owner, 'boss@example.edu');
        $this->signUpOwner(new HttpClient(), 'elsewhere@example.edu');

        $form = $owner->get('/index.php?r=members')['body'];
        $this->assertTrue(str_contains($form, 'Add someone without email'));
        $this->assertTrue((bool) preg_match('/value="[a-z2-9]{4}-[a-z2-9]{4}-[a-z2-9]{4}" id="f_add_password"/', $form), 'A temporary password is suggested.');

        $taken = $owner->post('/index.php?r=members/add', ['add_name' => 'Someone', 'add_email' => 'elsewhere@example.edu',
            'add_role' => 'officer', 'add_password' => 'temporary-pass-1'], '/index.php?r=members');
        $this->assertSame(422, $taken['status']);
        $this->assertTrue(str_contains($taken['body'], 'already has an account'), 'Existing accounts must be invited.');
        $ownerRole = $owner->post('/index.php?r=members/add', ['add_name' => 'Sneaky', 'add_email' => 'sneaky@example.edu',
            'add_role' => 'owner', 'add_password' => 'temporary-pass-1'], '/index.php?r=members');
        $this->assertSame(422, $ownerRole['status'], 'Nobody can be added as owner.');

        $added = $owner->post('/index.php?r=members/add', ['add_name' => 'Olu Officer', 'add_email' => 'Olu@Example.edu',
            'add_role' => 'officer', 'add_password' => 'temporary-pass-1'], '/index.php?r=members');
        $this->assertSame(303, $added['status']);
        $schoolId = (int) $pdo->query("SELECT m.school_id FROM school_members m JOIN users u ON u.id = m.user_id WHERE u.email = 'boss@example.edu'")->fetchColumn();
        $row = $pdo->query("SELECT u.must_change_password, m.role, m.school_id FROM users u JOIN school_members m ON m.user_id = u.id
            WHERE u.email = 'olu@example.edu'")->fetch();
        $this->assertSame(['1', 'officer', (string) $schoolId], [(string) $row['must_change_password'], $row['role'], (string) $row['school_id']]);
        $this->assertTrue(str_contains($owner->get('/index.php?r=members')['body'], 'Has not chosen a password yet'));

        // First sign-in: must choose their own password before anything else.
        $olu = new HttpClient();
        $first = $this->signIn($olu, 'olu@example.edu', 'temporary-pass-1');
        $this->assertTrue(str_contains(self::location($first), 'auth/new-password'));
        $blocked = $olu->get('/index.php?r=rooms');
        $this->assertTrue(str_contains(self::location($blocked), 'auth/new-password'), 'Other pages wait for the new password.');
        $same = $olu->post('/index.php?r=auth/new-password', ['password' => 'temporary-pass-1', 'password_confirm' => 'temporary-pass-1'],
            '/index.php?r=auth/new-password');
        $this->assertSame(422, $same['status'], 'The temporary password cannot be kept.');
        $olu->post('/index.php?r=auth/new-password', ['password' => 'my own secret 9', 'password_confirm' => 'my own secret 9'],
            '/index.php?r=auth/new-password');
        $this->assertSame(200, $olu->get('/index.php?r=rooms')['status'], 'Officer can now work.');
        $this->assertSame(403, $olu->get('/index.php?r=members')['status'], 'An officer cannot manage members.');
        $this->assertSame(403, $olu->post('/index.php?r=members/add', ['add_name' => 'X', 'add_email' => 'x@example.edu',
            'add_role' => 'admin', 'add_password' => 'temporary-pass-2'], '/index.php?r=rooms')['status'], 'Nor add them.');
        $this->assertSame('0', (string) $pdo->query("SELECT must_change_password FROM users WHERE email = 'olu@example.edu'")->fetchColumn());
        $this->dropAllTables($pdo);
    }
}
