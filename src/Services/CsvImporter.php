<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\SchoolContext;
use PDO;

/**
 * CSV import for master data (BUILD_SPEC section 11).
 * Every row is validated first; rows are written in one transaction only
 * when all pass. Existing records are updated by their natural key.
 */
final class CsvImporter
{
    public const MAX_BYTES = 2 * 1024 * 1024;

    public const TEMPLATES = [
        'lecturers' => [
            'columns' => ['staff_no', 'title', 'full_name', 'short_name', 'email'],
            'example' => ['NOUN/001', 'Dr.', 'Adaeze Bello', 'A. Bello', 'a.bello@example.edu'],
        ],
        'groups' => [
            'columns' => ['code', 'name', 'size'],
            'example' => ['100L', '100 Level Computer Science', '120'],
        ],
        'rooms' => [
            'columns' => ['code', 'name', 'capacity', 'room_type'],
            'example' => ['LT1', 'Lecture Theatre 1', '150', 'lecture'],
        ],
        'courses' => [
            'columns' => ['code', 'title', 'credit_units', 'hours_per_week', 'needs_lab', 'allow_two_hour_block',
                'class_size_override', 'group_code', 'lecturer_staff_no'],
            'example' => ['CSC 101', 'Introduction to Computing', '3', '3', '0', '1', '', '100L', 'NOUN/001'],
        ],
        'availability' => [
            'columns' => ['staff_no', 'day', 'start_time', 'status'],
            'example' => ['NOUN/001', 'Mon', '08:00', 'unavailable'],
        ],
    ];

