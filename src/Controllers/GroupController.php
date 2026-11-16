<?php
declare(strict_types=1);

namespace App\Controllers;

/**
 * Levels (student groups): students who take their classes together. A
 * level never has two classes at the same time (hard rule H3).
 */
final class GroupController extends CrudController
{
    protected function definition(): array
    {
        return [
            'route' => 'groups',
            'title' => 'Levels',
            'singular' => 'level',
            'plural' => 'levels',
            'table' => 'student_groups',
            'entity' => 'group',
            'add' => 'Add level',
            'select' => 'SELECT t.*, (SELECT COUNT(*) FROM courses c JOIN semesters s ON s.id = c.semester_id
                          WHERE c.group_id = t.id AND s.is_active = 1) AS course_count FROM student_groups t',
            'order' => 't.department, t.code',
            'search' => ['t.code', 't.name', 't.department'],
            'fields' => [
                'code' => ['label' => 'Code', 'rules' => 'required|code|maxlen:20', 'unique' => true,
                    'help' => 'A short name shown on the timetable, for example 100L or CSC300.'],
                'name' => ['label' => 'Name', 'rules' => 'required|maxlen:100', 'help' => 'For example 100 Level Computer Science.'],
                'department' => ['label' => 'Department (optional)', 'rules' => 'maxlen:100', 'nullable' => true,
                    'help' => 'Only needed if your school timetables several departments, for example Computer Science.'],
                'size' => ['label' => 'Number of students', 'rules' => 'required|int|min:1|max:5000', 'type' => 'number',
                    'help' => 'Used to choose rooms that are big enough. For practicals in smaller lab groups, set a class size on the course instead.'],
            ],
            'columns' => ['code' => 'Code', 'name' => 'Name', 'department' => 'Department', 'size' => 'Students', 'course_count' => 'Courses this semester'],
            'empty' => 'No levels yet. Add one for each group of students who take classes together, for example 100L to 400L.',
            'in_use' => 'This level has courses, so it cannot be deleted. Move or delete its courses first.',
        ];
    }
}
