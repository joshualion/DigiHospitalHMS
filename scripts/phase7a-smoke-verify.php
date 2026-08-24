<?php

use App\Models\BloodDonation;
use App\Models\User;
use App\Services\BloodBankWorkflowService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$donation = BloodDonation::with(['groupResult', 'screeningResults'])->findOrFail((int) ($argv[1] ?? 0));
$verifier = User::where('email', $argv[2] ?? 'phase7a-verifier@example.test')->firstOrFail();
$workflow = app(BloodBankWorkflowService::class);

if ($donation->groupResult?->status === 'draft') {
    $workflow->verifyGroup($donation->groupResult, $verifier);
}

foreach ($donation->screeningResults as $result) {
    if ($result->status === 'draft') {
        $workflow->verifyScreeningResult($result, $verifier);
    }
}

echo "Phase 7A smoke verification completed.\n";
