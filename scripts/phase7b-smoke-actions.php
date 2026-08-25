<?php

use App\Models\BloodComponent;
use App\Models\BloodRequest;
use App\Models\User;
use App\Services\BloodRequestWorkflowService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$request = BloodRequest::findOrFail((int) ($argv[1] ?? 0));
$tech = User::where('email', $argv[2] ?? 'phase7b-tech@example.test')->firstOrFail();
$auth = User::where('email', $argv[3] ?? 'phase7b-auth@example.test')->firstOrFail();
$workflow = app(BloodRequestWorkflowService::class);
$components = BloodComponent::whereIn('id', array_slice($argv, 4))->get();

$workflow->transition($request->fresh(), 'submitted', $tech, 'Smoke submit');
$workflow->transition($request->fresh(), 'accepted', $auth, 'Smoke accept');
$specimen = $workflow->collectSpecimen($request->fresh(), ['patient_confirmed_name' => $request->patient->full_name, 'patient_confirmed_identifier' => $request->patient->hospital_number, 'label_status' => 'matched', 'collection_location' => 'Smoke ward'], $tech);
$group = $workflow->enterPatientGroup($request->fresh(), ['blood_request_specimen_id' => $specimen->id, 'abo_group' => 'O', 'rh_factor' => 'positive'], $tech);
$workflow->verifyPatientGroup($group->fresh(), $auth);

$issues = [];
foreach ($components as $component) {
    $test = $workflow->enterCompatibility($request->fresh(), ['blood_request_specimen_id' => $specimen->id, 'blood_component_id' => $component->id, 'result' => 'Compatible by manual smoke entry', 'interpretation' => 'Manual smoke authorization.'], $tech);
    $workflow->authorizeCompatibility($test->fresh(), $auth);
    $reservation = $workflow->reserve($request->fresh(), $component->fresh(), $tech);
    $issues[] = $workflow->issue($request->fresh(), $reservation->fresh(), ['received_by_name' => 'Smoke Receiver', 'receiver_role' => 'Nurse', 'destination' => 'Smoke ward'], $auth);
}

$workflow->returnToStock($issues[0]->fresh(), ['return_reason' => 'Smoke return', 'return_assessment' => 'Authorized suitability assessment recorded.'], $auth);
$workflow->reverseIssue($issues[1]->fresh(), 'Smoke reversal', $auth);

echo "Phase 7B smoke actions completed. Issue {$issues[0]->issue_number}\n";
