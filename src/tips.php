<?php
/**
 * First-visit tips (BUILD_SPEC 10.3 item 4): shown once at the top of a page
 * until the user dismisses it. Keyed like the help files (Controller::helpKey).
 * Each tip: one sentence on what the page is for, and the main action.
 */
declare(strict_types=1);

return [
    'setup' => ['text' => 'This is your checklist from an empty school to a published timetable. Each step turns green by itself when it is done.',
        'action' => 'Start with step 1', 'route' => 'school/profile'],
    'dashboard' => ['text' => 'The dashboard shows how far your school has got and anything that needs your attention.',
        'action' => 'Open the Setup Guide', 'route' => 'setup'],
    'school_profile' => ['text' => 'Set your school name, the hours of your teaching day and which periods are blocked, such as Friday prayers.',
        'action' => '', 'route' => ''],
    'rooms' => ['text' => 'List every room and lab the timetable may use, with how many students each one seats.',
        'action' => 'Add a room', 'route' => 'rooms/create'],
    'groups' => ['text' => 'A level is a group of students who take classes together, such as 100L. A level never gets two classes at once.',
        'action' => 'Add a level', 'route' => 'groups/create'],
    'lecturers' => ['text' => 'Add the staff who teach. The timetable never puts one lecturer in two places at once.',
        'action' => 'Add a lecturer', 'route' => 'lecturers/create'],
    'courses' => ['text' => 'Each course links a level and a lecturer to its weekly contact hours. The classes to schedule are worked out from these.',
        'action' => 'Add a course', 'route' => 'courses/create'],
    'availability' => ['text' => 'Click a period to mark it preferred or unavailable for the chosen lecturer. Unmarked periods count as available.',
        'action' => '', 'route' => ''],
    'import' => ['text' => 'Already have your data in Excel? Download a template, fill it in, save it as CSV UTF-8 and upload it here.',
        'action' => '', 'route' => ''],
    'feasibility' => ['text' => 'Check readiness looks for problems that make a clash-free timetable impossible, so you can fix them before waiting for a run.',
        'action' => '', 'route' => ''],
    'generate' => ['text' => 'Press Start and the genetic algorithm breeds the best clash-free timetable on the server, in about a minute. You can leave the page while it runs.',
        'action' => '', 'route' => ''],
    'timetables' => ['text' => 'Every run and imported timetable is listed here. Open one to see its grids and quality report, or tick two to compare them.',
        'action' => '', 'route' => ''],
    'published' => ['text' => 'The published timetable is what students and staff see. Share the link below; it never changes when you publish a newer run.',
        'action' => '', 'route' => ''],
    'members' => ['text' => 'Invite colleagues by email, or add them with a temporary password, and choose what each one may do. Viewers can see timetables but not change anything.',
        'action' => '', 'route' => ''],
    'rules' => ['text' => 'Hard rules must never be broken; soft rules are wishes. A weight says how much the genetic algorithm cares about each one.',
        'action' => '', 'route' => ''],
];
