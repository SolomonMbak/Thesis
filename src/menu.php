<?php
/**
 * Sidebar: four collapsible groups (BUILD_SPEC 10.1), plus Platform for
 * platform administrators. Each item is [label, route, minimum role, icon].
 * Hiding an item is a convenience only: the router enforces access.
 */
declare(strict_types=1);

return [
    ['id' => 'start', 'label' => 'Get started', 'icon' => 'start', 'items' => [
        ['Setup Guide', 'setup', 'viewer', 'guide'],
        ['Dashboard', 'dashboard', 'viewer', 'home'],
        ['About this app', 'about', 'viewer', 'info'],
    ]],
    ['id' => 'data', 'label' => 'School data', 'icon' => 'school', 'items' => [
        ['School profile and periods', 'school/profile', 'officer', 'school'],
        ['Rooms', 'rooms', 'officer', 'room'],
        ['Levels', 'groups', 'officer', 'level'],
        ['Lecturers', 'lecturers', 'officer', 'lecturer'],
        ['Courses', 'courses', 'officer', 'course'],
        ['Lecturer availability', 'availability', 'officer', 'availability'],
        ['Import from spreadsheet', 'import', 'officer', 'import'],
    ]],
    ['id' => 'timetables', 'label' => 'Timetables', 'icon' => 'calendar', 'items' => [
        ['Check readiness', 'feasibility', 'officer', 'check'],
        ['Generate timetable', 'generate', 'officer', 'generate'],
        ['Runs and results', 'timetables', 'viewer', 'runs'],
        ['Published timetable and share link', 'published', 'viewer', 'publish'],
    ]],
    ['id' => 'manage', 'label' => 'Manage', 'icon' => 'manage', 'items' => [
        ['Members and invitations', 'members', 'admin', 'members'],
        ['Rules and weights', 'rules', 'admin', 'rules'],
        ['Semesters', 'semesters', 'admin', 'semester'],
        ['Activity log', 'audit', 'admin', 'activity'],
        ['School settings', 'school/settings', 'admin', 'settings'],
    ]],
    ['id' => 'platform', 'label' => 'Platform', 'icon' => 'platform', 'items' => [
        ['Schools', 'platform/schools', 'platform', 'platform'],
        ['Platform settings', 'platform/settings', 'platform', 'settings'],
        ['Health', 'health', 'platform', 'health'],
        ['Backups', 'backups', 'platform', 'backup'],
    ]],
];
