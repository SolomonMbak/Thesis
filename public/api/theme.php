<?php
/**
 * POST theme=system|light|dark (with X-CSRF-Token header)
 * Saves the signed-in user's theme choice to users.theme (BUILD_SPEC 10.5).
 * Public visitors keep their choice in localStorage only and never call this.
 */
declare(strict_types=1);

use App\Core\Api;
use App\Core\Response;
use App\Core\Router;
use App\Core\Theme;
use App\Data\UserRepository;

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$app = Api::boot('POST', Router::AUTH);
$theme = (string) $app->request->input('theme', '');
if (!isset(Theme::CHOICES[$theme])) {
    Response::json(['error' => 'Choose System, Light or Dark.'], 422);
}
(new UserRepository($app->db()))->setTheme((int) $app->auth()->id(), $theme);
Response::json(['theme' => $theme]);
