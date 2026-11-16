<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\SchoolContext;
use App\Data\CalendarRepository;
use App\Data\JobRepository;
use App\Data\SchoolRepository;
use App\Data\SemesterRepository;
use App\Services\CalendarService;
use App\Services\TimetableService;

/**
 * Public, read-only timetable pages, no sign-in (BUILD_SPEC 10.4):
 *   /s/{slug}                          the school page: levels, lecturers and rooms
 *   /s/{slug}/level|lecturer|room/{id} one grid
 * The school is found by its slug and only its published timetable of the
 * active semester is shown. Ids of other schools are simply not found.
 */
final class PublicController extends Controller
{
    public function page(): void
    {
        $row = (new SchoolRepository($this->app->db()))->findBySlug((string) $this->app->request->query('slug', ''));
        if ($row === null || $row['status'] !== 'active') {
            http_response_code($row === null ? 404 : 200);
            $this->renderPlain('public/unavailable', ['title' => 'Timetable not available']);
            return;
        }
        $school = SchoolContext::system($row);
        $semester = (new SemesterRepository($this->app->db(), $school))->active();
        $job = $semester ? (new JobRepository($this->app->db(), $school))->published((int) $semester['id']) : null;
        $data = ['title' => $school->name() . ' timetable', 'schoolRow' => $row, 'semester' => $semester, 'job' => $job,
            'options' => ['level' => [], 'lecturer' => [], 'room' => []], 'view' => '', 'selectedId' => 0, 'layout' => [], 'rows' => [],
            'logoUrl' => $row['logo_path'] ? url('school/logo', ['s' => $row['slug'], 'v' => substr(md5((string) $row['logo_path']), 0, 8)]) : null,
            'publicPage' => true];
        if ($job) {
            $service = new TimetableService($this->app->db(), $school);
            $entries = $service->entries((int) $job['id']);
            $data['options'] = TimetableService::options($entries);
            $view = (string) $this->app->request->query('view', '');
            $id = (int) $this->app->request->query('id', 0);
            if (isset(TimetableService::VIEWS[$view])) {
                if (!isset($data['options'][$view][$id])) {
                    http_response_code(404);
                    $this->renderPlain('public/unavailable', ['title' => 'Timetable not found', 'notFound' => true, 'slug' => $row['slug']]);
                    return;
                }
                $grid = $service->grid($entries, $view, $id);
                $data = ['view' => $view, 'selectedId' => $id, 'rows' => $grid['rows'], 'layout' => TimetableService::layout($grid),
                    'title' => $data['options'][$view][$id] . ' | ' . $school->name()] + $data;
                // Month view: the repeating week on the real dates of a month, holidays left out (BUILD_SPEC 10.7).
                $data['mode'] = $this->app->request->query('mode') === 'month' ? 'month' : 'week';
                [$year, $month] = CalendarService::pickMonth($this->app->request->query('month'), $semester);
                $key = ['level' => 'group_id', 'lecturer' => 'lecturer_id', 'room' => 'room_id'][$view];
                $data['month'] = CalendarService::month($year, $month, array_values(array_filter($entries, fn ($e) => (int) $e[$key] === $id)),
                    $grid['rows'], $semester, (new CalendarRepository($this->app->db(), $school))->closureMap((int) $semester['id']));
            }
        }
        $this->renderPlain('public/school', $data);
    }

    /** Old single-school address: there is no single school any more. */
    public function legacy(): void
    {
        $this->renderPlain('public/unavailable', ['title' => 'Timetable moved', 'moved' => true]);
    }
}
