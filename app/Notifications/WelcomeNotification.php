<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public $tries = 3;

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $dashboardUrl = rtrim(config('app.url'), '/').'/dashboard';
        $studentId = $notifiable->student_id ?? 'N/A';
        $roomNumber = $notifiable->room_number ?? 'N/A';
        $credits = $notifiable->credits ?? 8;

        return (new MailMessage)
            ->subject('Bienvenue sur le portail Buanderie - Centrale Casablanca')
            ->greeting('Bonjour '.($notifiable->name ?? 'Étudiant').',')
            ->line('Félicitations ! Votre compte buanderie a été créé avec succès sur le portail officiel de l\'École Centrale Casablanca.')
            ->line("Numéro Étudiant : **{$studentId}**")
            ->line("Chambre Résidence : **{$roomNumber}**")
            ->line("Quota hebdomadaire disponible : **{$credits} heures** pour vos cycles de lavage et séchage.")
            ->action('Accéder à mon espace buanderie', $dashboardUrl)
            ->line('Vous pouvez dès à présent consulter le calendrier des machines et réserver vos créneaux en quelques clics.')
            ->line('Pour toute question ou signalement technique, contactez le support de la résidence.')
            ->salutation("Cordialement,\nL'équipe Buanderie Centrale Casablanca");
    }
}
