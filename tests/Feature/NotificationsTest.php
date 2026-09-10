<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    private function notification(User $user, string $title = 'A saved notification')
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'Tests\\TestNotification',
            'data' => ['title' => $title, 'body' => 'Notification body', 'url' => '/profile'],
        ]);
    }

    public function test_notification_feed_page_read_and_delete_are_scoped_to_current_user(): void
    {
        $tenant = $this->tenant();
        $user = $this->user($tenant);
        $colleague = $this->user($tenant);
        $other = $this->user($this->tenant());
        $ownNotification = $this->notification($user, 'My alert');
        $colleagueNotification = $this->notification($colleague, 'Colleague secret alert');
        $foreignNotification = $this->notification($other, 'Foreign secret alert');

        $this->actingAs($user)->get(route('notifications.index'))->assertOk()->assertSee('My alert')->assertDontSee('Colleague secret alert')->assertDontSee('Foreign secret alert');
        $this->getJson(route('notifications.feed'))->assertOk()->assertJsonPath('unread_count', 1)->assertJsonFragment(['id' => $ownNotification->id])->assertJsonMissing(['id' => $colleagueNotification->id]);
        $this->patchJson(route('notifications.read', $colleagueNotification->id))->assertNotFound();
        $this->deleteJson(route('notifications.destroy', $foreignNotification->id))->assertNotFound();
        $this->patchJson(route('notifications.read', $ownNotification->id))->assertSuccessful();
        $this->assertNotNull($ownNotification->fresh()->read_at);
        $this->assertNull($colleagueNotification->fresh()->read_at);
        $this->getJson(route('notifications.feed'))->assertJsonPath('unread_count', 0);
        $this->deleteJson(route('notifications.destroy', $ownNotification->id))->assertRedirect();
        $this->assertDatabaseMissing('notifications', ['id' => $ownNotification->id]);
        $this->assertDatabaseHas('notifications', ['id' => $foreignNotification->id]);
    }

    public function test_mark_all_read_does_not_change_another_users_notifications(): void
    {
        $user = $this->user($this->tenant());
        $other = $this->user($user->tenant);
        $first = $this->notification($user);
        $second = $this->notification($user);
        $foreign = $this->notification($other);
        $this->actingAs($user)->postJson(route('notifications.read-all'))->assertSuccessful();
        $this->assertNotNull($first->fresh()->read_at);
        $this->assertNotNull($second->fresh()->read_at);
        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_root_can_send_a_notification_to_only_the_selected_client(): void
    {
        $root = $this->rootUser();
        $first = $this->tenant();
        $recipient = $this->user($first);
        $other = $this->user($this->tenant());
        $this->actingAs($root)->get(route('admin.notifications.create'))->assertOk();
        $this->post(route('admin.notifications.store'), [
            'title' => 'Scheduled maintenance', 'body' => 'A client-specific update',
            'audience' => 'client', 'tenant_id' => $first->id, 'url' => '/profile',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($recipient->notifications()->get()->contains(fn ($item) => ($item->data['title'] ?? '') === 'Scheduled maintenance'));
        $this->assertFalse($other->notifications()->get()->contains(fn ($item) => ($item->data['title'] ?? '') === 'Scheduled maintenance'));
        $this->assertFalse($root->notifications()->get()->contains(fn ($item) => ($item->data['title'] ?? '') === 'Scheduled maintenance'));
    }

    public function test_platform_audience_excludes_clients_and_all_audience_reaches_both(): void
    {
        $root = $this->rootUser();
        $staff = $this->user();
        $client = $this->user($this->tenant());
        $this->actingAs($root)->post(route('admin.notifications.store'), [
            'title' => 'Platform only', 'body' => 'Platform update', 'audience' => 'platform',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($staff->notifications()->get()->contains(fn ($item) => ($item->data['title'] ?? '') === 'Platform only'));
        $this->assertFalse($client->notifications()->get()->contains(fn ($item) => ($item->data['title'] ?? '') === 'Platform only'));
        $this->post(route('admin.notifications.store'), [
            'title' => 'Everyone', 'body' => 'General update', 'audience' => 'all',
        ])->assertRedirect()->assertSessionHasNoErrors();
        foreach ([$staff, $client] as $recipient) {
            $this->assertTrue($recipient->notifications()->get()->contains(fn ($item) => ($item->data['title'] ?? '') === 'Everyone'));
        }
    }

    public function test_client_and_unprivileged_platform_staff_cannot_broadcast(): void
    {
        foreach ([$this->owner($this->tenant()), $this->user()] as $user) {
            $this->actingAs($user)->get(route('admin.notifications.create'))->assertForbidden();
            $this->post(route('admin.notifications.store'), ['title' => 'Unauthorized', 'body' => 'Bad', 'audience' => 'all'])->assertForbidden();
        }
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_broadcast_rejects_external_or_script_urls_and_missing_client(): void
    {
        $this->actingAs($this->rootUser());
        foreach (['https://evil.example/path', '//evil.example/path', 'javascript:alert(1)'] as $url) {
            $this->post(route('admin.notifications.store'), ['title' => 'Bad link', 'body' => 'Body', 'audience' => 'all', 'url' => $url])->assertSessionHasErrors('url');
        }
        $this->post(route('admin.notifications.store'), ['title' => 'Missing client', 'body' => 'Body', 'audience' => 'client'])->assertSessionHasErrors('tenant_id');
        $this->assertDatabaseCount('notifications', 0);
    }
}
