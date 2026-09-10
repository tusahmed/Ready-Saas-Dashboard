<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkspaceSetting;
use App\Rules\SafePassword;
use App\Services\ActivityLogger;
use App\Services\TenantProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['active', 'suspended'])], 'plan' => ['nullable', Rule::in(['starter', 'growth', 'enterprise'])]]);
        $clients = Tenant::withCount('users')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$request->q.'%')->orWhere('email', 'like', '%'.$request->q.'%')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('plan'), fn ($q) => $q->where('plan', $request->plan))->latest()->paginate(12)->withQueryString();

        return view('clients.index', compact('clients'));
    }

    public function create()
    {
        return view('clients.form', ['client' => new Tenant(['status' => 'active', 'plan' => 'starter'])]);
    }

    public function store(Request $request, TenantProvisioner $provisioner)
    {
        $data = $this->validated($request);
        $owner = $request->validate(['owner_name' => ['required', 'string', 'max:120'], 'owner_email' => ['required', 'email', 'max:255', 'unique:users,email'], 'owner_password' => ['required', 'confirmed', new SafePassword, Password::min(12)->mixedCase()->numbers()]]);
        $client = DB::transaction(function () use ($request, $data, $owner, $provisioner) {
            $client = $provisioner->create($data, ['name' => $owner['owner_name'], 'email' => $owner['owner_email'], 'password' => $owner['owner_password'], 'locale' => $request->user()->locale, 'status' => 'active']);
            ActivityLogger::record($request->user(), 'clients.created', $client->name, route('admin.clients.show', $client, false));

            return $client;
        });

        return $this->redirectFor($request, 'clients.view', 'admin.clients.show', $client)->with('success', __('app.saved'));
    }

    public function show(Tenant $client)
    {
        $client->load(['users' => fn ($q) => $q->with('role')->orderBy('name')]);

        return view('clients.show', ['client' => $client, 'stats' => ['users' => $client->users()->count(), 'roles' => $client->roles()->count(), 'active_users' => $client->users()->where('status', 'active')->count()]]);
    }

    public function edit(Tenant $client)
    {
        return view('clients.form', compact('client'));
    }

    public function update(Request $request, Tenant $client)
    {
        $data = $this->validated($request, $client);
        DB::transaction(function () use ($request, $data, $client) {
            $client->update($data);
            if ($client->status === 'suspended') {
                DB::table('sessions')->whereIn('user_id', $client->users()->select('id'))->delete();
            }
            ActivityLogger::record($request->user(), 'clients.updated', $client->name, route('admin.clients.show', $client, false));
        });

        return $this->redirectFor($request, 'clients.view', 'admin.clients.show', $client)->with('success', __('app.saved'));
    }

    public function destroy(Request $request, Tenant $client)
    {
        DB::transaction(function () use ($request, $client) {
            ActivityLogger::record($request->user(), 'clients.deleted', $client->name);
            DB::table('notifications')->where('notifiable_type', User::class)->whereIn('notifiable_id', $client->users()->select('id'))->delete();
            DB::table('sessions')->whereIn('user_id', $client->users()->select('id'))->delete();
            DB::table('password_reset_tokens')->whereIn('email', $client->users()->select('email'))->delete();
            WorkspaceSetting::where('tenant_id', $client->id)->each(fn (WorkspaceSetting $settings) => $settings->delete());
            $client->delete();
        });

        return $this->redirectFor($request, 'clients.view', 'admin.clients.index')->with('success', __('app.deleted'));
    }

    private function validated(Request $request, ?Tenant $client = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'], 'slug' => ['required', 'alpha_dash:ascii', 'max:80', Rule::unique('tenants')->ignore($client?->id)],
            'email' => ['required', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:40'], 'website' => ['nullable', 'url:http,https', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'], 'plan' => ['required', Rule::in(['starter', 'growth', 'enterprise'])],
            'status' => ['required', Rule::in(['active', 'suspended'])], 'trial_ends_at' => ['nullable', 'date'],
        ]);
    }
}
