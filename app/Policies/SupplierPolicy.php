<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;
use App\Policies\Concerns\AuthorizesFoundationAccess;

class SupplierPolicy
{
    use AuthorizesFoundationAccess;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'procurement.view');
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $this->allowed($user, 'procurement.view', $supplier);
    }

    public function manage(User $user, ?Supplier $supplier = null): bool
    {
        return $this->allowed($user, 'procurement.suppliers.manage', $supplier);
    }

    public function requisition(User $user, ?Supplier $supplier = null): bool
    {
        return $this->allowed($user, 'procurement.requisitions.create', $supplier);
    }

    public function approve(User $user, ?Supplier $supplier = null): bool
    {
        return $this->allowed($user, 'procurement.requisitions.approve', $supplier);
    }

    public function receive(User $user, ?Supplier $supplier = null): bool
    {
        return $this->allowed($user, 'procurement.receive', $supplier);
    }

    public function reverse(User $user, ?Supplier $supplier = null): bool
    {
        return $this->allowed($user, 'procurement.reverse', $supplier);
    }
}
