<?php
declare(strict_types=1);

namespace App\Controllers;

final class GroupController extends CrudController
{
    protected function definition(): array
    {
        return [
            'route' => 'groups',
            'title' => 'Student groups',
            'singular' => 'group',
            'table' => 'student_groups',
            'entity' => 'group',
            'select' => 'SELECT t.* FROM student_groups t',
            'order' => 't.code',
            'search' => ['t.code', 't.name'],
            'fields' => [
                'code' => ['label' => 'Code', 'rules' => 'required|code|maxlen:20', 'unique' => true, 'help' => 'For example 100L. Used to name the level timetable.'],
                'name' => ['label' => 'Name', 'rules' => 'required|maxlen:100'],
                'size' => ['label' => 'Number of students', 'rules' => 'required|int|min:1|max:5000', 'type' => 'number'],
            ],
            'columns' => ['code' => 'Code', 'name' => 'Name', 'size' => 'Students'],
            'empty' => 'No student groups yet. Add one per level (100L to 400L), or import them from a CSV file.',
            'in_use' => 'This group has courses, so it cannot be deleted. Move or delete its courses first.',
        ];
    }
}
