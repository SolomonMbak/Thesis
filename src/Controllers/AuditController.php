<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Data\AuditRepository;

/**
 * Activity log of the school: who did what and when, with filters and paging.
 */
final class AuditController extends Controller
{
    private const PER_PAGE = 50;

    public function index(): void
    {
        $req = $this->app->request;
        $action = preg_replace('/[^a-z_]/', '', (string) $req->query('action', ''));
        $userId = (int) $req->query('user', 0);
        $page = max(1, (int) $req->query('page', 1));
        $log = new AuditRepository($this->app->db(), $this->school());
        $result = $log->page($action, $userId, $page, self::PER_PAGE);
        $pages = max(1, (int) ceil($result['total'] / self::PER_PAGE));
        if ($page > $pages) {
            $page = $pages;
            $result = $log->page($action, $userId, $page, self::PER_PAGE);
        }
        $this->render('audit/index', [
            'title' => 'Activity log',
            'rows' => $result['rows'],
            'users' => $log->people(),
            'actions' => $log->areas(),
            'action' => $action,
            'userId' => $userId,
            'page' => $page,
            'pages' => $pages,
            'total' => $result['total'],
        ]);
    }
}
