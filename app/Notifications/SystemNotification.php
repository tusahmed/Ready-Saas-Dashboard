<?php

namespace App\Notifications;

use App\Support\InternalUrl;
use Illuminate\Notifications\Notification;

class SystemNotification extends Notification
{
    public function __construct(public array $payload) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return array_replace($this->payload, ['url' => InternalUrl::safe($this->payload['url'] ?? null)]);
    }
}
