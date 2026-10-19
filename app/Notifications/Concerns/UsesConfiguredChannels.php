<?php

namespace App\Notifications\Concerns;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Los canales (campana / correo) los define la configuración del evento, no la clase:
 * NotificationDispatcher los asigna con withChannels() antes de enviar.
 */
trait UsesConfiguredChannels
{
    /** @var list<string> */
    protected array $configuredChannels = ['database'];

    /** @param list<string> $channels */
    public function withChannels(array $channels): static
    {
        $this->configuredChannels = $channels;

        return $this;
    }

    public function via(object $notifiable): array
    {
        return $this->configuredChannels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->toArray($notifiable);

        return (new MailMessage)
            ->subject((string) ($data['title'] ?? 'Notificación del sistema'))
            ->line((string) ($data['body'] ?? ''))
            ->action('Abrir en el sistema', url((string) ($data['url'] ?? '/')));
    }
}
