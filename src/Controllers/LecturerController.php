<?php
declare(strict_types=1);

namespace App\Controllers;

/**
 * Lecturers. A lecturer is never in two places at once (hard rule H1).
 */
final class LecturerController extends CrudController
{
    protected function definition(): array
    {
        return [
            'route' => 'lecturers',
            'title' => 'Lecturers',
            'singular' => 'lecturer',
            'plural' => 'lecturers',
            'table' => 'lecturers',
            'entity' => 'lecturer',
            'add' => 'Add lecturer',
            'select' => 'SELECT t.*, (SELECT COUNT(*) FROM courses c JOIN semesters s ON s.id = c.semester_id
                          WHERE c.lecturer_id = t.id AND s.is_active = 1) AS course_count FROM lecturers t',
            'order' => 't.full_name',
            'search' => ['t.staff_no', 't.full_name', 't.short_name'],
            'fields' => [
                'staff_no' => ['label' => 'Staff number', 'rules' => 'required|maxlen:20', 'unique' => true,
                    'help' => 'Used to match lecturers when you import courses or availability from a spreadsheet.'],
                'title' => ['label' => 'Title', 'rules' => 'maxlen:20', 'help' => 'For example Dr., Prof., Mr. or Mrs.'],
                'full_name' => ['label' => 'Full name', 'rules' => 'required|maxlen:120'],
                'short_name' => ['label' => 'Short name', 'rules' => 'required|maxlen:30',
                    'help' => 'Shown in timetable cells, where space is short, for example A. Bello.'],
                'email' => ['label' => 'Email (optional)', 'rules' => 'email|maxlen:120', 'type' => 'email', 'nullable' => true],
                'is_active' => ['label' => 'Active', 'rules' => 'in:0,1', 'type' => 'checkbox', 'default' => '1',
                    'help' => 'Untick for staff on leave. Their courses must then be given to someone else before generating.'],
            ],
            'columns' => ['staff_no' => 'Staff no', 'title' => 'Title', 'full_name' => 'Name', 'short_name' => 'Short name',
                'course_count' => 'Courses this semester', 'is_active' => 'Active'],
            'bool_columns' => ['is_active'],
            'row_links' => [['availability', 'lecturer', 'Availability']],
            'empty' => 'No lecturers yet. Add the staff who teach, so the timetable never puts one lecturer in two places at once.',
            'in_use' => 'This lecturer teaches courses, so it cannot be deleted. Give the courses to another lecturer or mark this one inactive.',
        ];
    }
}
