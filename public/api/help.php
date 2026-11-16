<?php
/**
 * POST item=tip_rooms|tour_done|skip_availability (with X-CSRF-Token header)
 * Records that the user dismissed a first-visit tip, finished or skipped
 * the product tour, or skipped an optional setup step (help_progress).
 */
declare(strict_types=1);

use App\Core\Api;
use App\Core\Response;
use App\Data\HelpProgressRepository;

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$app = Api::boot('POST');
$item = (string) $app->request->input('item', '');
if (!preg_match('/^(tip_[a-z0-9_]{1,50}|tour_done)$/', $item)) {
    Response::json(['error' => 'Unknown help item.'], 422);
}
(new HelpProgressRepository($app->db(), $app->school(), (int) $app->auth()->id()))->mark($item);
Response::json(['ok' => true]);
