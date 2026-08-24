<?php

namespace App\Policies;

use App\Models\InpatientChart;
use App\Models\User;
use App\Policies\Concerns\AuthorizesFoundationAccess;

class InpatientChartPolicy
{
    use AuthorizesFoundationAccess;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'inpatient.view');
    }

    public function view(User $user, InpatientChart $chart): bool
    {
        return $this->allowed($user, 'inpatient.view', $chart);
    }

    public function document(User $user, InpatientChart $chart): bool
    {
        return $this->allowed($user, 'inpatient.document', $chart);
    }

    public function sign(User $user, InpatientChart $chart): bool
    {
        return $this->allowed($user, 'inpatient.sign', $chart);
    }

    public function orders(User $user, InpatientChart $chart): bool
    {
        return $this->allowed($user, 'inpatient.orders', $chart);
    }

    public function handover(User $user, InpatientChart $chart): bool
    {
        return $this->allowed($user, 'inpatient.handover', $chart);
    }

    public function signDischargeSummary(User $user, InpatientChart $chart): bool
    {
        return $this->allowed($user, 'inpatient.discharge-summary.sign', $chart);
    }
}
