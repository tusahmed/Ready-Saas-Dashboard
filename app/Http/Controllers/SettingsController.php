<?php

namespace App\Http\Controllers;

use App\Models\WorkspaceSetting;
use App\Services\ActivityLogger;
use App\Services\SafeLogoUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

    public function update(Request $request, SafeLogoUpload $uploads): RedirectResponse
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
            $newLogo = $uploads->store($request->file('logo'), $scope);
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

    public function logo(Request $request, SafeLogoUpload $uploads): StreamedResponse
    {
        $actor = $request->user();
        abort_unless($actor->status === 'active' && ($actor->isPlatform() || $actor->tenant?->status === 'active'), 403);
        $settings = WorkspaceSetting::forUser($actor);
        $disk = Storage::disk('local');
        abort_unless($settings->ownsLogoPath($settings->logo_path) && $disk->exists($settings->logo_path), 404);

        $mime = $disk->mimeType($settings->logo_path);
        abort_unless(in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true), 404);

        $headers = [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Cache-Control' => 'private, no-store',
        ];
        $extension = pathinfo($settings->logo_path, PATHINFO_EXTENSION);
        if (! str_contains($settings->logo_path, '/sanitized/')) {
            // Older installations stored original image bytes. Never expose those
            // bytes, including legacy metadata or appended scripts, after upgrading.
            abort_if($disk->size($settings->logo_path) > 2 * 1024 * 1024, 404);
            $original = $disk->get($settings->logo_path);
            abort_unless(is_string($original), 404);
            try {
                $clean = $uploads->sanitize($original, $extension);
            } catch (ValidationException) {
                abort(404);
            }

            return response()->stream(fn () => print ($clean), 200, $headers + [
                'Content-Disposition' => 'inline; filename="logo.'.$extension.'"',
            ]);
        }

        return $disk->response($settings->logo_path, 'logo.'.$extension, $headers, 'inline');
    }
}
