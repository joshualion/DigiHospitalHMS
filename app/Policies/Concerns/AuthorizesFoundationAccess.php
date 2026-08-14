<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait AuthorizesFoundationAccess
{
    protected function allowed(User $user, string $permission, ?Model $model = null): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        if ($user->hasRole('superadmin')) {
            return true;
        }

        if (! $user->can($permission)) {
            return false;
        }

        if (! $model || ! isset($model->hospital_id)) {
            return true;
        }

        return $user->hospitalId() === (int) $model->hospital_id;
    }
}
