<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use App\Policies\Concerns\AuthorizesFoundationAccess;

class InvoicePolicy
{
    use AuthorizesFoundationAccess;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'invoices.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->allowed($user, 'invoices.view', $invoice);
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, 'invoices.create');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->allowed($user, 'invoices.create', $invoice);
    }

    public function issue(User $user, Invoice $invoice): bool
    {
        return $this->allowed($user, 'invoices.issue', $invoice);
    }

    public function void(User $user, Invoice $invoice): bool
    {
        return $this->allowed($user, 'invoices.void', $invoice);
    }
}
