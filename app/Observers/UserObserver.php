<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Str;
use Propaganistas\LaravelPhone\PhoneNumber;

class UserObserver
{
    public function creating(User $user)
    {
        $user->email = Str::lower($user->email);
        if ($user->phone) {
            if (!$user->phone_country) {
                $user->phone_country = 'ke';
            }
            $user->phone = (new PhoneNumber($user->phone, $user->phone_country))->formatE164();
        }
    }

    public function updating(User $user)
    {
        $user->email = Str::lower($user->email);
        if ($user->isDirty('phone')) {
            $user->phone = (new PhoneNumber($user->phone, $user->phone_country))->formatE164();
        }
    }
}
