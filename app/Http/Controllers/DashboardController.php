<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        return redirect()->route($request->user()->isPlatform() ? 'admin.dashboard' : 'client.dashboard');
    }

    public function client(Request $request)
    {
        abort_if($request->user()->isPlatform(), 403);

        return view('dashboard.client');
    }

    public function admin(Request $request)
    {
        // Client statistics are permission-gated as well as the client list itself.
        $visible = $request->user()->canDo('clients.view');
        $stats = ['clients' => 0, 'active_clients' => 0, 'users' => 0, 'new_clients' => 0];
        $recentClients = collect();
        $growth = collect();
        $planCounts = ['starter' => 0, 'growth' => 0, 'enterprise' => 0];
        if ($visible) {
            $stats = ['clients' => Tenant::count(), 'active_clients' => Tenant::where('status', 'active')->count(), 'users' => User::whereNotNull('tenant_id')->count(), 'new_clients' => Tenant::where('created_at', '>=', now()->startOfMonth())->count()];
            $recentClients = Tenant::withCount('users')->latest()->take(5)->get();
            $counts = Tenant::selectRaw('plan, COUNT(*) as total')->groupBy('plan')->pluck('total', 'plan')->all();
            $planCounts = array_replace($planCounts, $counts);
            $growth = collect(range(5, 0))->map(function ($offset) {
                $month = now()->startOfMonth()->subMonths($offset);

                return ['label' => $month->translatedFormat('M'), 'count' => Tenant::whereBetween('created_at', [$month, $month->copy()->endOfMonth()])->count()];
            });
        }
        $recentActivity = Activity::with('user')->whereNull('tenant_id')->when(! $request->user()->is_super_admin, fn ($q) => $q->where('user_id', $request->user()->id))->latest()->take(5)->get();

        return view('dashboard.admin', compact('stats', 'recentClients', 'growth', 'planCounts', 'recentActivity'));
    }
}
