<form class="card appearance-card" id="appearance-form" action="{{ route('preferences.update') }}" method="POST">
    @csrf @method('PATCH')
    <div class="card-header"><div><h2 class="card-title">{{ __('app.workspace_appearance') }}</h2><p class="card-subtitle">{{ __('app.workspace_appearance_description') }}</p></div><span class="section-icon"><x-icon name="dashboard" /></span></div>
    <div class="card-body stack">
        <fieldset class="appearance-fieldset"><legend class="form-label">{{ __('app.navigation_layout') }}</legend>
            <div class="appearance-options">
                @foreach(['vertical','horizontal'] as $layout)
                    <label class="layout-option">
                        <input type="radio" name="layout" value="{{ $layout }}" @checked(old('layout', $user->layout ?? 'vertical') === $layout)>
                        <span class="layout-preview preview-{{ $layout }}" aria-hidden="true"><span class="preview-bar"></span><span class="preview-nav"><i></i><i></i><i></i></span><span class="preview-content"><i></i><i></i><i></i><i></i></span></span>
                        <span class="layout-option-copy"><strong>{{ __('app.layout_'.$layout) }}</strong><span>{{ __('app.layout_'.$layout.'_description') }}</span></span>
                    </label>
                @endforeach
            </div>
        </fieldset>
        <div class="appearance-bottom">
            <fieldset class="appearance-fieldset"><legend class="form-label">{{ __('app.appearance') }}</legend><div class="theme-options">
                @foreach(['light','dark'] as $theme)<label class="theme-option"><input type="radio" name="theme" value="{{ $theme }}" @checked(old('theme', $user->theme) === $theme)><span class="theme-swatch swatch-{{ $theme }}"><x-icon :name="$theme === 'light' ? 'sun' : 'moon'" /></span><strong>{{ __('app.'.$theme.'_mode') }}</strong></label>@endforeach
            </div></fieldset>
            <div class="form-group"><label class="form-label" for="profile-locale">{{ __('app.language') }}</label><select class="form-select" id="profile-locale" name="locale">@foreach(config('saas.locales') as $code => $label)<option value="{{ $code }}" @selected(old('locale', $user->locale) === $code)>{{ $label }}</option>@endforeach</select></div>
        </div>
    </div>
    <div class="card-footer"><p class="form-help"><x-icon name="user" />{{ __('app.personal_preferences_hint') }}</p><button class="btn btn-primary" type="submit"><x-icon name="check" />{{ __('app.save_preferences') }}</button></div>
</form>
