@extends('layouts.app')
@section('title', __('app.board'))
@section('content')
<div class="page-heading"><div><h1 class="page-title">{{ __('app.board') }}</h1><p class="page-description">{{ __('app.board_description') }}</p></div><div class="page-actions"><span class="date-label"><x-icon name="calendar" />{{ now()->translatedFormat('d F Y') }}</span>@if(auth()->user()->canDo('clients.create'))<a href="{{ route('admin.clients.create') }}" class="btn btn-primary"><x-icon name="plus" />{{ __('app.add_client') }}</a>@endif</div></div>
@if(auth()->user()->canDo('clients.view'))
<div class="stats-grid">
    @foreach([['clients', 'total_clients', 'building-2', 'all_workspaces'], ['active_clients', 'active_clients', 'activity', 'currently_active'], ['users', 'total_users', 'users', 'across_workspaces'], ['new_clients', 'new_clients', 'trending-up', 'this_month']] as [$key, $label, $icon, $hint])
    <article class="stat-card"><div class="stat-top"><span class="stat-label">{{ __('app.'.$label) }}</span><span class="stat-icon"><x-icon :name="$icon" /></span></div><strong class="stat-value">{{ number_format($stats[$key]) }}</strong><span class="stat-footnote">{{ __('app.'.$hint) }}</span></article>
    @endforeach
</div>
<div class="dashboard-grid">
    <section class="card"><div class="card-header"><div><h2 class="card-title">{{ __('app.client_growth') }}</h2><p class="card-subtitle">{{ __('app.client_growth_description') }}</p></div><span class="badge badge-neutral">{{ __('app.last_six_months') }}</span></div><div class="card-body">
        @php
            $maxGrowth = max(1, $growth->max('count'));
            $chartPoints = $growth->values()->map(fn ($month, $index) => ['x' => 50 + ($index * 620 / max(1, $growth->count() - 1)), 'y' => 194 - ($month['count'] / $maxGrowth * 144), 'label' => $month['label'], 'count' => $month['count']]);
            $linePoints = $chartPoints->map(fn ($point) => $point['x'].','.$point['y'])->implode(' ');
        @endphp
        <svg class="growth-chart" viewBox="0 0 720 250" role="img" aria-labelledby="growth-title" style="color:var(--primary)">
            <title id="growth-title">{{ __('app.client_growth') }}: {{ $growth->map(fn ($month) => $month['label'].': '.$month['count'])->implode(', ') }}</title>
            @foreach([50,98,146,194] as $y)<line x1="35" x2="690" y1="{{ $y }}" y2="{{ $y }}" stroke="var(--border)" stroke-width="1" />@endforeach
            @foreach($chartPoints as $point)<line x1="{{ $point['x'] }}" x2="{{ $point['x'] }}" y1="40" y2="194" stroke="var(--border)" stroke-width="1" opacity=".4" />@endforeach
            @if($chartPoints->isNotEmpty())<polygon points="50,194 {{ $linePoints }} 670,194" fill="currentColor" opacity=".07" /><polyline points="{{ $linePoints }}" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />@endif
            @foreach($chartPoints as $point)
                <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="5" fill="currentColor" stroke="var(--surface)" stroke-width="2" />
                <text x="{{ $point['x'] }}" y="{{ $point['y'] - 15 }}" fill="var(--text)" text-anchor="middle" style="font-size:15px;font-weight:800">{{ $point['count'] }}</text>
                <text x="{{ $point['x'] }}" y="230" fill="var(--text-secondary)" text-anchor="middle" style="font-size:14px;font-weight:700">{{ $point['label'] }}</text>
            @endforeach
        </svg>
    </div></section>
    <section class="card"><div class="card-header"><div><h2 class="card-title">{{ __('app.plan_distribution') }}</h2><p class="card-subtitle">{{ __('app.plan_distribution_description') }}</p></div><x-icon name="pie-chart" /></div><div class="card-body plan-distribution">
        <div class="plan-total"><strong>{{ number_format(array_sum($planCounts)) }}</strong><span class="muted">{{ __('app.total_clients') }}</span></div>
        @foreach(['starter','growth','enterprise'] as $plan)<div class="plan-item"><div class="plan-row"><span><span class="plan-dot plan-{{ $plan }}"></span>{{ __('app.plan_'.$plan) }}</span><strong>{{ $planCounts[$plan] ?? 0 }}</strong></div><div class="progress-track"><div class="progress-fill plan-{{ $plan }}" style="width: {{ array_sum($planCounts) > 0 ? (($planCounts[$plan] ?? 0) / array_sum($planCounts)) * 100 : 0 }}%"></div></div></div>@endforeach
    </div></section>
