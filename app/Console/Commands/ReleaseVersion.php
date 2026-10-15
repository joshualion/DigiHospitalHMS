<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ReleaseVersion extends Command
{
    protected $signature = 'hms:version {--json : Machine-readable version information}';
    protected $description = 'Show deployed application release identity without exposing credentials';

    public function handle(): int
    {
        $release = trim((string) config('release.version', ''));
        $revision = trim((string) config('release.revision', ''));

        // The release version is operator-controlled; the commit is recorded by deployment.
        $details = [
            'application' => 'DigiHospitalHMS',
            'version' => $release !== '' ? $release : 'unversioned',
            'revision' => $revision !== '' ? $revision : 'unknown',
            'environment' => app()->environment(),
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            foreach ($details as $key => $value) {
                $this->line(ucfirst($key).': '.$value);
            }
        }

        return self::SUCCESS;
    }
}
