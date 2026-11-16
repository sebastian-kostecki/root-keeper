<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the owner that someone created an account, so an unwanted one can be removed.
 */
class NewUserRegistered extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $userId,
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $ip,
    ) {}

    public static function for(User $user, ?string $ip): self
    {
        return new self($user->id, $user->name, $user->email, $ip);
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('New account in :app', ['app' => config('app.name')]))
            ->line(__('Someone created an account in :app.', ['app' => config('app.name')]))
            ->line(__('Name: :name', ['name' => $this->name]))
            ->line(__('E-mail: :email', ['email' => $this->email]))
            ->line(__('IP address: :ip', ['ip' => $this->ip ?? '-']))
            ->line(__('Account ID: :id', ['id' => $this->userId]))
            ->line(__('The account can use the application only after verifying its e-mail address. An account that does not do so within :days days is deleted automatically.', ['days' => config('registration.unverified_days')]));
    }
}
