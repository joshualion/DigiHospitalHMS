<?php

namespace App\Policies;

use App\Models\QueueEntry;
use App\Models\User;
use App\Policies\Concerns\AuthorizesFoundationAccess;

class QueueEntryPolicy
{
    use AuthorizesFoundationAccess;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'queues.view');
    }

    public function update(User $user, QueueEntry $queueEntry): bool
    {
        return $this->allowed($user, 'queues.manage', $queueEntry);
    }

    public function changePriority(User $user, QueueEntry $queueEntry): bool
    {
        return $this->allowed($user, 'queues.prioritize', $queueEntry);
    }
}
