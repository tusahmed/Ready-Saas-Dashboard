@extends('layouts.app')
@section('title', __('app.user_management'))
@section('content')
<div class="page-heading"><div><h1 class="page-title">{{ __('app.user_management') }}</h1><p class="page-description">{{ __('app.users_description') }}</p></div>@if(auth()->user()->canDo('users.create'))<a class="btn btn-primary" href="{{ route('users.create') }}"><x-icon name="plus" />{{ __('app.add_user') }}</a>@endif</div>
@include('partials.management-tabs')
<section class="card">
    <div class="card-header"><div class="inline-heading"><h2 class="card-title">{{ __('app.all_users') }}</h2><span class="badge badge-primary">{{ $users->total() }}</span></div><span class="muted">{{ __('app.your_team') }}</span></div>
    <form class="table-toolbar filters" method="GET" action="{{ route('users.index') }}">
        <div class="search-field"><x-icon name="search" /><input class="form-input" type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('app.search_users') }}" aria-label="{{ __('app.search_users') }}"></div>
        <select class="form-select" name="role" aria-label="{{ __('app.role') }}"><option value="">{{ __('app.all_roles') }}</option>@foreach($roles as $role)<option value="{{ $role->id }}" @selected((string)request('role') === (string)$role->id)>{{ $role->name }}</option>@endforeach</select>
        <select class="form-select" name="status" aria-label="{{ __('app.status') }}"><option value="">{{ __('app.all_statuses') }}</option><option value="active" @selected(request('status') === 'active')>{{ __('app.active') }}</option><option value="inactive" @selected(request('status') === 'inactive')>{{ __('app.inactive') }}</option></select>
        <button class="btn btn-secondary" type="submit"><x-icon name="filter" />{{ __('app.filter') }}</button>@if(request()->filled('q') || request()->filled('role') || request()->filled('status'))<a class="btn btn-ghost" href="{{ route('users.index') }}">{{ __('app.clear_filters') }}</a>@endif
    </form>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>{{ __('app.user') }}</th><th>{{ __('app.role') }}</th><th>{{ __('app.status') }}</th><th>{{ __('app.last_login') }}</th><th>{{ __('app.actions') }}</th></tr></thead><tbody>
    @forelse($users as $user)
        @php($canManageUser = $user->id === auth()->id() || auth()->user()->is_super_admin || (!$user->is_super_admin && !$user->role?->is_owner && collect($user->role?->permissions ?? [])->every(fn($permission) => auth()->user()->canDo($permission))))
        <tr><td><div class="identity"><span class="avatar">{{ $user->initials() }}</span><div class="identity-info"><strong>{{ $user->name }} @if($user->id === auth()->id())<span class="you-label">{{ __('app.you') }}</span>@endif</strong><span dir="ltr">{{ $user->email }}</span></div></div></td><td><span class="badge {{ $user->is_super_admin ? 'badge-primary' : 'badge-neutral' }}">{{ $user->is_super_admin ? __('app.super_admin') : ($user->role?->name ?? __('app.no_role')) }}</span></td><td><span class="badge {{ $user->status === 'active' ? 'badge-success' : 'badge-neutral' }}"><span class="status-dot"></span>{{ __('app.'.$user->status) }}</span></td><td class="muted">{{ $user->last_login_at?->diffForHumans() ?? __('app.never_logged_in') }}</td><td><div class="row-actions">@if($canManageUser && auth()->user()->canDo('users.update'))<a class="icon-button" href="{{ route('users.edit', $user) }}" aria-label="{{ __('app.edit_user', ['name'=>$user->name]) }}"><x-icon name="pencil" /></a>@endif @if($canManageUser && auth()->user()->canDo('users.delete') && $user->id !== auth()->id() && !$user->is_super_admin && !$user->role?->is_owner)<form action="{{ route('users.destroy', $user) }}" method="POST" data-confirm="{{ __('app.delete_user_confirm', ['name'=>$user->name]) }}">@csrf @method('DELETE')<button class="icon-button text-danger" type="submit" aria-label="{{ __('app.delete_user', ['name'=>$user->name]) }}"><x-icon name="trash-2" /></button></form>@endif</div></td></tr>
    @empty<tr><td colspan="5"><div class="empty-state compact"><div class="empty-icon"><x-icon name="users" /></div><h3>{{ __('app.no_users') }}</h3><p>{{ __('app.no_users_description') }}</p></div></td></tr>@endforelse
    </tbody></table></div>
    @include('partials.pagination', ['paginator' => $users])
</section>
@endsection
