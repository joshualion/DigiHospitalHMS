<?php

namespace App\Policies;

use App\Models\AuditEvent;
use App\Models\User;
use App\Policies\Concerns\AuthorizesFoundationAccess;

class AuditEventPolicy
{
    use AuthorizesFoundationAccess;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'audit.view');
    }

    public function view(User $user, AuditEvent $auditEvent): bool
    {
        return $this->allowed($user, 'audit.view', $auditEvent);
    }
}
