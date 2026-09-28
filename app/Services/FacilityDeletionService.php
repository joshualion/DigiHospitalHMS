<?php

namespace App\Services;

use App\Models\Facility;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FacilityDeletionService
{
    public function dependencyCount(Facility $facility): int
    {
        return collect($this->references(DB::connection()))
            ->sum(fn (array $reference): int => (int) DB::table($reference['table'])
                ->where($reference['column'], $facility->id)
                ->count());
    }

    public function reassignAndDelete(Facility $source, Facility $target): array
    {
        if ($source->hospital_id !== $target->hospital_id) {
            throw ValidationException::withMessages(['facility' => 'The replacement facility must belong to the same hospital.']);
        }

        if ($source->is($target)) {
            throw ValidationException::withMessages(['facility' => 'Choose a different replacement facility.']);
        }

        if (! $target->isActive()) {
            throw ValidationException::withMessages(['facility' => 'The replacement facility must be active.']);
        }

        return DB::transaction(function () use ($source, $target): array {
            $this->mergeFacilityMemberships($source, $target);
            $this->mergeServiceAssignments($source, $target);
            $this->mergeNumberSequences($source, $target);

            $moved = [];

            try {
                foreach ($this->references(DB::connection()) as $reference) {
                    $count = DB::table($reference['table'])
                        ->where($reference['column'], $source->id)
                        ->update([$reference['column'] => $target->id]);

                    if ($count > 0) {
                        $moved["{$reference['table']}.{$reference['column']}"] = $count;
                    }
                }

                $source->delete();
            } catch (QueryException $exception) {
                throw ValidationException::withMessages([
                    'facility' => 'The facility could not be reassigned because one of the target facility records conflicts with existing data. Nothing was changed. Resolve the duplicate configuration/operational record and try again. Database detail: '.str($exception->getMessage())->limit(220),
                ]);
            }

            return $moved;
        });
    }

    private function mergeFacilityMemberships(Facility $source, Facility $target): void
    {
        if (! DB::getSchemaBuilder()->hasTable('facility_memberships')) {
            return;
        }

        $sourceRows = DB::table('facility_memberships')->where('facility_id', $source->id)->get();

        foreach ($sourceRows as $row) {
            $targetRow = DB::table('facility_memberships')
                ->where('facility_id', $target->id)
                ->where('staff_profile_id', $row->staff_profile_id)
                ->first();

            if (! $targetRow) {
                continue;
            }

            if ((bool) $row->is_default && ! (bool) $targetRow->is_default) {
                DB::table('facility_memberships')->where('id', $targetRow->id)->update(['is_default' => true]);
            }

            DB::table('facility_memberships')->where('id', $row->id)->delete();
        }
    }

    private function mergeServiceAssignments(Facility $source, Facility $target): void
    {
        if (! DB::getSchemaBuilder()->hasTable('billable_service_facility')) {
            return;
        }

        $sourceRows = DB::table('billable_service_facility')->where('facility_id', $source->id)->get();

        foreach ($sourceRows as $row) {
            $duplicate = DB::table('billable_service_facility')
                ->where('facility_id', $target->id)
                ->where('billable_service_id', $row->billable_service_id)
                ->exists();

            if ($duplicate) {
                DB::table('billable_service_facility')->where('id', $row->id)->delete();
            }
        }
    }

    private function mergeNumberSequences(Facility $source, Facility $target): void
    {
        if (! DB::getSchemaBuilder()->hasTable('number_sequences')) {
            return;
        }

        $sourceRows = DB::table('number_sequences')->where('facility_id', $source->id)->get();

        foreach ($sourceRows as $row) {
            $duplicate = DB::table('number_sequences')
                ->where('hospital_id', $row->hospital_id)
                ->where('facility_id', $target->id)
                ->where('key', $row->key)
                ->exists();

            if ($duplicate) {
                DB::table('number_sequences')->where('id', $row->id)->delete();
            }
        }
    }

    private function references(ConnectionInterface $connection): array
    {
        return match ($connection->getDriverName()) {
            'mysql', 'mariadb' => $this->mysqlReferences(),
            'sqlite' => $this->sqliteReferences(),
            default => [],
        };
    }

    private function mysqlReferences(): array
    {
        return collect(DB::select(
            "SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
               AND REFERENCED_TABLE_NAME = 'facilities'
               AND REFERENCED_COLUMN_NAME = 'id'"
        ))->map(fn ($row): array => [
            'table' => $row->table_name,
            'column' => $row->column_name,
        ])->values()->all();
    }

    private function sqliteReferences(): array
    {
        $tables = DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'");
        $references = [];

        foreach ($tables as $table) {
            $safeTable = str_replace("'", "''", $table->name);
            foreach (DB::select("PRAGMA foreign_key_list('{$safeTable}')") as $foreignKey) {
                if (($foreignKey->table ?? null) === 'facilities' && ($foreignKey->to ?? null) === 'id') {
                    $references[] = ['table' => $table->name, 'column' => $foreignKey->from];
                }
            }
        }

        return $references;
    }
}