</div>
<section class="card recent-clients-card"><div class="card-header"><div><h2 class="card-title">{{ __('app.recent_clients') }}</h2><p class="card-subtitle">{{ __('app.recent_clients_description') }}</p></div>@if(auth()->user()->canDo('clients.view'))<a class="btn btn-ghost btn-sm" href="{{ route('admin.clients.index') }}">{{ __('app.view_all') }}<x-icon name="arrow-right" /></a>@endif</div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>{{ __('app.client') }}</th><th>{{ __('app.plan') }}</th><th>{{ __('app.status') }}</th><th>{{ __('app.created_at') }}</th><th class="actions-column"><span class="sr-only">{{ __('app.actions') }}</span></th></tr></thead><tbody>
    @forelse($recentClients as $client)<tr><td><div class="identity"><span class="avatar avatar-company">{{ mb_substr($client->name, 0, 1) }}</span><div class="identity-info"><strong>{{ $client->name }}</strong><span dir="ltr">{{ $client->email }}</span></div></div></td><td><span class="badge badge-neutral">{{ __('app.plan_'.$client->plan) }}</span></td><td><span class="badge {{ $client->status === 'active' ? 'badge-success' : 'badge-warning' }}">{{ __('app.'.$client->status) }}</span></td><td class="muted">{{ $client->created_at->translatedFormat('d M Y') }}</td><td>@if(auth()->user()->canDo('clients.view'))<a href="{{ route('admin.clients.show', $client) }}" class="icon-button" aria-label="{{ __('app.view_client', ['name'=>$client->name]) }}"><x-icon name="arrow-up-right" /></a>@endif</td></tr>
    @empty<tr><td colspan="5"><div class="empty-state compact"><div class="empty-icon"><x-icon name="building-2" /></div><h3>{{ __('app.no_clients') }}</h3><p>{{ __('app.no_clients_description') }}</p>@if(auth()->user()->canDo('clients.create'))<a href="{{ route('admin.clients.create') }}" class="btn btn-primary btn-sm">{{ __('app.add_client') }}</a>@endif</div></td></tr>@endforelse
    </tbody></table></div>
</section>
@if($recentActivity->isNotEmpty())
<section class="card"><div class="card-header"><h2 class="card-title">{{ __('app.recent_activity') }}</h2><x-icon name="activity" /></div><div class="card-body activity-list">@foreach($recentActivity as $activity)<div class="activity-item"><span class="avatar avatar-sm"><x-icon name="activity" /></span><div class="identity-info"><strong>{{ __('app.'.str_replace('.', '_', $activity->action)) }}</strong><span>{{ $activity->description }}</span></div><time class="muted" datetime="{{ $activity->created_at->toIso8601String() }}">{{ $activity->created_at->diffForHumans() }}</time></div>@endforeach</div></section>
@endif
@else
<section class="card empty-dashboard"><div class="empty-state"><div class="empty-icon"><x-icon name="layout-dashboard" /></div><h2>{{ __('app.welcome_name', ['name'=>auth()->user()->name]) }}</h2><p>{{ __('app.platform_staff_description') }}</p><a href="{{ route('profile.edit') }}" class="btn btn-primary">{{ __('app.edit_profile') }}</a></div></section>
@endif
@endsection
