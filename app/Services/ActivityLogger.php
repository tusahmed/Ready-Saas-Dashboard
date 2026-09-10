<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\User;
use App\Notifications\SystemNotification;

class ActivityLogger
{
    public static function record(User $actor, string $action, string $description, ?string $url = null): void
    {
        Activity::create(['tenant_id' => $actor->tenant_id, 'user_id' => $actor->id, 'action' => $action, 'description' => mb_substr($description, 0, 255)]);
        $actor->notify(new SystemNotification(['title_key' => 'app.saved', 'body' => $description, 'url' => $url]));
    }
}
