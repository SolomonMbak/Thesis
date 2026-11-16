<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Services\CsvImporter;

/**
 * CSV import of master data, with downloadable templates.
 * Uploaded files are read from PHP's temporary file and never stored.
 */
final class ImportController extends Controller
{
    private const TYPES = [
        'groups' => 'Levels',
        'rooms' => 'Rooms',
        'lecturers' => 'Lecturers',
        'courses' => 'Courses (into the active semester)',
        'availability' => 'Lecturer availability',
    ];

    public function index(array $result = [], string $type = ''): void
    {
        $this->render('import/index', [
            'title' => 'Import from spreadsheet',
            'types' => self::TYPES,
            'templates' => CsvImporter::TEMPLATES,
            'result' => $result,
            'resultType' => $type,
        ]);
    }

    public function upload(): void
    {
        $type = (string) $this->app->request->input('type', '');
        if (!isset(self::TYPES[$type])) {
            $this->app->flash->error('Choose what kind of data the file contains.');
            $this->redirect('import');
        }
        $file = $this->app->request->file('file');
        $problem = CsvImporter::checkUpload($file);
        if ($problem !== null) {
            http_response_code(422);
            $this->index(['ok' => false, 'errors' => [$problem]], $type);
            return;
        }
        $semester = $this->activeSemester();
        $result = (new CsvImporter($this->app->db(), $this->school()))->import($type, $file['tmp_name'], $semester ? (int) $semester['id'] : null);
        if (!$result['ok']) {
            http_response_code(422);
            $this->index($result, $type);
            return;
        }
        $this->app->log('import.' . $type, null, null, ['file' => basename((string) $file['name']),
            'inserted' => $result['inserted'], 'updated' => $result['updated']]);
        $message = self::TYPES[$type] . " imported: {$result['inserted']} added, {$result['updated']} updated.";
        if ($type === 'courses') {
            $message .= ' Class sessions are updated automatically when you open Check readiness.';
        }
        $this->app->flash->success($message);
        $this->redirect('import');
    }

    /** Download a template with the header row and one example row. */
    public function template(): void
    {
        $type = (string) $this->app->request->query('type', '');
        $template = CsvImporter::TEMPLATES[$type] ?? null;
        if ($template === null) {
            throw new HttpException(404, 'There is no template of that kind.');
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $type . '.csv"');
        $out = fopen('php://output', 'wb');
        fputcsv($out, $template['columns'], ',', '"', '');
        fputcsv($out, $template['example'], ',', '"', '');
        fclose($out);
    }
}
