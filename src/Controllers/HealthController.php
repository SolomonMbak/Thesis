<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Migrator;
use App\Services\HealthService;

/**
 * Platform: health page and "Apply updates" (platform administrators only).
 */
final class HealthController extends Controller
{
    public function index(): void
    {
        $health = new HealthService($this->app->db());
        $this->render('health/index', [
            'title' => 'Health',
            'checks' => $health->checks(),
            'cron' => $health->cronLines(),
        ]);
    }

    public function migrate(): void
    {
        try {
            $applied = (new Migrator($this->app->db(), APP_ROOT . '/database/migrations'))->migrate();
        } catch (\RuntimeException $ex) {
            $this->app->flash->error($ex->getMessage());
            $this->redirect('health');
        }
        $this->app->audit()->log($this->app->auth()->id(), 'system.migrate', null, null, ['applied' => $applied], $this->app->request->ip());
        $this->app->flash->success($applied ? 'Applied: ' . implode(', ', $applied) . '.' : 'The database was already up to date.');
        $this->redirect('health');
    }
}
