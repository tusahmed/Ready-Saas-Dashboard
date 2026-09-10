@extends('layouts.app')
@section('title', $role->exists ? __('app.edit_role') : __('app.add_role'))
@section('content')
<div class="page-heading"><div><a class="back-link" href="{{ route('roles.index') }}"><x-icon name="arrow-left" />{{ __('app.back_to_roles') }}</a><h1 class="page-title">{{ $role->exists ? __('app.edit_role') : __('app.add_role') }}</h1><p class="page-description">{{ __('app.role_form_description') }}</p></div></div>
@include('partials.management-tabs')
<form action="{{ $role->exists ? route('roles.update', $role) : route('roles.store') }}" method="POST" class="stack">
    @csrf @if($role->exists) @method('PUT') @endif
    <section class="card"><div class="card-header"><h2 class="card-title">{{ __('app.role_details') }}</h2><x-icon name="shield" /></div><div class="card-body form-grid"><div class="form-group"><label class="form-label" for="name">{{ __('app.role_name') }} <span class="required">*</span></label><input class="form-input" id="name" name="name" value="{{ old('name', $role->name) }}" required maxlength="100" placeholder="{{ __('app.role_name_placeholder') }}"></div><div class="form-group"><label class="form-label" for="description">{{ __('app.description') }}</label><input class="form-input" id="description" name="description" value="{{ old('description', $role->description) }}" maxlength="1000" placeholder="{{ __('app.role_description_placeholder') }}"></div></div></section>
    <section class="card"><div class="card-header"><div><h2 class="card-title">{{ __('app.permissions') }}</h2><p class="card-subtitle">{{ __('app.permissions_description') }}</p></div><label class="checkbox-row"><input type="checkbox" data-select-all="permissions[]"><span>{{ __('app.select_all') }}</span></label></div><div class="card-body permission-grid">
        @php($selectedPermissions = session()->hasOldInput() ? old('permissions', []) : ($role->permissions ?? []))
        @foreach(collect($permissions)->groupBy(fn($label, $key) => explode('.', $key)[0], true) as $group => $groupPermissions)
            <fieldset class="permission-group"><legend><x-icon :name="match($group) { 'users' => 'users', 'roles' => 'shield', 'clients' => 'building-2', 'settings' => 'settings', default => 'bell' }" />{{ __('app.permission_group_'.$group) }}</legend>
                @foreach($groupPermissions as $permission => $label)<label class="checkbox-row"><input type="checkbox" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission, $selectedPermissions))><span>{{ __($label) }}</span></label>@endforeach
            </fieldset>
        @endforeach
    </div></section>
    <div class="form-footer"><a class="btn btn-secondary" href="{{ route('roles.index') }}">{{ __('app.cancel') }}</a><button class="btn btn-primary" type="submit"><x-icon name="check" />{{ $role->exists ? __('app.save_changes') : __('app.create_role') }}</button></div>
</form>
@endsection
