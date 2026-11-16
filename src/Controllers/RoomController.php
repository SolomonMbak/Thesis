<?php
declare(strict_types=1);

namespace App\Controllers;

/**
 * Rooms and labs of the school. Rooms are shared by every department of the
 * school (BUILD_SPEC section 1).
 */
final class RoomController extends CrudController
{
    private const TYPES = ['lecture' => 'Lecture room', 'lab' => 'Laboratory'];

    protected function definition(): array
    {
        return [
            'route' => 'rooms',
            'title' => 'Rooms',
            'singular' => 'room',
            'plural' => 'rooms',
            'table' => 'rooms',
            'entity' => 'room',
            'add' => 'Add room',
            'select' => 'SELECT t.* FROM rooms t',
            'order' => 't.code',
            'search' => ['t.code', 't.name'],
            'filters' => ['room_type' => 'Type'],
            'fields' => [
                'code' => ['label' => 'Code', 'rules' => 'required|code|maxlen:20', 'unique' => true,
                    'help' => 'A short name shown in timetable cells, for example LT1 or LAB2.'],
                'name' => ['label' => 'Name', 'rules' => 'required|maxlen:100', 'help' => 'The full name, for example Lecture Theatre 1.'],
                'capacity' => ['label' => 'Seats', 'rules' => 'required|int|min:1|max:5000', 'type' => 'number',
                    'help' => 'Maximum students the room can seat. Rooms smaller than a class are never given to that class.'],
                'room_type' => ['label' => 'Type', 'rules' => 'required|in:lecture,lab', 'type' => 'select',
                    'options' => self::TYPES, 'default' => 'lecture',
                    'help' => 'Practicals are only placed in laboratories. Lectures can use either kind if it is big enough.'],
                'is_active' => ['label' => 'Available for timetabling', 'rules' => 'in:0,1', 'type' => 'checkbox', 'default' => '1',
                    'help' => 'Untick to keep the room on file but stop new timetables from using it (for example during repairs).'],
            ],
            'columns' => ['code' => 'Code', 'name' => 'Name', 'capacity' => 'Seats', 'room_type' => 'Type', 'is_active' => 'Active'],
            'bool_columns' => ['is_active'],
            'value_labels' => ['room_type' => self::TYPES],
            'empty' => 'No rooms yet. The timetable can only use rooms you list here, so add your lecture rooms and labs.',
            'in_use' => 'Saved timetables use this room, so it cannot be deleted. Untick "Available for timetabling" instead.',
        ];
    }

    protected function options(): array
    {
        return ['room_type' => self::TYPES];
    }
}
