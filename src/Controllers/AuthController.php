<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\RateLimiter;
use App\Core\Validator;
use App\Data\SchoolRepository;
use App\Data\SettingsRepository;
use App\Data\TokenRepository;
use App\Data\UserRepository;
use App\Services\AccountMail;

/**
 * Sign in, sign out, sign up (create a school account), email verification
 * and password reset (BUILD_SPEC section 2).
 */
final class AuthController extends Controller
{
    public function showLogin(): void
    {
        if ($this->app->auth()->check()) {
            $this->redirect('dashboard');
        }
        $this->renderPlain('auth/login', ['title' => 'Sign in', 'email' => '', 'errors' => [], 'signupOpen' => $this->signupOpen(),
            'invite' => $this->inviteToken((string) $this->app->request->query('invite', ''))]);
    }

    public function login(): void
    {
        $request = $this->app->request;
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');

        $errors = Validator::validate(
            ['email' => $email, 'password' => $password],
            ['email' => 'required|email|maxlen:120', 'password' => 'required|maxlen:200']
        );
        if (!$errors) {
            $message = $this->app->auth()->attempt($email, $password, $request->ip());
            if ($message === null) {
                $invite = $this->inviteToken((string) $request->input('invite', ''));
                if ($invite !== '') {
                    $this->redirect('invite/accept', ['token' => $invite]);
                }
                $this->app->flash->success('Welcome back.');
                $this->redirect($this->afterSignIn());
            }
            $errors['form'] = $message;
        }
        http_response_code(422);
        $this->renderPlain('auth/login', ['title' => 'Sign in', 'email' => $email, 'errors' => $errors, 'signupOpen' => $this->signupOpen(),
            'invite' => $this->inviteToken((string) $request->input('invite', ''))]);
    }

    public function logout(): void
    {
        $this->app->auth()->logout($this->app->request->ip());
        $this->redirect('auth/login');
    }

    public function showSignup(array $errors = [], array $old = []): void
    {
        if (!$this->signupOpen()) {
            $this->renderPlain('auth/signup_closed', ['title' => 'Sign-up is closed']);
            return;
        }
        $this->renderPlain('auth/signup', [
            'title' => 'Create a school account',
            'errors' => $errors,
            'old' => $old + ['full_name' => '', 'email' => '', 'school_name' => '', 'school_type' => 'university', 'department_label' => ''],
            'types' => SchoolRepository::TYPES,
        ]);
    }

