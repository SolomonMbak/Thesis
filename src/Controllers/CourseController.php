<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Data\TableRepository;

/**
 * Courses of the active semester: which level takes it, who teaches it and
 * for how many hours a week. Class sessions follow automatically.
 */
final class CourseController extends CrudController
{
    private ?array $semester = null;
    private bool $semesterLoaded = false;

    protected function definition(): array
    {
        return [
            'route' => 'courses',
            'title' => 'Courses',
            'singular' => 'course',
            'plural' => 'courses',
            'table' => 'courses',
            'entity' => 'course',
            'add' => 'Add course',
            'select' => 'SELECT t.*, g.code AS group_code, l.short_name AS lecturer_name
                         FROM courses t
                         JOIN student_groups g ON g.id = t.group_id
                         JOIN lecturers l ON l.id = t.lecturer_id',
            'order' => 'g.code, t.code',
            'search' => ['t.code', 't.title', 'l.short_name'],
            'filters' => ['group_id' => 'Level'],
            'fields' => [
                'code' => ['label' => 'Course code', 'rules' => 'required|code|maxlen:20', 'unique' => true, 'help' => 'For example CSC 201.'],
                'title' => ['label' => 'Title', 'rules' => 'required|maxlen:150'],
                'group_id' => ['label' => 'Level', 'rules' => 'required|int', 'type' => 'select',
                    'help' => 'The students who take this course. Two courses of one level are never at the same time.'],
                'lecturer_id' => ['label' => 'Lecturer', 'rules' => 'required|int', 'type' => 'select'],
                'credit_units' => ['label' => 'Credit units', 'rules' => 'required|int|min:0|max:20', 'type' => 'number', 'default' => '2',
                    'help' => 'For your records only. The timetable uses the contact hours below.'],
                'hours_per_week' => ['label' => 'Contact hours per week', 'rules' => 'required|int|min:1|max:20', 'type' => 'number', 'default' => '2',
                    'help' => 'Each hour becomes one class in the week. A 3-hour lecture becomes three 1-hour classes, spread over different days where possible.'],
                'needs_lab' => ['label' => 'Practical (must be taught in a lab)', 'rules' => 'in:0,1', 'type' => 'checkbox',
                    'help' => 'Practicals are placed only in laboratories, as one block of up to 3 hours.'],
                'allow_two_hour_block' => ['label' => 'Allow one 2-hour block', 'rules' => 'in:0,1', 'type' => 'checkbox',
                    'help' => 'Tick if two of the hours should be taught back to back.'],
                'class_size_override' => ['label' => 'Class size (optional)', 'rules' => 'int|min:1|max:5000', 'type' => 'number',
                    'nullable' => true, 'help' => 'Leave blank to use the size of the level. For a practical taught in lab groups, enter the size of one lab group.'],
            ],
            'columns' => ['code' => 'Code', 'title' => 'Title', 'group_code' => 'Level', 'lecturer_name' => 'Lecturer',
                'hours_per_week' => 'Hours', 'needs_lab' => 'Practical', 'allow_two_hour_block' => '2-hour block'],
            'bool_columns' => ['needs_lab', 'allow_two_hour_block'],
            'empty' => 'No courses in this semester yet. Each course links a level and a lecturer to its weekly hours.',
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

    /** Levels and lecturers of this school only, so a course can never link to another school's records. */
    protected function options(): array
    {
        return [
            'group_id' => (new TableRepository($this->app->db(), $this->school(), 'student_groups'))
                ->pairs("CONCAT(t.code, ' - ', t.name)", 't.code'),
            'lecturer_id' => (new TableRepository($this->app->db(), $this->school(), 'lecturers'))
                ->pairs("CONCAT(t.full_name, ' (', t.staff_no, ')')", 't.full_name', 't.is_active = 1'),
        ];
    }

    protected function notice(): ?string
    {
        if (!$this->semester()) {
            return 'There is no active semester. An administrator must create one under Manage, Semesters before courses can be added.';
        }
        $options = $this->options();
        if (!$options['group_id'] || !$options['lecturer_id']) {
            return 'Add at least one level and one lecturer first: every course needs both.';
        }
        return null;
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

    private function requireSemester(): void
    {
        if (!$this->semester()) {
            $this->app->flash->error('Create and activate a semester first (Manage, Semesters).');
            $this->redirect('courses');
        }
    }

    private function semester(): ?array
    {
        if (!$this->semesterLoaded) {
            $this->semester = $this->activeSemester();
            $this->semesterLoaded = true;
        }
        return $this->semester;
    }
}
