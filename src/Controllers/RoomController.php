<?php
declare(strict_types=1);

namespace App\Controllers;

final class RoomController extends CrudController
{
    protected function definition(): array
    {
        return [
            'route' => 'rooms',
            'title' => 'Rooms',
            'singular' => 'room',
            'table' => 'rooms',
            'entity' => 'room',
            'select' => 'SELECT t.* FROM rooms t',
            'order' => 't.code',
            'search' => ['t.code', 't.name'],
            'filters' => ['room_type' => 'Type'],
            'fields' => [
                'code' => ['label' => 'Code', 'rules' => 'required|code|maxlen:20', 'unique' => true],
                'name' => ['label' => 'Name', 'rules' => 'required|maxlen:100'],
                'capacity' => ['label' => 'Seats', 'rules' => 'required|int|min:1|max:5000', 'type' => 'number'],
                'room_type' => ['label' => 'Type', 'rules' => 'required|in:lecture,lab', 'type' => 'select',
                    'options' => ['lecture' => 'Lecture room', 'lab' => 'Laboratory'], 'default' => 'lecture'],
                'is_active' => ['label' => 'Available for timetabling', 'rules' => 'in:0,1', 'type' => 'checkbox', 'default' => '1'],
            ],
            'columns' => ['code' => 'Code', 'name' => 'Name', 'capacity' => 'Seats', 'room_type' => 'Type', 'is_active' => 'Active'],
            'bool_columns' => ['is_active'],
            'empty' => 'No rooms yet. Add lecture rooms and labs, or import them from a CSV file.',
            'in_use' => 'Saved timetables use this room, so it cannot be deleted. Untick "Available for timetabling" instead.',
        ];
    }

    protected function options(): array
    {
        return ['room_type' => ['lecture' => 'Lecture room', 'lab' => 'Laboratory']];
    }
}
