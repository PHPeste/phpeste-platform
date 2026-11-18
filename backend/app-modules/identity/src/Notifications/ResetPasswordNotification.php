<?php

declare(strict_types=1);

namespace PHPeste\Identity\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use PHPeste\Identity\Models\User;

class ResetPasswordNotification extends Notification
{
    public function __construct(public readonly string $token) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $minutes = (int) config('auth.passwords.users.expire');

        return (new MailMessage)
            ->subject('Redefinir sua senha')
            ->greeting("Oi, {$notifiable->name}!")
            ->line('Recebemos um pedido para redefinir a senha da sua conta na PHPeste Platform.')
            ->action('Escolher nova senha', $this->url($notifiable))
            ->line("O link vale por {$minutes} minutos e só pode ser usado uma vez.")
            ->line('Se não foi você, ignore este e-mail: sua senha continua a mesma.')
            ->salutation('Equipe PHPeste');
    }

    public function url(User $notifiable): string
    {
        return mb_rtrim((string) config('app.admin_url'), '/').'/redefinir-senha?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->email,
        ]);
    }
}
