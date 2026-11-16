<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Validator;
use App\Data\TableRepository;

/**
 * Shared list / create / edit / delete pages for simple master data tables
 * (rooms, levels, lecturers, courses). Each subclass describes its table in
 * definition(); all reading and writing goes through TableRepository, which
 * limits every query to the current school. A record of another school is
 * "not found" (404), never "not allowed", so its existence is not revealed.
 *
 * definition() keys:
 *   route, title, singular, plural, table, entity (for the activity log), order,
 *   fields:  name => [label, rules, type, (options), (help), (nullable), (unique), (default)]
 *   columns: list column => heading  (from select)
 *   select:  SELECT ... FROM <table> t [JOIN ...]
 *   search:  qualified columns searched by ?q=
 *   filters: field => label, matched exactly from ?<field>=
 *   empty:   one line shown when the list is empty
 *   add:     label of the primary button, e.g. "Add room"
 *   in_use:  message when delete fails because other records use it
 */
abstract class CrudController extends Controller
{
    abstract protected function definition(): array;

    /** Extra WHERE for every query, e.g. the active semester. [sql, params] */
    protected function scope(): array
    {
        return ['1 = 1', []];
    }

    /** Values written on insert that are not form fields. */
    protected function fixedValues(): array
    {
        return [];
    }

    /** Select options computed at run time, e.g. lists of levels. */
    protected function options(): array
    {
        return [];
    }

    /** Called after a successful save or delete. */
    protected function afterChange(string $action, int $id): void
    {
    }

    /** Optional message above the list (e.g. "no active semester"). */
    protected function notice(): ?string
    {
        return null;
    }

    protected function repository(): TableRepository
    {
        return new TableRepository($this->app->db(), $this->school(), $this->definition()['table']);
    }

    public function index(): void
    {
        $def = $this->definition();
        [$scopeSql, $params] = $this->scope();
        $where = [$scopeSql];
        $q = trim((string) $this->app->request->query('q', ''));
        if ($q !== '' && !empty($def['search'])) {
            $where[] = '(' . implode(' OR ', array_map(fn ($c) => "{$c} LIKE ?", $def['search'])) . ')';
            array_push($params, ...array_fill(0, count($def['search']), '%' . $q . '%'));
        }
        $activeFilters = [];
        foreach ($def['filters'] ?? [] as $field => $label) {
            $value = (string) $this->app->request->query($field, '');
            if ($value !== '') {
                $where[] = "t.{$field} = ?";
                $params[] = $value;
                $activeFilters[$field] = $value;
            }
        }
        $this->render('crud/index', [
            'title' => $def['title'],
            'def' => $def,
            'rows' => $this->repository()->list($def['select'], $where, $params, $def['order']),
            'q' => $q,
            'activeFilters' => $activeFilters,
            'options' => $this->options(),
            'notice' => $this->notice(),
        ]);
    }

    public function create(): void
    {
        $this->showForm(null, $this->defaults(), []);
    }

    public function store(): void
    {
        $this->save(null);
    }

    public function edit(): void
    {
        $row = $this->findOrFail((int) $this->app->request->query('id', 0));
        $this->showForm((int) $row['id'], $row, []);
    }

    public function update(): void
    {
        $row = $this->findOrFail((int) $this->app->request->input('id', 0));
        $this->save((int) $row['id']);
    }

    public function delete(): void
    {
        $def = $this->definition();
        $row = $this->findOrFail((int) $this->app->request->input('id', 0));
        try {
            $this->repository()->delete((int) $row['id']);
        } catch (\PDOException $ex) {
            if ($ex->getCode() === '23000') {
                $this->app->flash->error($def['in_use']);
                $this->redirect($def['route']);
            }
            throw $ex;
        }
        $this->app->log($def['entity'] . '.delete', $def['entity'], (int) $row['id'], $this->summary($row));
        $this->afterChange('delete', (int) $row['id']);
        $this->app->flash->success(ucfirst($def['singular']) . ' deleted.');
        $this->redirect($def['route']);
    }

    private function save(?int $id): void
    {
        $def = $this->definition();
        $input = $this->readInput($def['fields']);
        $errors = $this->validate($def, $input, $id);
        if ($errors) {
            http_response_code(422);
            $this->showForm($id, $input, $errors);
            return;
        }
        $values = $this->toDbValues($def['fields'], $input);
        if ($id === null) {
            $id = $this->repository()->insert($values + $this->fixedValues());
            $action = 'create';
        } else {
            $this->repository()->update($id, $values);
            $action = 'update';
        }
        $this->app->log($def['entity'] . '.' . $action, $def['entity'], $id, $values);
        $this->afterChange($action, $id);
        $this->app->flash->success(ucfirst($def['singular']) . ($action === 'create' ? ' added.' : ' saved.'));
        $this->redirect($def['route']);
    }

    private function showForm(?int $id, array $values, array $errors): void
    {
        $def = $this->definition();
        $this->render('crud/form', [
            'title' => ($id ? 'Edit ' : 'Add ') . $def['singular'],
            'def' => $def,
            'id' => $id,
            'values' => $values,
            'errors' => $errors,
            'options' => $this->options(),
        ]);
    }

    private function readInput(array $fields): array
    {
        $input = [];
        foreach ($fields as $name => $field) {
            $raw = $this->app->request->input($name, '');
            $input[$name] = is_string($raw) ? trim($raw) : '';
            if (($field['type'] ?? 'text') === 'checkbox') {
                $input[$name] = $input[$name] === '1' ? '1' : '0';
            }
        }
        return $input;
    }

    private function validate(array $def, array $input, ?int $id): array
    {
        $rules = array_map(fn ($f) => $f['rules'], $def['fields']);
        $labels = array_map(fn ($f) => $f['label'], $def['fields']);
        $errors = Validator::validate($input, $rules, $labels);
        $options = $this->options();
        [$scopeSql, $scopeParams] = $this->scope();

        foreach ($def['fields'] as $name => $field) {
            if (isset($errors[$name]) || $input[$name] === '') {
                continue;
            }
            // Select choices come from this school only, so a level or lecturer of
            // another school can never be linked (BUILD_SPEC 13.1, same-school foreign keys).
            if (($field['type'] ?? '') === 'select') {
                $choices = $field['options'] ?? $options[$name] ?? [];
                if (!array_key_exists($input[$name], $choices)) {
                    $errors[$name] = "Choose a {$field['label']} from the list.";
                }
            }
            if (!empty($field['unique']) && $this->repository()->exists($name, $input[$name], $id, [$scopeSql], $scopeParams)) {
                $errors[$name] = "{$field['label']} '{$input[$name]}' is already used. Each {$def['singular']} needs its own.";
            }
        }
        return $errors;
    }

    private function toDbValues(array $fields, array $input): array
    {
        $values = [];
        foreach ($fields as $name => $field) {
            $values[$name] = $input[$name] === '' && !empty($field['nullable']) ? null : $input[$name];
        }
        return $values;
    }

    private function defaults(): array
    {
        $values = [];
        foreach ($this->definition()['fields'] as $name => $field) {
            $values[$name] = $field['default'] ?? '';
        }
        return $values;
    }

    private function findOrFail(int $id): array
    {
        $def = $this->definition();
        [$scopeSql, $params] = $this->scope();
        $row = $this->repository()->find($id, [$scopeSql], $params);
        if (!$row) {
            throw new HttpException(404, 'That ' . $def['singular'] . ' does not exist. It may have been deleted.');
        }
        return $row;
    }

    private function summary(array $row): array
    {
        return array_intersect_key($row, array_flip(['code', 'staff_no', 'full_name', 'name', 'title']));
    }
}
