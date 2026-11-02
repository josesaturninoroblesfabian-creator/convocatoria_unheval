<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Application $application)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Cargo de postulación · UNHEVAL')->greeting('Hola, '.$notifiable->name)->line('Tu expediente fue recibido. Código: '.$this->application->reference)->line('La recepción no implica que hayas sido declarado apto.')->action('Consultar mi expediente', route('applications.show', $this->application))->line('Conserva este cargo para realizar el seguimiento.');
    }
}
