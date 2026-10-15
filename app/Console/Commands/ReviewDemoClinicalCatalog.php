<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReviewDemoClinicalCatalog extends Command
{
    protected $signature = 'hms:review-demo-catalog
        {--hospital= : Required hospital ID; never touch another hospital}
        {--apply : Deactivate matching records (default is read-only preview)}';

    protected $description = 'Preview or deactivate identifiable clinical and procurement demonstration catalogue entries without deleting historical references.';

    public function handle(): int
    {
        $hospitalId = filter_var($this->option('hospital'), FILTER_VALIDATE_INT);
        if (!$hospitalId || $hospitalId < 1 || !DB::table('hospitals')->where('id', $hospitalId)->exists()) {
            $this->error('Specify a valid --hospital=ID. No changes made.');
            return self::FAILURE;
        }

        $catalogues = [
            'lab_tests' => ['code', 'name'],
            'lab_test_profiles' => ['code', 'name'],
            'lab_specimen_types' => ['code', 'name'],
            'lab_units' => ['code', 'name'],
            'radiology_studies' => ['code', 'name'],
            'radiology_modalities' => ['code', 'name'],
            'blood_component_types' => ['code', 'name'],
            'blood_screening_tests' => ['code', 'name'],
            'blood_donor_categories' => ['code', 'name'],
            'inventory_items' => ['sku', 'name'],
            'inventory_units' => ['code', 'name'],
            'inventory_locations' => ['code', 'name'],
        ];

        $knownCodes = [
            'LAB-CONFIG', 'LAB-PANEL-CONFIG', 'BB-SCREEN-CONFIG',
            'XR-CONFIG', 'MED-CONFIG', 'CONFIG',
        ];
        $count = 0;
        foreach ($catalogues as $table => [$codeColumn, $nameColumn]) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'hospital_id')
                || !Schema::hasColumn($table, 'is_active')
                || !Schema::hasColumn($table, $codeColumn)
                || !Schema::hasColumn($table, $nameColumn)) {
                continue;
            }
            $records = DB::table($table)->where('hospital_id', $hospitalId)
                ->where('is_active', true)->get(['id', $codeColumn, $nameColumn]);
            foreach ($records as $record) {
                $code = (string) $record->{$codeColumn};
                $name = (string) $record->{$nameColumn};
                $phaseCode = preg_match('/^(?:P|B)(?:HASE)?[0-9]+[A-Z]?(?:[-_]|$)/i', $code) === 1;
                $phaseName = preg_match('/\bphase\s+[0-9]+[A-Z]?\b/i', $name) === 1;
                $structural = in_array($code, $knownCodes, true)
                    && (stripos($name, 'example') !== false || stripos($name, 'configure') !== false);
                $placeholder = (stripos($name, 'configure professionally') !== false
                    || stripos($name, 'structural example') !== false
                    || stripos($name, 'do not dispense until configured') !== false);
                if (!(($phaseCode && $phaseName) || $structural || $placeholder)) {
                    continue;
                }
                ++$count;
                $this->line(sprintf('%s #%d [%s] %s', $table, $record->id, $code, $name));
                if ($this->option('apply')) {
                    DB::table($table)->where('id', $record->id)
                        ->where('hospital_id', $hospitalId)
                        ->where($codeColumn, $code)
                        ->where($nameColumn, $name)
                        ->where('is_active', true)
                        ->update(['is_active' => false]);
                }
            }
        }
        // Supplier status is a string, unlike clinical catalogues' is_active column.
        // Only match unmistakably phase-labelled smoke suppliers; never infer from amount or ID.
        if (Schema::hasTable('suppliers')) {
            $suppliers = DB::table('suppliers')->where('hospital_id', $hospitalId)
                ->where('status', 'active')->get(['id', 'code', 'name']);
            foreach ($suppliers as $supplier) {
                $code = (string) $supplier->code;
                $name = (string) $supplier->name;
                if (preg_match('/^P[0-9]+[A-Z]?[-_]/i', $code) !== 1
                    || preg_match('/\\bphase\\s+[0-9]+[A-Z]?\\b/i', $name) !== 1
                    || stripos($name, 'smoke') === false) {
                    continue;
                }
                ++$count;
                $this->line(sprintf('suppliers #%d [%s] %s', $supplier->id, $code, $name));
                if ($this->option('apply')) {
                    DB::table('suppliers')->where('id', $supplier->id)
                        ->where('hospital_id', $hospitalId)
                        ->where('code', $code)->where('name', $name)
                        ->where('status', 'active')->update(['status' => 'inactive']);
                }
            }
        }
        $this->info($this->option('apply')
            ? "Finished: up to {$count} matching demo entries deactivated; no rows deleted."
            : "Preview: {$count} candidate entries. No changes made. Run with --apply only after review and backup.");
        $this->warn('Items referenced by clinical records remain in the database. User-created nonmatching entries are untouched.');
        return self::SUCCESS;
    }
}
