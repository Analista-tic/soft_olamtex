<?php

namespace App\Notifications;

use App\Notifications\Concerns\UsesConfiguredChannels;
use Illuminate\Notifications\Notification;

/** Aviso de un evento sin clase propia: el contenido lo arma quien lo dispara. */
class GenericEventNotification extends Notification
{
    use UsesConfiguredChannels;

    public function __construct(
        protected string $type,
        protected string $module,
        protected string $title,
        protected string $body,
        protected string $url = '/',
    ) {}

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'module' => $this->module,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
        ];
    }
}
