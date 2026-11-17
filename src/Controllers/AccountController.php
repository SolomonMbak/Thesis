<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\QrCode;
use App\Core\Session;
use App\Core\Theme;
use App\Core\Totp;
use App\Core\Validator;
use App\Data\RememberTokenRepository;
use App\Data\SettingsRepository;
use App\Data\TwoFactorRepository;
use App\Data\UserRepository;

/**
 * The signed-in user's own account: name, theme, password, two-step
 * sign-in and the browsers where they stay signed in (BUILD_SPEC 13.3).
 */
final class AccountController extends Controller
{
    /** The key being set up, kept in the session until the first code confirms it. */
    private const SETUP_KEY = '_2fa_setup';
    /** New recovery codes, shown once on the next page. */
    private const CODES_KEY = '_2fa_new_codes';

    public function profile(array $errors = []): void
    {
        $user = $this->app->auth()->user();
        $this->render('account/profile', [
            'title' => 'Your profile',
            'errors' => $errors,
            'themes' => Theme::CHOICES,
            'twoStepOn' => TwoFactorRepository::isOn($user),
            'codesLeft' => (new TwoFactorRepository($this->app->db()))->recoveryCodesLeft((int) $user['id']),
            'browsers' => (new RememberTokenRepository($this->app->db()))->forUser((int) $user['id']),
            'rememberDays' => Auth::rememberDays(),
        ]);
    }

    public function saveProfile(): void
    {
        $name = trim((string) $this->app->request->input('full_name', ''));
        $theme = (string) $this->app->request->input('theme', 'system');
        $errors = Validator::validate(['full_name' => $name, 'theme' => $theme],
            ['full_name' => 'required|maxlen:120', 'theme' => 'required|in:system,light,dark'], ['full_name' => 'Your name']);
        if ($errors) {
            http_response_code(422);
            $this->profile($errors);
            return;
        }
        $users = new UserRepository($this->app->db());
        $id = (int) $this->app->auth()->id();
        $users->updateProfile($id, $name);
        $users->setTheme($id, $theme);
        $this->app->auth()->refresh();
        $this->app->flash->success('Profile saved.');
        $this->redirect('account/profile');
    }

    public function savePassword(): void
    {
        $auth = $this->app->auth();
        $user = $auth->user();
        $current = (string) $this->app->request->input('current_password', '');
        $new = (string) $this->app->request->input('password', '');
        $errors = [];
        if (!password_verify($current, (string) $user['password_hash'])) {
            $errors['current_password'] = 'Your current password is not correct.';
        }
        $errors += Validator::validate(['password' => $new], ['password' => 'required|minlen:' . Auth::MIN_PASSWORD_LENGTH . '|maxlen:200'],
            ['password' => 'New password']);
        if (!isset($errors['password']) && $new !== (string) $this->app->request->input('password_confirm', '')) {
            $errors['password_confirm'] = 'The two new passwords do not match.';
        }
        if ($errors) {
            http_response_code(422);
            $this->profile($errors);
            return;
        }
        (new UserRepository($this->app->db()))->setPassword((int) $user['id'], $new);
        Session::regenerate();
        $auth->keepSessionAfterPasswordChange();
        // A new password ends "Keep me signed in" everywhere else; this browser stays signed in if it was.
        $kept = $auth->isRemembered();
        $auth->forgetAllBrowsers((int) $user['id']);
        if ($kept) {
            $auth->rememberThisBrowser();
        }
        $this->app->audit()->log((int) $user['id'], 'auth.change_password', 'user', (int) $user['id'], [], $this->app->request->ip());
        $this->app->flash->success('Password changed. Other browsers where you stayed signed in must sign in again.');
        $this->redirect('account/profile');
    }

    /** End "Keep me signed in" on every browser, this one included (the current session goes on until it times out or you sign out). */
    public function signOutEverywhere(): void
    {
        $id = (int) $this->app->auth()->id();
        $count = $this->app->auth()->forgetAllBrowsers($id);
        $this->app->log('auth.sign_out_everywhere', 'user', $id, ['browsers' => $count]);
        $this->app->flash->success($count === 0
            ? 'You were not kept signed in anywhere.'
            : "Done. {$count} browser(s) will ask for your password next time.");
        $this->redirect('account/profile');
    }

    // ---- Two-step sign-in ----

