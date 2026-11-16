<?php
declare(strict_types=1);

namespace App\Data;

use App\Core\SchoolContext;
use PDO;

/**
 * Generic, school-scoped access to one simple tenant table, used by the
 * master data pages (lecturers, groups, rooms, courses).
 *
 * Every query adds "t.school_id = ?" for the current school, so a record of
 * another school can never be listed, found, changed or deleted, even by
 * guessing its id. Table and column names come from code (the page
 * definitions), never from the request.
 */
final class TableRepository
{
    public const TABLES = ['lecturers', 'student_groups', 'rooms', 'courses'];

    public function __construct(private PDO $pdo, private SchoolContext $school, private string $table)
    {
        if (!in_array($table, self::TABLES, true)) {
            throw new \InvalidArgumentException("Table {$table} is not a master data table.");
        }
    }

    /**
     * Rows for a list page.
     * @param string $select SELECT ... FROM <table> t [JOIN ...]  (the table must be aliased t)
     * @param string[] $where extra conditions joined with AND
     */
    public function list(string $select, array $where, array $params, string $order): array
    {
        $where[] = 't.school_id = ?';
        $params[] = $this->school->id;
        $stmt = $this->pdo->prepare($select . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $order);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** One row of this school, or null (also when it belongs to another school). */
    public function find(int $id, array $where = [], array $params = []): ?array
    {
        $where[] = 't.id = ?';
        $where[] = 't.school_id = ?';
        $stmt = $this->pdo->prepare("SELECT t.* FROM {$this->table} t WHERE " . implode(' AND ', $where));
        $stmt->execute([...$params, $id, $this->school->id]);
        return $stmt->fetch() ?: null;
    }

    public function insert(array $values): int
    {
        $values['school_id'] = $this->school->id;
        $columns = array_keys($values);
        $this->pdo->prepare("INSERT INTO {$this->table} (" . implode(', ', $columns) . ') VALUES ('
            . implode(', ', array_fill(0, count($columns), '?')) . ')')->execute(array_values($values));
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $values): bool
    {
        unset($values['school_id'], $values['id']);
        $set = implode(', ', array_map(fn ($c) => "{$c} = ?", array_keys($values)));
        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET {$set} WHERE id = ? AND school_id = ?");
        $stmt->execute([...array_values($values), $id, $this->school->id]);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = ? AND school_id = ?");
        $stmt->execute([$id, $this->school->id]);
        return $stmt->rowCount() > 0;
    }

    /** True if another row of this school already has $value in $column (within the extra scope). */
    public function exists(string $column, string $value, ?int $exceptId, array $where = [], array $params = []): bool
    {
        $where[] = "t.{$column} = ?";
        $where[] = 't.school_id = ?';
        $params = [...$params, $value, $this->school->id];
        if ($exceptId !== null) {
            $where[] = 't.id <> ?';
            $params[] = $exceptId;
        }
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->table} t WHERE " . implode(' AND ', $where));
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** id => label pairs for a select box, e.g. pairs("CONCAT(code, ' - ', name)", 'code'). */
    public function pairs(string $labelSql, string $order, string $where = '1 = 1'): array
    {
        $stmt = $this->pdo->prepare("SELECT t.id, {$labelSql} FROM {$this->table} t WHERE t.school_id = ? AND {$where} ORDER BY {$order}");
        $stmt->execute([$this->school->id]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function count(string $where = '1 = 1', array $params = []): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->table} t WHERE t.school_id = ? AND {$where}");
        $stmt->execute([$this->school->id, ...$params]);
        return (int) $stmt->fetchColumn();
    }
}
