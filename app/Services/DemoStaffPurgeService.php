<?php

namespace App\Services;

use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoStaffPurgeService
{
    private array $foreignKeysByReferencedTable = [];
    private array $visited = [];
    private int $deletedRows = 0;
    private int $nulledReferences = 0;

    public function purge(StaffProfile $staff, User $actor): array
    {
        $staff->loadMissing('user');
        $user = $staff->user;

        if (! $user) {
            throw new RuntimeException('This staff profile no longer has a user account.');
        }

        if ($actor->is($user)) {
            throw new RuntimeException('You cannot purge the account you are currently signed in with.');
        }

        $this->foreignKeysByReferencedTable = $this->foreignKeys();
        $this->visited = [];
        $this->deletedRows = 0;
        $this->nulledReferences = 0;

        return DB::transaction(function () use ($staff, $user): array {
            // Spatie's polymorphic role/permission pivots do not reference users with
            // a database foreign key, so remove them explicitly before the user row.
            DB::table('model_has_roles')
                ->where('model_type', User::class)
                ->where('model_id', $user->id)
                ->delete();

            DB::table('model_has_permissions')
                ->where('model_type', User::class)
                ->where('model_id', $user->id)
                ->delete();

            $this->purgeRow('staff_profiles', (int) $staff->id);
            $this->purgeRow('users', (int) $user->id);

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

        if (! DB::table($table)->where('id', $id)->exists()) {
            return;
        }

        foreach ($this->foreignKeysByReferencedTable[$table] ?? [] as $foreignKey) {
            $childTable = $foreignKey['table'];
            $column = $foreignKey['column'];
            $deleteRule = strtoupper($foreignKey['delete_rule']);

            if ($deleteRule === 'SET NULL') {
                $updated = DB::table($childTable)->where($column, $id)->update([$column => null]);
                $this->nulledReferences += $updated;
                continue;
            }

            if ($this->tableHasNoId($childTable)) {
                $this->deletedRows += DB::table($childTable)->where($column, $id)->delete();
                continue;
            }

            $childIds = DB::table($childTable)
                ->where($column, $id)
                ->pluck('id')
                ->all();

            foreach ($childIds as $childId) {
                $this->purgeRow($childTable, $childId);
            }
        }

        $this->deletedRows += DB::table($table)->where('id', $id)->delete();
    }

    private function foreignKeys(): array
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'mysql', 'mariadb' => $this->mysqlForeignKeys(),
            'sqlite' => $this->sqliteForeignKeys(),
            default => throw new RuntimeException("Demo staff purge is not configured for the {$driver} database driver."),
        };
    }

    private function mysqlForeignKeys(): array
    {
        $database = DB::getDatabaseName();

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
            [$database]
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
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $quoted = str_replace("'", "''", $table);

            return collect(DB::select("PRAGMA table_info('{$quoted}')"))
                ->doesntContain(fn ($column) => $column->name === 'id');
        }

        $columns = DB::select(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [DB::getDatabaseName(), $table, 'id']
        );

        return $columns === [];
    }
}
