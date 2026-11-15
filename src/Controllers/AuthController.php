<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Validator;

/**
 * Sign in and sign out.
 */
final class AuthController extends Controller
{
    public function showLogin(): void
    {
        if ($this->app->auth()->check()) {
            $this->redirect('dashboard');
        }
        $this->renderPlain('auth/login', ['title' => 'Sign in', 'email' => '', 'errors' => []]);
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
                $this->app->flash->success('Welcome back.');
                $this->redirect('dashboard');
            }
            $errors['form'] = $message;
        }

        http_response_code(422);
        $this->renderPlain('auth/login', ['title' => 'Sign in', 'email' => $email, 'errors' => $errors]);
    }

    public function logout(): void
    {
        $this->app->auth()->logout($this->app->request->ip());
        $this->redirect('auth/login');
    }
}
