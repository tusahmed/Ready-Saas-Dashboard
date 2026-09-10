@extends('layouts.app')
@section('title', __('app.dashboard'))
@section('content')
<div class="page-heading"><div><h1 class="page-title">{{ __('app.dashboard') }}</h1><p class="page-description">{{ __('app.welcome_name', ['name' => auth()->user()->name]) }}</p></div><span class="badge badge-success"><span class="status-dot"></span>{{ __('app.workspace_ready') }}</span></div>
<section class="card empty-dashboard">
    <div class="empty-state">
        <div class="empty-dashboard-art" aria-hidden="true"><span class="empty-art-orbit"></span><div class="empty-icon"><x-icon name="layout-dashboard" /></div><span class="empty-art-spark spark-one"></span><span class="empty-art-spark spark-two"></span></div>
        
        <h2>{{ __('app.dashboard_empty_title') }}</h2>
        <p>{{ __('app.dashboard_empty_description') }}</p>
        <div class="page-actions">
            @if(auth()->user()->canDo('users.view'))<a href="{{ route('users.index') }}" class="btn btn-primary"><x-icon name="users" />{{ __('app.manage_team') }}</a>@endif
            <a href="{{ route('profile.edit') }}" class="btn btn-secondary">{{ __('app.edit_profile') }}<x-icon name="arrow-right" /></a>
        </div>
    </div>
</section>
@endsection
