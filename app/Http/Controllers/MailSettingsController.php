<?php

namespace App\Http\Controllers;

use App\Models\MailSetting;
use App\Services\PlatformMailConfigurator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MailSettingsController extends Controller
{
    public function edit(Request $request)
    {
        $this->authorizeRoot($request);

        return view('settings.smtp', [
            'settings' => MailSetting::query()->firstOrNew(['id' => 1]),
            'encryptionOptions' => $this->encryptionOptions(),
        ]);
    }

    public function update(Request $request, PlatformMailConfigurator $configurator)
    {
        $this->authorizeRoot($request);
        $requiredWhenEnabled = Rule::requiredIf(fn () => $request->boolean('enabled'));
        $data = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'host' => [$requiredWhenEnabled, 'nullable', 'string', 'max:255', 'regex:/\A(?:[a-zA-Z0-9](?:[a-zA-Z0-9.-]*[a-zA-Z0-9])?|\[[0-9a-fA-F:]+\])\z/'],
            'port' => [$requiredWhenEnabled, 'nullable', 'integer', 'between:1,65535'],
            'encryption' => [$requiredWhenEnabled, 'nullable', Rule::in($this->encryptionOptions())],
            'username' => ['nullable', 'string', 'max:255', 'not_regex:/[\x00\r\n]/'],
            'smtp_password' => ['nullable', 'string', 'max:2048', 'not_regex:/\x00/', Rule::prohibitedIf(fn () => $request->boolean('clear_password'))],
            'clear_password' => ['sometimes', 'boolean'],
            'from_address' => [$requiredWhenEnabled, 'nullable', 'email', 'max:255'],
            'from_name' => [$requiredWhenEnabled, 'nullable', 'string', 'max:120', 'not_regex:/[\x00\r\n]/'],
        ]);

        $settings = MailSetting::query()->firstOrNew(['id' => 1]);
        $data['enabled'] = $request->boolean('enabled');
        $data['port'] = $data['port'] ?? $settings->port;
        $data['encryption'] = $data['encryption'] ?? $settings->encryption;
        if ($request->boolean('clear_password')) {
            $data['password'] = null;
        } elseif (isset($data['smtp_password']) && $data['smtp_password'] !== '') {
            $data['password'] = $data['smtp_password'];
        }
        unset($data['smtp_password'], $data['clear_password']);

        MailSetting::query()->updateOrCreate(['id' => 1], $data);
        $configurator->apply();

        return redirect()->route('admin.settings.smtp')->with('success', __('app.smtp_saved'));
    }

    private function authorizeRoot(Request $request): void
    {
        abort_unless($request->user()?->is_super_admin && $request->user()->isPlatform(), 403);
    }

    private function encryptionOptions(): array
    {
        return app()->environment(['local', 'testing']) ? ['tls', 'ssl', 'none'] : ['tls', 'ssl'];
    }
}
