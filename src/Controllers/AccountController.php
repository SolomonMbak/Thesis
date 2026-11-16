<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Theme;
use App\Core\Validator;
use App\Data\UserRepository;

/**
 * The signed-in user's own account: name, theme and password.
 */
final class AccountController extends Controller
{
    public function profile(array $errors = []): void
    {
        $this->render('account/profile', [
            'title' => 'Your profile',
            'errors' => $errors,
            'themes' => Theme::CHOICES,
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
        $user = $this->app->auth()->user();
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
        \App\Core\Session::regenerate();
        $this->app->audit()->log((int) $user['id'], 'auth.change_password', 'user', (int) $user['id'], [], $this->app->request->ip());
        $this->app->flash->success('Password changed.');
        $this->redirect('account/profile');
    }
}
