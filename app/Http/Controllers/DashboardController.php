<?php

namespace App\Http\Controllers;

use App\Models\Admission;
use App\Models\Appointment;
use App\Models\Facility;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\StaffProfile;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = request()->user();
        $hospitalId = $user->hospitalId();
        $can = fn (string $permission): bool => $user->hasRole('superadmin') || $user->can($permission);
        $today = Carbon::today();
        $cards = [];
        $trends = [];
        $days = collect(range(6, 0))->map(fn ($offset) => $today->copy()->subDays($offset)->toDateString())->values();

        if ($hospitalId && $can('patients.view')) {
            $cards[] = ['label' => 'Registered patients', 'value' => Patient::where('hospital_id', $hospitalId)->count(), 'href' => '/admin/patients'];
            $counts = Patient::where('hospital_id', $hospitalId)
                ->whereDate('created_at', '>=', $days->first())
                ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
                ->groupByRaw('DATE(created_at)')
                ->pluck('total', 'day');
            $trends[] = ['name' => 'Registrations', 'values' => $days->map(fn ($day) => (int) ($counts[$day] ?? 0))->all()];
        }

        if ($hospitalId && $can('appointments.view')) {
            $cards[] = ['label' => "Today's appointments", 'value' => Appointment::where('hospital_id', $hospitalId)->whereDate('starts_at', $today)->count(), 'href' => '/admin/appointments'];
        }

        if ($hospitalId && $can('facilities.view')) {
            $cards[] = ['label' => 'Active facilities', 'value' => Facility::where('hospital_id', $hospitalId)->where('status', 'active')->count(), 'href' => '/admin/facilities'];
        }

        if ($hospitalId && $can('staff.view')) {
            $cards[] = ['label' => 'Active staff', 'value' => StaffProfile::where('hospital_id', $hospitalId)->where('is_active', true)->count(), 'href' => '/admin/staff'];
        }

        if ($hospitalId && $can('admissions.view')) {
            $cards[] = ['label' => 'Current admissions', 'value' => Admission::where('hospital_id', $hospitalId)->where('status', 'admitted')->count(), 'href' => '/admin/admissions'];
        }

        if ($hospitalId && $can('invoices.view')) {
            $cards[] = ['label' => 'Outstanding invoice balance (NGN)', 'value' => number_format(max(0, (int) Invoice::where('hospital_id', $hospitalId)->whereNotIn('status', ['cancelled', 'voided'])->sum('balance_minor')) / 100, 2), 'href' => '/admin/billing/invoices'];
        }

        return Inertia::render('Dashboard', [
            'dashboard' => [
                'cards' => $cards,
                'days' => $days->map(fn ($day) => Carbon::parse($day)->format('D'))->all(),
                'trends' => $trends,
                'hasHospital' => (bool) $hospitalId,
            ],
        ]);
    }
}