    /**
     * Create the user, the school (unique slug, default settings, 50 periods,
     * one active semester) and the owner membership, send the verification
     * email, sign in and open the Setup Guide.
     */
    public function signup(): void
    {
        if (!$this->signupOpen()) {
            $this->redirect('auth/signup');
        }
        $in = $this->input(['full_name', 'email', 'password', 'password_confirm', 'school_name', 'school_type', 'department_label']);
        $in['email'] = strtolower($in['email']);
        $errors = Validator::validate($in, [
            'full_name' => 'required|maxlen:120',
            'email' => 'required|email|maxlen:120',
            'password' => 'required|minlen:' . Auth::MIN_PASSWORD_LENGTH . '|maxlen:200',
            'school_name' => 'required|maxlen:150',
            'school_type' => 'required|in:' . implode(',', array_keys(SchoolRepository::TYPES)),
            'department_label' => 'maxlen:150',
        ], ['full_name' => 'Your name', 'email' => 'Email address', 'school_name' => 'School name', 'school_type' => 'Type of school']);
        if (!isset($errors['password']) && $in['password'] !== $in['password_confirm']) {
            $errors['password_confirm'] = 'The two passwords do not match. Type the same password twice.';
        }
        $users = new UserRepository($this->app->db());
        if (!isset($errors['email']) && $users->findByEmail($in['email'])) {
            $errors['email'] = 'An account with this email already exists. Sign in instead, then use "Create another school" in the top bar.';
        }
        if (!$errors && $this->throttle()->hit('signup|' . $this->app->request->ip())) {
            $errors['form'] = 'Too many accounts were created from this network in the last hour. Please try again later.';
        }
        if ($errors) {
            http_response_code(422);
            $this->showSignup($errors, $in);
            return;
        }

        $pdo = $this->app->db();
        $pdo->beginTransaction();
        try {
            $userId = $users->create($in['full_name'], $in['email'], $in['password']);
            $schoolId = (new SchoolRepository($pdo))->create([
                'name' => $in['school_name'], 'school_type' => $in['school_type'], 'department_label' => $in['department_label'],
            ], $userId);
            $pdo->commit();
        } catch (\Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
        $this->app->auth()->loginAs($userId);
        $this->app->switchSchool($schoolId);
        $this->app->log('school.create', 'school', $schoolId, ['via' => 'signup']);
        $sent = (new AccountMail($pdo))->sendVerification($users->find($userId));
        $this->app->flash->success('Your school account is ready. Follow the Setup Guide below: it takes you from an empty workspace to a published timetable.');
        if (!$sent) {
            $this->app->flash->info('We could not send the verification email just now. You can send it again from the banner at the top.');
        }
        $this->redirect('setup');
    }

    public function verify(): void
    {
        $tokens = new TokenRepository($this->app->db());
        $token = $tokens->check((string) $this->app->request->query('token', ''), 'verify_email');
        if ($token === null || !$tokens->consume((int) $token['id'])) {
            $this->renderPlain('auth/message', ['title' => 'Link not valid', 'heading' => 'This link does not work any more',
                'message' => 'Verification links work once and for 7 days. Sign in and use "Send the email again" in the banner to get a new one.']);
            return;
        }
        (new UserRepository($this->app->db()))->markVerified((int) $token['user_id']);
        $this->app->auth()->refresh();
        $this->app->flash->success('Thank you, your email address is confirmed.');
        $this->redirect($this->app->auth()->check() ? 'dashboard' : 'auth/login');
    }

    public function verifyRequired(): void
    {
        if (!$this->app->auth()->mustVerify()) {
            $this->redirect('dashboard');
        }
        $this->renderPlain('auth/verify_required', ['title' => 'Confirm your email', 'email' => $this->app->auth()->user()['email']]);
    }

    public function resendVerification(): void
    {
        $user = $this->app->auth()->user();
        if ($this->app->auth()->isVerified()) {
            $this->app->flash->info('Your email address is already confirmed.');
        } elseif ($this->throttle()->hit('verify|' . $user['id'])) {
            $this->app->flash->error('Several emails were sent in the last hour. Check your inbox and spam folder, or try again later.');
        } elseif ((new AccountMail($this->app->db()))->sendVerification($user)) {
            $this->app->flash->success("A new confirmation email is on its way to {$user['email']}. Check the spam folder too.");
        } else {
            $this->app->flash->error('The email could not be sent. Ask the platform administrator to check the email settings.');
        }
        $this->redirect($this->app->auth()->mustVerify() ? 'auth/verify-required' : 'dashboard');
    }

    public function showForgot(): void
    {
        $this->renderPlain('auth/forgot', ['title' => 'Forgot your password?', 'email' => '', 'sent' => false]);
    }

    /** Always answer the same way, so the page does not reveal which emails have accounts. */
    public function forgot(): void
    {
        $email = strtolower(trim((string) $this->app->request->input('email', '')));
        $limited = $this->throttle()->hit('forgot|' . $this->app->request->ip());
        $user = filter_var($email, FILTER_VALIDATE_EMAIL) ? (new UserRepository($this->app->db()))->findByEmail($email) : null;
        if (!$limited && $user && (int) $user['is_active'] === 1) {
            (new AccountMail($this->app->db()))->sendPasswordReset($user);
            $this->app->audit()->log((int) $user['id'], 'auth.reset_requested', 'user', (int) $user['id'], [], $this->app->request->ip());
        }
        $this->renderPlain('auth/forgot', ['title' => 'Forgot your password?', 'email' => $email, 'sent' => true]);
    }

    public function showReset(array $errors = []): void
    {
        $raw = (string) ($this->app->request->query('token') ?? $this->app->request->input('token', ''));
        $valid = (new TokenRepository($this->app->db()))->check($raw, 'reset_password') !== null;
        $this->renderPlain('auth/reset', ['title' => 'Choose a new password', 'token' => $raw, 'valid' => $valid, 'errors' => $errors]);
    }

    public function reset(): void
    {
        $tokens = new TokenRepository($this->app->db());
        $raw = (string) $this->app->request->input('token', '');
        $password = (string) $this->app->request->input('password', '');
        $errors = Validator::validate(['password' => $password], ['password' => 'required|minlen:' . Auth::MIN_PASSWORD_LENGTH . '|maxlen:200']);
        if (!$errors && $password !== (string) $this->app->request->input('password_confirm', '')) {
            $errors['password_confirm'] = 'The two passwords do not match.';
        }
        $token = $tokens->check($raw, 'reset_password');
        if ($errors || $token === null) {
            http_response_code(422);
            $this->renderPlain('auth/reset', ['title' => 'Choose a new password', 'token' => $raw, 'valid' => $token !== null, 'errors' => $errors]);
            return;
        }
        if ($tokens->consume((int) $token['id'])) {
            $users = new UserRepository($this->app->db());
            $users->setPassword((int) $token['user_id'], $password);
            // Opening the emailed link proves the address too.
            $users->markVerified((int) $token['user_id']);
            $this->app->audit()->log((int) $token['user_id'], 'auth.reset_password', 'user', (int) $token['user_id'], [], $this->app->request->ip());
        }
        $this->app->flash->success('Your password has been changed. Sign in with the new password.');
        $this->redirect('auth/login');
    }

    /** After sign-in: a member of no school creates one; a new owner continues the Setup Guide. */
    private function afterSignIn(): string
    {
        $school = $this->app->school();
        if ($school === null) {
            return 'schools/new';
        }
        return empty($school->school['setup_completed_at']) && $school->can('officer') ? 'setup' : 'dashboard';
    }

    /** An invitation token to return to after signing in; only a well-formed token is kept (never a free URL). */
    private function inviteToken(string $raw): string
    {
        return preg_match('/^[a-f0-9]{64}$/', $raw) ? $raw : '';
    }

    private function signupOpen(): bool
    {
        return (new SettingsRepository($this->app->db()))->get('signup_open', '1') === '1';
    }

    private function throttle(): RateLimiter
    {
        return new RateLimiter($this->app->db(), 5, 60);
    }

    private function input(array $keys): array
    {
        $in = [];
        foreach ($keys as $key) {
            $value = $this->app->request->input($key, '');
            $in[$key] = is_string($value) ? (str_starts_with($key, 'password') ? $value : trim($value)) : '';
        }
        return $in;
    }
}
