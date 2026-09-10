@if ($errors->any())
    <div class="alert alert-danger" role="alert"><x-icon name="alert-circle" /><div><strong>{{ __('app.error_heading') }}</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
@endif
@if (session('status'))
    <div class="alert alert-success" role="status"><x-icon name="check-circle" /><span>{{ session('status') }}</span></div>
@endif
@if (session('success'))
    <div class="alert alert-success flash-message" role="status" data-flash-message="{{ session('success') }}"><x-icon name="check-circle" /><span>{{ session('success') }}</span></div>
@endif
