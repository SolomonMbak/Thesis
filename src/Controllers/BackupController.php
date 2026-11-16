<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Services\BackupService;

/**
 * Platform: create, list and download backups of the whole database
 * (every school). Platform administrators only, enforced by the router.
 */
final class BackupController extends Controller
{
    public function index(): void
    {
        $this->render('backups/index', [
            'title' => 'Backups',
            'backups' => (new BackupService($this->app->db()))->list(),
            'keep' => BackupService::KEEP,
        ]);
    }

    public function create(): void
    {
        set_time_limit(300);
        try {
            $result = (new BackupService($this->app->db()))->create();
        } catch (\Throwable $ex) {
            \App\Core\Logger::exception($ex);
            $this->app->flash->error('The backup could not be created: ' . $ex->getMessage() . ' Check that storage/backups is writable.');
            $this->redirect('backups');
        }
        $this->app->audit()->log($this->app->auth()->id(), 'backup.create', null, null, $result);
        $this->app->flash->success(sprintf('Backup %s created (%s, %.1f KB).', $result['file'],
            $result['method'] === 'mysqldump' ? 'mysqldump' : 'PHP export', $result['bytes'] / 1024));
        $this->redirect('backups');
    }

    public function download(): void
    {
        $name = (string) $this->app->request->query('file', '');
        $path = (new BackupService($this->app->db()))->path($name);
        if ($path === null) {
            throw new HttpException(404, 'That backup does not exist.');
        }
        $this->app->audit()->log($this->app->auth()->id(), 'backup.download', null, null, ['file' => $name]);
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
    }
}
