<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CycleFinishedNotification extends Notification
{
    public function __construct(public string $machineName) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Votre cycle de buanderie est terminé')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Le cycle sur '.$this->machineName.' est terminé. Vous pouvez récupérer votre linge.')
            ->action('Mon espace buanderie', rtrim(config('app.url'), '/').'/dashboard');
    }
}
