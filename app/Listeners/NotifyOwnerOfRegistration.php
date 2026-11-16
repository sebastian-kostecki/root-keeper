<?php

namespace App\Listeners;

use App\Models\User;
use App\Notifications\NewUserRegistered;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Notification;

class NotifyOwnerOfRegistration
{
    public function handle(Registered $event): void
    {
        $address = config('registration.notification_mail');

        if (blank($address) || ! $event->user instanceof User) {
            return;
        }

        Notification::route('mail', $address)
            ->notify(NewUserRegistered::for($event->user, request()->ip()));
    }
}
