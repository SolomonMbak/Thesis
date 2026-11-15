<?php
/**
 * Left menu. Each section lists [label, route, adminOnly].
 * Hiding an item is a convenience only: the router enforces access.
 */
declare(strict_types=1);

return [
    ['heading' => null, 'items' => [
        ['Dashboard', 'dashboard', false],
    ]],
    ['heading' => '1. Master data', 'items' => [
        ['Lecturers', 'lecturers', false],
        ['Student groups', 'groups', false],
        ['Rooms', 'rooms', false],
        ['Courses', 'courses', false],
        ['Periods', 'periods', false],
        ['Availability', 'availability', false],
        ['Build sessions', 'sessions', false],
        ['Import CSV', 'import', false],
    ]],
    ['heading' => '2. Settings', 'items' => [
        ['Constraints and settings', 'settings', false],
    ]],
    ['heading' => '3. Check', 'items' => [
        ['Feasibility check', 'feasibility', false],
    ]],
    ['heading' => '6. Administration', 'items' => [
        ['Semesters', 'semesters', true],
    ]],
];
