<?php

namespace App\Http\Controllers;

use App\Models\WorkspaceSetting;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        abort_unless($request->user()->canDo('settings.manage'), 403);

        return view('settings.general', ['settings' => WorkspaceSetting::forUser($request->user())]);
    }

    public function update(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor->canDo('settings.manage'), 403);

        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:150'],
            'business_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'country' => ['nullable', 'string', 'max:120'],
            'tax_number' => ['nullable', 'string', 'max:120'],
            'registration_number' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'extensions:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096'],
            'remove_logo' => ['sometimes', 'boolean'],
        ]);
        $scope = WorkspaceSetting::scopeFor($actor);
        $removeLogo = $request->boolean('remove_logo');
        $newLogo = null;
        unset($data['logo'], $data['remove_logo']);

        if ($request->hasFile('logo')) {
            // Use the detected image type, never the submitted filename or MIME header.
            $image = @getimagesize($request->file('logo')->getRealPath());
            $extension = match ($image[2] ?? null) {
                IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp', default => null,
            };
            if (! $extension) {
                throw ValidationException::withMessages(['logo' => __('validation.image', ['attribute' => __('app.logo')])]);
            }
            $newLogo = $request->file('logo')->storeAs('workspace-logos/'.$scope, Str::random(40).'.'.$extension, 'local');
            if (! $newLogo) {
                throw ValidationException::withMessages(['logo' => __('app.logo_upload_failed')]);
            }
        }

        try {
            [$settings, $oldLogo] = DB::transaction(function () use ($actor, $scope, $data, $newLogo, $removeLogo): array {
                $initial = WorkspaceSetting::forUser($actor);
                if (! $initial->exists) {
                    // The unique scope handles simultaneous first saves; identifiers are actor-derived.
                    WorkspaceSetting::unguarded(fn () => WorkspaceSetting::query()->firstOrCreate(
                        ['scope' => $scope], $initial->getAttributes()
                    ));
                }
                $settings = WorkspaceSetting::query()->where('scope', $scope)->lockForUpdate()->firstOrFail();
                $oldLogo = $settings->logo_path;
                $settings->fill($data);
                if ($newLogo !== null || $removeLogo) {
                    // If both were submitted, the newly uploaded logo is the final choice.
                    $settings->logo_path = $newLogo;
                }
                $settings->save();
                ActivityLogger::record($actor, 'settings.updated', $settings->business_name, route('settings.general', absolute: false));

                return [$settings, $oldLogo];
            }, 3);
        } catch (Throwable $error) {
            if ($newLogo) {
                Storage::disk('local')->delete($newLogo);
            }
            throw $error;
        }

        if ($oldLogo && $oldLogo !== $settings->logo_path) {
            DB::afterCommit(fn () => $settings->deleteLogoFile($oldLogo));
        }

        return redirect()->route('settings.general')->with('success', __('app.saved'));
    }

    public function logo(Request $request): StreamedResponse
    {
        $actor = $request->user();
        abort_unless($actor->status === 'active' && ($actor->isPlatform() || $actor->tenant?->status === 'active'), 403);
        $settings = WorkspaceSetting::forUser($actor);
        $disk = Storage::disk('local');
        abort_unless($settings->ownsLogoPath($settings->logo_path) && $disk->exists($settings->logo_path), 404);

        $mime = $disk->mimeType($settings->logo_path);
        abort_unless(in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true), 404);

        return $disk->response($settings->logo_path, 'logo.'.pathinfo($settings->logo_path, PATHINFO_EXTENSION), [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }
}
