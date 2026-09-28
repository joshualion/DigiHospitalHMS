<?php

namespace App\Services;

use App\Models\Hospital;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AdmissionsDemoResetService
{
    private array $foreignKeysByReferencedTable = [];
    private array $visited = [];
    private int $deletedRows = 0;
    private int $nulledReferences = 0;

    public function reset(Hospital $hospital): array
    {
        $this->foreignKeysByReferencedTable = $this->foreignKeys();
        $this->visited = [];
        $this->deletedRows = 0;
        $this->nulledReferences = 0;

        return DB::transaction(function () use ($hospital): array {
            $admissionIds = DB::table('admissions')
                ->where('hospital_id', $hospital->id)
                ->pluck('id')
                ->all();

            foreach ($admissionIds as $admissionId) {
                $this->purgeRow('admissions', $admissionId);
            }

            // These history tables intentionally use nullable references so routine
            // deletion preserves history. A pre-production reset should remove the
            // demo history itself rather than leave empty shells behind.
            foreach (['admission_bed_movements', 'admission_events'] as $historyTable) {
                if (DB::getSchemaBuilder()->hasTable($historyTable)) {
                    $this->deletedRows += DB::table($historyTable)
                        ->where('hospital_id', $hospital->id)
                        ->delete();
                }
            }

            if (DB::getSchemaBuilder()->hasTable('patient_activity_events')) {
                $this->deletedRows += DB::table('patient_activity_events')
                    ->where('hospital_id', $hospital->id)
                    ->where('action', 'like', 'admission.%')
                    ->delete();
            }

            foreach (['beds', 'ward_rooms', 'wards', 'bed_classes'] as $table) {
                if (! DB::getSchemaBuilder()->hasTable($table)) {
                    continue;
                }

                $ids = DB::table($table)->where('hospital_id', $hospital->id)->pluck('id')->all();
                foreach ($ids as $id) {
                    $this->purgeRow($table, $id);
                }
            }

            return [
                'deleted_rows' => $this->deletedRows,
                'nulled_references' => $this->nulledReferences,
            ];
        }, 3);
    }

    private function purgeRow(string $table, int|string $id): void
    {
        $key = $table.':'.$id;
        if (isset($this->visited[$key])) {
            return;
        }
        $this->visited[$key] = true;

        if (! DB::getSchemaBuilder()->hasTable($table) || ! DB::table($table)->where('id', $id)->exists()) {
            return;
        }

        foreach ($this->foreignKeysByReferencedTable[$table] ?? [] as $foreignKey) {
            $childTable = $foreignKey['table'];
            $column = $foreignKey['column'];
            $deleteRule = strtoupper($foreignKey['delete_rule']);

            if ($deleteRule === 'SET NULL') {
                $this->nulledReferences += DB::table($childTable)->where($column, $id)->update([$column => null]);
                continue;
            }

            if ($this->tableHasNoId($childTable)) {
                $this->deletedRows += DB::table($childTable)->where($column, $id)->delete();
                continue;
            }

            foreach (DB::table($childTable)->where($column, $id)->pluck('id')->all() as $childId) {
                $this->purgeRow($childTable, $childId);
            }
        }

        $this->deletedRows += DB::table($table)->where('id', $id)->delete();
    }

    private function foreignKeys(): array
    {
        return match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => $this->mysqlForeignKeys(),
            'sqlite' => $this->sqliteForeignKeys(),
            default => throw new RuntimeException('Admissions demo reset is not configured for this database driver.'),
        };
    }

    private function mysqlForeignKeys(): array
    {
        $rows = DB::select(
            <<<'SQL'
                SELECT
                    kcu.TABLE_NAME AS child_table,
                    kcu.COLUMN_NAME AS child_column,
                    kcu.REFERENCED_TABLE_NAME AS referenced_table,
                    rc.DELETE_RULE AS delete_rule
                FROM information_schema.KEY_COLUMN_USAGE kcu
                INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
                    ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
                    AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
                    AND rc.TABLE_NAME = kcu.TABLE_NAME
                WHERE kcu.CONSTRAINT_SCHEMA = ?
                  AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
                  AND kcu.REFERENCED_COLUMN_NAME = 'id'
            SQL,
            [DB::getDatabaseName()]
        );

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row->referenced_table][] = [
                'table' => $row->child_table,
                'column' => $row->child_column,
                'delete_rule' => $row->delete_rule,
            ];
        }

        return $grouped;
    }

    private function sqliteForeignKeys(): array
    {
        $tables = collect(DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"))
            ->pluck('name')
            ->all();

        $grouped = [];

        foreach ($tables as $table) {
            $quoted = str_replace("'", "''", $table);
            foreach (DB::select("PRAGMA foreign_key_list('{$quoted}')") as $foreignKey) {
                if (($foreignKey->to ?? null) !== 'id') {
                    continue;
                }

                $grouped[$foreignKey->table][] = [
                    'table' => $table,
                    'column' => $foreignKey->from,
                    'delete_rule' => $foreignKey->on_delete ?: 'NO ACTION',
                ];
            }
        }

        return $grouped;
    }

    private function tableHasNoId(string $table): bool
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $quoted = str_replace("'", "''", $table);

            return collect(DB::select("PRAGMA table_info('{$quoted}')"))
                ->doesntContain(fn ($column) => $column->name === 'id');
        }

        return DB::select(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [DB::getDatabaseName(), $table, 'id']
        ) === [];
    }
}
