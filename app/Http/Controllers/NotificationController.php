<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Support\InternalUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['filter' => ['nullable', Rule::in(['all', 'unread'])]]);
        $notifications = $request->user()->notifications()->when($request->input('filter') === 'unread', fn ($q) => $q->whereNull('read_at'))->paginate(15)->withQueryString();

        return view('notifications.index', compact('notifications'));
    }

    public function feed(Request $request)
    {
        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'notifications' => $request->user()->notifications()->limit(10)->get()->map(fn ($item) => [
                'id' => $item->id, 'title' => $item->data['title'] ?? __($item->data['title_key'] ?? 'app.notification'),
                'body' => $item->data['body'] ?? __($item->data['body_key'] ?? 'app.saved'),
                'url' => InternalUrl::safe($item->data['url'] ?? null), 'read_at' => $item->read_at?->toIso8601String(), 'created_at' => $item->created_at->toIso8601String(),
            ]),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function read(Request $request, string $notification)
    {
        $request->user()->notifications()->findOrFail($notification)->markAsRead();

        return $request->expectsJson() ? response()->json(['saved' => true]) : back()->with('success', __('app.saved'));
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $request->expectsJson() ? response()->json(['saved' => true]) : back()->with('success', __('app.saved'));
    }

    public function destroy(Request $request, string $notification)
    {
        $request->user()->notifications()->findOrFail($notification)->delete();

        return back()->with('success', __('app.deleted'));
    }

    public function create()
    {
        return view('notifications.create', ['clients' => Tenant::orderBy('name')->get(['id', 'name'])]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'], 'body' => ['required', 'string', 'max:2000'],
            'audience' => ['required', Rule::in(['all', 'platform', 'client'])],
            'tenant_id' => ['required_if:audience,client', 'nullable', 'integer', 'exists:tenants,id'],
            'url' => ['nullable', 'string', 'max:255', function ($attribute, $value, $fail) {
                if (! str_starts_with($value, '/') || str_starts_with($value, '//') || preg_match('/[\\\\\x00-\x20]/', $value)) {
                    $fail(__('app.internal_url_only'));
                }
            }],
        ]);
        DB::transaction(function () use ($request, $data) {
            $recipients = User::query()->where('status', 'active')
                ->when($data['audience'] === 'platform', fn ($q) => $q->whereNull('tenant_id'))
                ->when($data['audience'] === 'client', fn ($q) => $q->where('tenant_id', $data['tenant_id']));
            // Bounded batches work synchronously on shared hosting; no worker is required.
            $recipients->chunkById(100, function ($users) use ($data) {
                foreach ($users as $user) {
                    $user->notify(new SystemNotification(['title' => $data['title'], 'body' => $data['body'], 'url' => $data['url'] ?? null]));
                }
            });
            Activity::create(['tenant_id' => null, 'user_id' => $request->user()->id, 'action' => 'notifications.sent', 'description' => $data['title']]);
        });

        return redirect()->route('notifications.index')->with('success', __('app.notification_sent'));
    }
}
