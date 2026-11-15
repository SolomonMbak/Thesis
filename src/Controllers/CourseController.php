<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Data\SemesterRepository;

/**
 * Courses of the active semester. Changing a course changes the sessions it
 * needs, so every change reminds the user to rebuild sessions.
 */
final class CourseController extends CrudController
{
    private ?array $semester = null;

    protected function definition(): array
    {
        return [
            'route' => 'courses',
            'title' => 'Courses',
            'singular' => 'course',
            'table' => 'courses',
            'entity' => 'course',
            'select' => 'SELECT t.*, g.code AS group_code, l.short_name AS lecturer_name
                         FROM courses t
                         JOIN student_groups g ON g.id = t.group_id
                         JOIN lecturers l ON l.id = t.lecturer_id',
            'order' => 'g.code, t.code',
            'search' => ['t.code', 't.title', 'l.short_name'],
            'filters' => ['group_id' => 'Group'],
            'fields' => [
                'code' => ['label' => 'Course code', 'rules' => 'required|code|maxlen:20', 'unique' => true],
                'title' => ['label' => 'Title', 'rules' => 'required|maxlen:150'],
                'group_id' => ['label' => 'Student group', 'rules' => 'required|int', 'type' => 'select'],
                'lecturer_id' => ['label' => 'Lecturer', 'rules' => 'required|int', 'type' => 'select'],
                'credit_units' => ['label' => 'Credit units', 'rules' => 'required|int|min:0|max:20', 'type' => 'number', 'default' => '2'],
                'hours_per_week' => ['label' => 'Contact hours per week', 'rules' => 'required|int|min:1|max:20', 'type' => 'number', 'default' => '2'],
                'needs_lab' => ['label' => 'Practical (must be taught in a lab)', 'rules' => 'in:0,1', 'type' => 'checkbox'],
                'allow_two_hour_block' => ['label' => 'Allow one 2-hour block', 'rules' => 'in:0,1', 'type' => 'checkbox'],
                'class_size_override' => ['label' => 'Class size override', 'rules' => 'int|min:1|max:5000', 'type' => 'number',
                    'nullable' => true, 'help' => 'Leave blank to use the group size. For practicals taught in lab groups, enter the lab group size.'],
            ],
            'columns' => ['code' => 'Code', 'title' => 'Title', 'group_code' => 'Group', 'lecturer_name' => 'Lecturer',
                'hours_per_week' => 'Hours', 'needs_lab' => 'Practical', 'allow_two_hour_block' => '2-hour block'],
            'bool_columns' => ['needs_lab', 'allow_two_hour_block'],
            'empty' => 'No courses in this semester yet. Add one, import a CSV file, or copy courses from an earlier semester.',
            'in_use' => 'Saved timetables use this course, so it cannot be deleted.',
        ];
    }

    protected function scope(): array
    {
        return ['t.semester_id = ?', [(int) ($this->semester()['id'] ?? 0)]];
    }

    protected function fixedValues(): array
    {
        return ['semester_id' => (int) ($this->semester()['id'] ?? 0)];
    }

    protected function options(): array
    {
        $pdo = $this->app->db();
        return [
            'group_id' => $pdo->query("SELECT id, CONCAT(code, ' - ', name) FROM student_groups ORDER BY code")->fetchAll(\PDO::FETCH_KEY_PAIR),
            'lecturer_id' => $pdo->query("SELECT id, CONCAT(full_name, ' (', staff_no, ')') FROM lecturers WHERE is_active = 1 ORDER BY full_name")->fetchAll(\PDO::FETCH_KEY_PAIR),
        ];
    }

    protected function notice(): ?string
    {
        return $this->semester() ? null : 'There is no active semester. An administrator must create one before courses can be added.';
    }

    public function create(): void
    {
        $this->requireSemester();
        parent::create();
    }

    public function store(): void
    {
        $this->requireSemester();
        parent::store();
    }

    protected function afterChange(string $action, int $id): void
    {
        $this->app->flash->info('Remember to rebuild class sessions so the change is used in the next run.');
    }

    private function requireSemester(): void
    {
        if (!$this->semester()) {
            $this->app->flash->error('Create and activate a semester first.');
            $this->redirect('courses');
        }
    }

    private function semester(): ?array
    {
        return $this->semester ??= (new SemesterRepository($this->app->db()))->active();
    }
}
