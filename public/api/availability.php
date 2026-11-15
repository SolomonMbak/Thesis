<?php
/**
 * POST lecturer=ID&timeslot=ID (with X-CSRF-Token header)
 * Cycles one availability cell and returns {"status": "preferred"}.
 */
declare(strict_types=1);

use App\Core\Api;
use App\Core\Logger;
use App\Core\Response;
use App\Services\AvailabilityService;

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$app = Api::boot('POST');
$lecturerId = (int) $app->request->input('lecturer', 0);
$timeslotId = (int) $app->request->input('timeslot', 0);

try {
    $status = (new AvailabilityService($app->db()))->cycle($lecturerId, $timeslotId);
    $app->log('availability.set', 'lecturer', $lecturerId, ['timeslot' => $timeslotId, 'status' => $status]);
    Response::json(['status' => $status]);
} catch (\InvalidArgumentException $ex) {
    Response::json(['error' => $ex->getMessage()], 422);
} catch (\Throwable $ex) {
    Logger::exception($ex);
    Response::json(['error' => 'The change could not be saved. Please try again.'], 500);
}