    private const DAY_NAMES = ['mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5,
        'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5];

    public function __construct(private PDO $pdo, private SchoolContext $school)
    {
    }

    /**
     * Check an uploaded file entry from $_FILES. Returns an error message or null.
     */
    public static function checkUpload(?array $file): ?string
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return 'Choose a CSV file to upload.';
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return 'The upload failed. Check that the file is smaller than 2 MB and try again.';
        }
        if ($file['size'] > self::MAX_BYTES) {
            return 'The file is larger than 2 MB.';
        }
        if (strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION)) !== 'csv') {
            return 'Only .csv files can be imported. In Excel, use "Save As" and choose CSV UTF-8.';
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $allowed = ['text/plain', 'text/csv', 'application/csv', 'text/x-csv', 'application/vnd.ms-excel', 'application/x-empty'];
        if (!in_array($mime, $allowed, true)) {
            return "The file does not look like a CSV text file (detected {$mime}).";
        }
        return null;
    }

    /**
     * Read a CSV file into rows keyed by column name.
     * @return array{rows: array<int, array<string,string>>, errors: string[]} rows keyed by file line number
     */
    public static function read(string $path, array $columns): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return ['rows' => [], 'errors' => ['The file could not be opened.']];
        }
        $header = fgetcsv($handle, 0, ',', '"', '');
        if ($header === false || $header === [null]) {
            fclose($handle);
            return ['rows' => [], 'errors' => ['The file is empty. The first row must be the header: ' . implode(',', $columns)]];
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

        $missing = array_diff($columns, $header);
        $unknown = array_diff($header, $columns);
        if ($missing || $unknown) {
            fclose($handle);
            $errors = [];
            if ($missing) {
                $errors[] = 'Missing column(s): ' . implode(', ', $missing) . '.';
            }
            if ($unknown) {
                $errors[] = 'Unknown column(s): ' . implode(', ', $unknown) . '. Use the template header exactly.';
            }
            return ['rows' => [], 'errors' => $errors];
        }

        $rows = [];
        $errors = [];
        $line = 1;
        while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $line++;
            if ($values === [null] || implode('', array_map('trim', array_map('strval', $values))) === '') {
                continue;
            }
            if (count($values) !== count($header)) {
                $errors[] = "Row {$line}: expected " . count($header) . ' values but found ' . count($values) . '.';
                continue;
            }
            $row = array_combine($header, array_map(fn ($v) => trim((string) $v), $values));
            if (!mb_check_encoding(implode('', $row), 'UTF-8')) {
                $errors[] = "Row {$line}: text is not UTF-8. Save the file as CSV UTF-8.";
                continue;
            }
            $rows[$line] = $row;
        }
        fclose($handle);
        if (!$rows && !$errors) {
            $errors[] = 'The file has a header but no data rows.';
        }
        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * Validate and import one file. Nothing is written unless every row passes.
     * @return array{ok:bool, errors:string[], inserted:int, updated:int}
     */
    public function import(string $type, string $path, ?int $semesterId = null): array
    {
        if (!isset(self::TEMPLATES[$type])) {
            throw new \InvalidArgumentException("Unknown import type {$type}");
        }
        if ($type === 'courses' && !$semesterId) {
            return self::result(false, ['Create and activate a semester before importing courses.']);
        }
        $read = self::read($path, self::TEMPLATES[$type]['columns']);
        if ($read['errors']) {
            return self::result(false, $read['errors']);
        }

        [$records, $errors] = $this->validateRows($type, $read['rows']);
        if ($errors) {
            return self::result(false, $errors);
        }

        $this->pdo->beginTransaction();
        try {
            $counts = $this->write($type, $records, $semesterId);
            $this->pdo->commit();
        } catch (\Throwable $ex) {
            $this->pdo->rollBack();
            throw $ex;
        }
        return self::result(true, [], $counts['inserted'], $counts['updated']);
    }

    /** @return array{0: array, 1: string[]} */
    private function validateRows(string $type, array $rows): array
    {
        $records = [];
        $errors = [];
        $seen = [];
        $lookups = $this->lookups($type);
        foreach ($rows as $line => $row) {
            $rowErrors = [];
            $record = match ($type) {
                'lecturers' => $this->lecturerRow($row, $rowErrors),
                'groups' => $this->groupRow($row, $rowErrors),
                'rooms' => $this->roomRow($row, $rowErrors),
                'courses' => $this->courseRow($row, $rowErrors, $lookups),
                'availability' => $this->availabilityRow($row, $rowErrors, $lookups),
            };
            $key = $record['_key'] ?? null;
            if ($key !== null && isset($seen[$key])) {
                $rowErrors[] = "duplicate of row {$seen[$key]}";
            }
            if ($rowErrors) {
                $errors[] = "Row {$line}: " . implode('; ', $rowErrors) . '.';
                continue;
            }
            $seen[$key] = $line;
            $records[] = $record;
        }
        return [$records, $errors];
    }

    private function lecturerRow(array $r, array &$e): array
    {
        $this->requireText($r, 'staff_no', 20, $e);
        $this->requireText($r, 'full_name', 120, $e);
        $this->requireText($r, 'short_name', 30, $e);
        if (mb_strlen($r['title']) > 20) {
            $e[] = 'title is longer than 20 characters';
        }
        if ($r['email'] !== '' && filter_var($r['email'], FILTER_VALIDATE_EMAIL) === false) {
            $e[] = "email '{$r['email']}' is not valid";
        }
        return $r + ['_key' => strtolower($r['staff_no'])];
    }

    private function groupRow(array $r, array &$e): array
    {
        $this->requireText($r, 'code', 20, $e);
        $this->requireText($r, 'name', 100, $e);
        $this->requireInt($r, 'size', 1, 5000, $e);
        return $r + ['_key' => strtolower($r['code'])];
    }

    private function roomRow(array $r, array &$e): array
    {
        $this->requireText($r, 'code', 20, $e);
        $this->requireText($r, 'name', 100, $e);
        $this->requireInt($r, 'capacity', 1, 5000, $e);
        $r['room_type'] = strtolower($r['room_type']);
        if (!in_array($r['room_type'], ['lecture', 'lab'], true)) {
            $e[] = 'room_type must be lecture or lab';
        }
        return $r + ['_key' => strtolower($r['code'])];
    }

    private function courseRow(array $r, array &$e, array $lookups): array
    {
        $this->requireText($r, 'code', 20, $e);
        $this->requireText($r, 'title', 150, $e);
        $this->requireInt($r, 'credit_units', 0, 20, $e);
        $this->requireInt($r, 'hours_per_week', 1, 20, $e);
        foreach (['needs_lab', 'allow_two_hour_block'] as $flag) {
            $value = self::parseBool($r[$flag]);
            if ($value === null) {
                $e[] = "{$flag} must be 0 or 1";
            }
            $r[$flag] = (int) $value;
        }
        if ($r['class_size_override'] !== '') {
            $this->requireInt($r, 'class_size_override', 1, 5000, $e);
        }
        $r['group_id'] = $lookups['groups'][strtolower($r['group_code'])] ?? null;
        if ($r['group_id'] === null) {
            $e[] = "group_code '{$r['group_code']}' does not exist (import groups first)";
        }
        $r['lecturer_id'] = $lookups['lecturers'][strtolower($r['lecturer_staff_no'])] ?? null;
        if ($r['lecturer_id'] === null) {
            $e[] = "lecturer_staff_no '{$r['lecturer_staff_no']}' does not exist (import lecturers first)";
        }
        return $r + ['_key' => strtolower($r['code'])];
    }

    private function availabilityRow(array $r, array &$e, array $lookups): array
    {
        $r['lecturer_id'] = $lookups['lecturers'][strtolower($r['staff_no'])] ?? null;
        if ($r['lecturer_id'] === null) {
            $e[] = "staff_no '{$r['staff_no']}' does not exist";
        }
        $day = self::DAY_NAMES[strtolower($r['day'])] ?? null;
        if ($day === null) {
            $e[] = 'day must be Mon, Tue, Wed, Thu or Fri';
        }
        $time = preg_match('/^(\d{1,2}):(\d{2})/', $r['start_time'], $m) ? sprintf('%02d:%02d', $m[1], $m[2]) : null;
        $r['timeslot_id'] = $day && $time ? ($lookups['timeslots']["{$day} {$time}"] ?? null) : null;
        if ($day && $r['timeslot_id'] === null) {
            $e[] = "no period starts at '{$r['start_time']}' on {$r['day']}";
        }
        $r['status'] = strtolower($r['status']);
        if (!in_array($r['status'], ['available', 'preferred', 'unavailable'], true)) {
            $e[] = 'status must be available, preferred or unavailable';
        }
        return $r + ['_key' => $r['lecturer_id'] . '-' . $r['timeslot_id']];
    }

    /** @return array{inserted:int, updated:int} */
    private function write(string $type, array $records, ?int $semesterId): array
    {
        [$table, $keyColumns, $columns] = match ($type) {
            'lecturers' => ['lecturers', ['staff_no'], ['staff_no', 'title', 'full_name', 'short_name', 'email']],
            'groups' => ['student_groups', ['code'], ['code', 'name', 'size']],
            'rooms' => ['rooms', ['code'], ['code', 'name', 'capacity', 'room_type']],
            'courses' => ['courses', ['semester_id', 'code'], ['semester_id', 'code', 'title', 'credit_units', 'hours_per_week',
                'needs_lab', 'allow_two_hour_block', 'class_size_override', 'group_id', 'lecturer_id']],
            'availability' => ['lecturer_availability', ['lecturer_id', 'timeslot_id'], ['lecturer_id', 'timeslot_id', 'status']],
        };
        // Every lookup, update and delete is limited to this school.
        $where = implode(' AND ', array_map(fn ($c) => "{$c} = ?", ['school_id', ...$keyColumns]));
        $columns = ['school_id', ...$columns];
        $find = $this->pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}");
        $setColumns = array_diff($columns, $keyColumns, ['school_id']);
        $update = $this->pdo->prepare("UPDATE {$table} SET " . implode(', ', array_map(fn ($c) => "{$c} = ?", $setColumns)) . " WHERE {$where}");
        $insert = $this->pdo->prepare("INSERT INTO {$table} (" . implode(', ', $columns) . ') VALUES ('
            . implode(', ', array_fill(0, count($columns), '?')) . ')');

        $counts = ['inserted' => 0, 'updated' => 0];
        foreach ($records as $record) {
            $record['semester_id'] = $semesterId;
            $record['school_id'] = $this->school->id;
            $record['email'] = ($record['email'] ?? '') === '' ? null : $record['email'];
            $record['class_size_override'] = ($record['class_size_override'] ?? '') === '' ? null : (int) $record['class_size_override'];
            $keyValues = array_map(fn ($c) => $record[$c], ['school_id', ...$keyColumns]);
            $find->execute($keyValues);
            $exists = (int) $find->fetchColumn() > 0;

            if ($type === 'availability' && $record['status'] === 'available') {
                // "available" is the default, stored as no row at all.
                $this->pdo->prepare("DELETE FROM {$table} WHERE {$where}")->execute($keyValues);
                if ($exists) {
                    $counts['updated']++;
                }
                continue;
            }
            if ($exists) {
                $update->execute(array_merge(array_map(fn ($c) => $record[$c], array_values($setColumns)), $keyValues));
                $counts['updated']++;
            } else {
                $insert->execute(array_map(fn ($c) => $record[$c], $columns));
                $counts['inserted']++;
            }
        }
        return $counts;
    }

    private function lookups(string $type): array
    {
        $lookups = [];
        if (in_array($type, ['courses', 'availability'], true)) {
            $lookups['lecturers'] = array_change_key_case(
                $this->pairs('SELECT staff_no, id FROM lecturers WHERE school_id = ?')
            );
        }
        if ($type === 'courses') {
            $lookups['groups'] = array_change_key_case(
                $this->pairs('SELECT code, id FROM student_groups WHERE school_id = ?')
            );
        }
        if ($type === 'availability') {
            $lookups['timeslots'] = $this->pairs(
                "SELECT CONCAT(day_of_week, ' ', TIME_FORMAT(start_time, '%H:%i')), id FROM timeslots WHERE school_id = ?"
            );
        }
        return $lookups;
    }

    /** key => id pairs from a query with one school_id placeholder. */
    private function pairs(string $sql): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$this->school->id]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    private function requireText(array $r, string $col, int $max, array &$e): void
    {
        if ($r[$col] === '') {
            $e[] = "{$col} is required";
        } elseif (mb_strlen($r[$col]) > $max) {
            $e[] = "{$col} is longer than {$max} characters";
        }
    }

    private function requireInt(array $r, string $col, int $min, int $max, array &$e): void
    {
        if (!preg_match('/^\d+$/', $r[$col]) || (int) $r[$col] < $min || (int) $r[$col] > $max) {
            $e[] = "{$col} must be a whole number from {$min} to {$max}";
        }
    }

    public static function parseBool(string $value): ?bool
    {
        return match (strtolower(trim($value))) {
            '1', 'yes', 'y', 'true' => true,
            '0', 'no', 'n', 'false', '' => false,
            default => null,
        };
    }

    private static function result(bool $ok, array $errors, int $inserted = 0, int $updated = 0): array
    {
        return ['ok' => $ok, 'errors' => $errors, 'inserted' => $inserted, 'updated' => $updated];
    }
}
