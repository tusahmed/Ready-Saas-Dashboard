@props(['id', 'name' => null, 'required' => false, 'autocomplete' => 'new-password', 'minlength' => null])
<div class="password-field">
    <input {{ $attributes->class(['form-input']) }} id="{{ $id }}" name="{{ $name ?? $id }}" type="password" autocomplete="{{ $autocomplete }}" @required($required) @if($minlength) minlength="{{ $minlength }}" @endif>
    <button type="button" class="password-toggle" data-password-toggle="{{ $id }}" data-label-show="{{ __('app.show_password') }}" data-label-hide="{{ __('app.hide_password') }}" aria-label="{{ __('app.show_password') }}" aria-controls="{{ $id }}" aria-pressed="false" title="{{ __('app.show_password') }}">
        <span data-password-show-icon><x-icon name="eye" /></span><span data-password-hide-icon hidden><x-icon name="eye-off" /></span>
    </button>
</div>
