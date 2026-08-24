<?php

use App\Models\BloodBankLocation;
use App\Models\BloodDonation;
use App\Models\User;
use App\Services\BloodBankWorkflowService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$donation = BloodDonation::with('components')->findOrFail((int) ($argv[1] ?? 0));
$actor = User::where('email', $argv[2] ?? 'phase7a-verifier@example.test')->firstOrFail();
$target = BloodBankLocation::where('hospital_id', $donation->hospital_id)->where('name', $argv[3] ?? 'Phase 7A Satellite Storage')->firstOrFail();
$component = $donation->components()->latest()->firstOrFail();
$workflow = app(BloodBankWorkflowService::class);

if ($component->state === 'quarantined') {
    $workflow->releaseComponent($component->fresh(), $actor, 'Smoke quarantine release');
}

$component = $component->fresh();
if ($component->state === 'available') {
    $workflow->transferComponent($component, $target, null, $actor, 'Smoke transfer');
}

$component = $component->fresh();
if ($component->state === 'transferred') {
    $workflow->recallComponent($component, $actor, 'Smoke recall');
}

echo "Phase 7A smoke component actions completed.\n";
