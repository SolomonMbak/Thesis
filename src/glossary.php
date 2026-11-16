<?php
/**
 * Plain-English meanings of the technical words used in the app
 * (BUILD_SPEC 10.3 item 3). Used by the info tooltips (term()) and by the
 * Glossary in the Help Centre, so both always say the same thing.
 */
declare(strict_types=1);

const GLOSSARY = [
    'hard rule' => 'A rule the timetable must never break, such as a lecturer in two places at once.',
    'soft rule' => 'A wish the timetable should meet when it can, such as no idle gaps for students.',
    'clash' => 'Two classes that need the same lecturer, room or level at the same time, or a class that breaks another hard rule.',
    'penalty' => 'A score for what is wrong with a timetable: each broken rule adds points, so lower is better.',
    'fitness' => 'The penalty turned into a number between 0 and 1, where 1 is a perfect timetable.',
    'generation' => 'One round of the search in which the whole group of candidate timetables is improved once.',
    'repair' => 'A step that moves each clashing class to the best free time and room, so clashes disappear faster.',
    'feasibility' => 'Whether your data allows a timetable with no clashes at all, checked before the search starts.',
    'seed' => 'A number that fixes the random choices of a run, so the same seed and data give the same timetable again.',
    'class session' => 'One meeting of a course in the week that needs a time and a room, for example one hour of a 3-hour course.',
    'period' => 'One teaching slot in the week, for example Tuesday 10:00 to 11:00.',
    'level' => 'A group of students who take their classes together, for example 200 Level Computer Science.',
    'run' => 'One search for a timetable, with its settings, its live progress and the best timetable it found.',
    'weight' => 'How many penalty points one break of a rule adds. A bigger weight makes the search care more about that rule.',
];

/**
 * A technical word with an info tooltip: hover or focus shows the meaning.
 * term('penalty') or term('hard rule', 'hard rules').
 */
function term(string $key, ?string $text = null): string
{
    $meaning = GLOSSARY[$key] ?? null;
    $label = htmlspecialchars($text ?? $key, ENT_QUOTES, 'UTF-8');
    if ($meaning === null) {
        return $label;
    }
    $id = 'tip-' . substr(md5($key . random_int(0, PHP_INT_MAX)), 0, 8);
    return '<span class="term" tabindex="0" aria-describedby="' . $id . '">' . $label
        . '<span class="term-tip" role="tooltip" id="' . $id . '">' . htmlspecialchars($meaning, ENT_QUOTES, 'UTF-8') . '</span></span>';
}
