<?php

namespace App\Policies;

use App\Models\InventoryItem;
use App\Models\User;
use App\Policies\Concerns\AuthorizesFoundationAccess;

class InventoryItemPolicy
{
    use AuthorizesFoundationAccess;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'inventory.view');
    }

    public function view(User $user, InventoryItem $item): bool
    {
        return $this->allowed($user, 'inventory.view', $item);
    }

    public function manageCatalogue(User $user): bool
    {
        return $this->allowed($user, 'inventory.catalogue.manage');
    }

    public function receive(User $user): bool
    {
        return $this->allowed($user, 'inventory.stock.receive');
    }

    public function transfer(User $user, ?InventoryItem $item = null): bool
    {
        return $this->allowed($user, 'inventory.stock.transfer', $item);
    }

    public function adjust(User $user, ?InventoryItem $item = null): bool
    {
        return $this->allowed($user, 'inventory.stock.adjust', $item);
    }

    public function approveAdjustment(User $user, ?InventoryItem $item = null): bool
    {
        return $this->allowed($user, 'inventory.adjustments.approve', $item);
    }
}
