<?php

namespace App\Policies;

use App\Models\OutgoingMessage;
use App\Models\User;

class OutgoingMessagePolicy
{
    public function viewAny(User $user): bool
    {
        // return $user->hasPermissionTo('view any outgoing messages');
        return $user->hasPermissionTo('view any clients');
    }

    public function view(User $user, OutgoingMessage $outgoingMessage): bool
    {
        // return $user->hasPermissionTo('view outgoing messages');
        return $user->hasPermissionTo('view clients');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, OutgoingMessage $outgoingMessage): bool
    {
        return false;
    }

    public function delete(User $user, OutgoingMessage $outgoingMessage): bool
    {
        return false;
    }

    public function restore(User $user, OutgoingMessage $outgoingMessage): bool
    {
        return false;
    }

    public function forceDelete(User $user, OutgoingMessage $outgoingMessage): bool
    {
        return false;
    }
}