    /** Set-up page (QR code and key) when off; status, new codes and turning off when on. */
    public function twoStep(array $errors = []): void
    {
        $user = $this->app->auth()->user();
        if (TwoFactorRepository::isOn($user)) {
            $this->render('account/two_step', ['title' => 'Two-step sign-in', 'on' => true, 'errors' => $errors,
                'codesLeft' => (new TwoFactorRepository($this->app->db()))->recoveryCodesLeft((int) $user['id'])]);
            return;
        }
        $secret = $this->app->sessionGet(self::SETUP_KEY);
        if (!is_string($secret)) {
            $secret = Totp::newSecret();
            $this->app->sessionSet(self::SETUP_KEY, $secret);
        }
        $issuer = (string) (new SettingsRepository($this->app->db()))->get('platform_name', 'Timetable');
        $uri = Totp::uri($secret, (string) $user['email'], $issuer);
        $this->render('account/two_step', [
            'title' => 'Two-step sign-in',
            'on' => false,
            'errors' => $errors,
            'qr' => QrCode::svg($uri, 'QR code to scan with your authenticator app'),
            'key' => Totp::grouped($secret),
            'issuer' => $issuer,
        ]);
    }

    public function enableTwoStep(): void
    {
        $user = $this->app->auth()->user();
        $secret = $this->app->sessionGet(self::SETUP_KEY);
        if (TwoFactorRepository::isOn($user) || !is_string($secret)) {
            $this->redirect('account/two-step');
        }
        $errors = [];
        if (!password_verify((string) $this->app->request->input('password', ''), (string) $user['password_hash'])) {
            $errors['password'] = 'Your password is not correct.';
        }
        $step = Totp::match($secret, (string) $this->app->request->input('code', ''), time());
        if ($step === null) {
            $errors['code'] = 'That code does not match. Check that the app shows the account you just added, and type the 6 digits it shows now.';
        }
        if ($errors) {
            http_response_code(422);
            $this->twoStep($errors);
            return;
        }
        $repo = new TwoFactorRepository($this->app->db());
        $repo->enable((int) $user['id'], $secret, $step);
        $this->app->sessionSet(self::CODES_KEY, $repo->newRecoveryCodes((int) $user['id']));
        $this->app->sessionForget(self::SETUP_KEY);
        Session::regenerate();
        $this->app->log('auth.2fa_enabled', 'user', (int) $user['id']);
        $this->app->flash->success('Two-step sign-in is on. Next time you sign in, we will ask for a code from your app.');
        $this->redirect('account/two-step/codes');
    }

    public function disableTwoStep(): void
    {
        $user = $this->app->auth()->user();
        if (!password_verify((string) $this->app->request->input('password', ''), (string) $user['password_hash'])) {
            http_response_code(422);
            $this->twoStep(['password' => 'Your password is not correct.']);
            return;
        }
        (new TwoFactorRepository($this->app->db()))->disable((int) $user['id']);
        $this->app->log('auth.2fa_disabled', 'user', (int) $user['id']);
        $this->app->flash->success('Two-step sign-in is off. You sign in with your password only. You can remove the account from your app.');
        $this->redirect('account/profile');
    }

    public function newRecoveryCodes(): void
    {
        $user = $this->app->auth()->user();
        if (!TwoFactorRepository::isOn($user)) {
            $this->redirect('account/two-step');
        }
        if (!password_verify((string) $this->app->request->input('codes_password', ''), (string) $user['password_hash'])) {
            http_response_code(422);
            $this->twoStep(['codes_password' => 'Your password is not correct.']);
            return;
        }
        $codes = (new TwoFactorRepository($this->app->db()))->newRecoveryCodes((int) $user['id']);
        $this->app->sessionSet(self::CODES_KEY, $codes);
        $this->app->log('auth.2fa_new_codes', 'user', (int) $user['id']);
        $this->app->flash->success('New recovery codes made. The old ones no longer work.');
        $this->redirect('account/two-step/codes');
    }

    /** Shows new recovery codes once; a reload shows nothing more. */
    public function showRecoveryCodes(): void
    {
        $codes = $this->app->sessionGet(self::CODES_KEY);
        if (!is_array($codes)) {
            $this->redirect('account/two-step');
        }
        $this->app->sessionForget(self::CODES_KEY);
        $this->render('account/recovery_codes', ['title' => 'Your recovery codes', 'codes' => $codes,
            'email' => $this->app->auth()->user()['email']]);
    }
}
