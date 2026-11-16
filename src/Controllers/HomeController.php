<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Data\SettingsRepository;

/**
 * The home page and the About page, so anyone opening the site sees at once
 * what the app does. Signed-in users who open the bare address go straight
 * to their dashboard.
 */
final class HomeController extends Controller
{
    public function index(): void
    {
        if ($this->app->auth()->check() && $this->app->school() !== null) {
            $this->redirect('dashboard');
        }
        $this->renderPlain('home/index', [
            'title' => 'Class timetables made for you',
            'signupOpen' => (new SettingsRepository($this->app->db()))->get('signup_open', '1') === '1',
            'signedIn' => $this->app->auth()->check(),
        ]);
    }

    public function about(): void
    {
        $data = ['title' => 'About this app'];
        if ($this->app->auth()->check() && $this->app->school() !== null) {
            $this->render('home/about', $data);
            return;
        }
        $this->renderPlain('home/about', $data + ['standalone' => true]);
    }
}
