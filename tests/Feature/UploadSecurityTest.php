<?php

namespace Tests\Feature;

use App\Models\WorkspaceSetting;
use App\Services\SafeLogoUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UploadSecurityTest extends TestCase
{
    public function test_executable_extensions_double_extensions_and_filename_delimiters_are_rejected(): void
    {
        Storage::fake('local');
        $this->actingAs($this->owner($this->tenant()));
        $valid = UploadedFile::fake()->image('logo.png', 24, 24);
        $bytes = file_get_contents($valid->getRealPath());

        foreach (['shell.php', 'shell.phtml', 'shell.phar', 'shell.php.png', 'shell.PHP.PNG',
            'shell.phtml.png', 'shell.png.php', 'shell.php%00.png', 'shell.php;.png', '.htaccess', 'shell..png'] as $filename) {
            $this->patchJson(route('settings.general.update'), [
                'business_name' => 'Business', 'logo' => UploadedFile::fake()->createWithContent($filename, $bytes),
            ])->assertUnprocessable()->assertJsonValidationErrors('logo');
        }

        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('workspace_settings', 0);
    }

    public function test_shell_script_html_svg_and_mime_spoofed_uploads_cannot_be_saved(): void
    {
        Storage::fake('local');
        $this->actingAs($this->owner($this->tenant()));

        foreach (['<?php echo "inert-shell-fixture"; ?>', '<html><script>alert(1)</script></html>',
            '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>', '#!/bin/sh'."\n".'echo inert-shell-fixture'] as $payload) {
            $file = UploadedFile::fake()->createWithContent('logo.png', $payload)->mimeType('image/png');
            $this->patchJson(route('settings.general.update'), ['business_name' => 'Business', 'logo' => $file])
                ->assertUnprocessable()->assertJsonValidationErrors('logo');
        }

        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('workspace_settings', 0);
    }

    public function test_a_raster_extension_must_match_the_actual_decoded_file_type(): void
    {
        Storage::fake('local');
        $this->actingAs($this->owner($this->tenant()));
        $jpeg = UploadedFile::fake()->image('image.jpg', 20, 20);
        $spoofed = UploadedFile::fake()->createWithContent('image.png', file_get_contents($jpeg->getRealPath()));

        $this->patchJson(route('settings.general.update'), ['business_name' => 'Business', 'logo' => $spoofed])
            ->assertUnprocessable()->assertJsonValidationErrors('logo');

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_png_and_jpeg_polyglots_are_rebuilt_from_pixels_and_never_store_the_original_payload(): void
    {
        Storage::fake('local');
        $owner = $this->owner($this->tenant());
        $this->actingAs($owner);
        $payload = '<?php echo "inert-polyglot-fixture"; ?>';

        foreach (['png', 'jpg', 'webp'] as $extension) {
            $image = UploadedFile::fake()->image('logo.'.$extension, 32, 24);
            $bytes = file_get_contents($image->getRealPath());
            if ($extension === 'jpg') {
                // A JPEG comment is valid metadata and survives a MIME/signature check.
                $bytes = substr($bytes, 0, 2)."\xFF\xFE".pack('n', strlen($payload) + 2).$payload.substr($bytes, 2);
            }
            $bytes .= $payload;
            $file = UploadedFile::fake()->createWithContent('logo.'.$extension, $bytes);

            $this->patch(route('settings.general.update'), ['business_name' => 'Business', 'logo' => $file])
                ->assertSessionHasNoErrors();
            $settings = WorkspaceSetting::forUser($owner);
            $clean = Storage::disk('local')->get($settings->logo_path);
            $this->assertNotSame($bytes, $clean);
            $this->assertStringNotContainsString($payload, $clean);
            $this->assertStringNotContainsString('<?php', $clean);
            // Unix chmod visibility is not portable to Windows. The security boundary
            // is the private disk plus authenticated delivery, tested below and in SettingsTest.
            $this->assertStringContainsString('/sanitized/', $settings->logo_path);
            $decoded = imagecreatefromstring($clean);
            $this->assertSame(32, imagesx($decoded));
            $this->assertSame(24, imagesy($decoded));
            imagedestroy($decoded);

            $this->get(route('settings.logo'))->assertOk()
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox")
                ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin')
                ->assertStreamedContent($clean);
        }
    }

    public function test_a_valid_png_signature_with_corrupt_image_data_is_rejected(): void
    {
        Storage::fake('local');
        $this->actingAs($this->owner($this->tenant()));
        $image = UploadedFile::fake()->image('logo.png', 16, 16);
        // Keep the PNG signature + complete IHDR so header-only validation passes.
        $header = substr(file_get_contents($image->getRealPath()), 0, 33);
        $this->assertIsArray(getimagesizefromstring($header));
        $file = UploadedFile::fake()->createWithContent('logo.png', $header);

        $this->patchJson(route('settings.general.update'), ['business_name' => 'Business', 'logo' => $file])
            ->assertUnprocessable()->assertJsonValidationErrors('logo');

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_legacy_logos_are_sanitized_before_delivery_without_exposing_original_bytes(): void
    {
        Storage::fake('local');
        $owner = $this->owner($this->tenant());
        $settings = WorkspaceSetting::forUser($owner);
        $path = 'workspace-logos/'.$settings->scope.'/'.Str::random(40).'.png';
        $image = UploadedFile::fake()->image('legacy.png', 32, 32);
        $payload = '<?php echo "inert-legacy-polyglot"; ?>';
        $original = file_get_contents($image->getRealPath()).$payload;
        Storage::disk('local')->put($path, $original);
        $settings->forceFill(['logo_path' => $path])->save();

        $response = $this->actingAs($owner)->get(route('settings.logo'))->assertOk()->assertHeader('Content-Type', 'image/png');
        $served = $response->streamedContent();
        $this->assertNotSame($original, $served);
        $this->assertStringNotContainsString($payload, $served);
        $this->assertSame([32, 32], array_slice(getimagesizefromstring($served), 0, 2));
        // The read does not mutate a logo while a concurrent settings save may be replacing it.
        $this->assertSame($path, $settings->fresh()->logo_path);
    }

    public function test_corrupt_legacy_logos_are_never_streamed_even_when_the_mime_type_looks_valid(): void
    {
        Storage::fake('local');
        $owner = $this->owner($this->tenant());
        $settings = WorkspaceSetting::forUser($owner);
        $path = 'workspace-logos/'.$settings->scope.'/'.Str::random(40).'.png';
        $image = UploadedFile::fake()->image('legacy.png', 32, 32);
        Storage::disk('local')->put($path, substr(file_get_contents($image->getRealPath()), 0, 33));
        $settings->forceFill(['logo_path' => $path])->save();

        $this->actingAs($owner)->get(route('settings.logo'))->assertNotFound();
    }

    public function test_compressed_large_pixel_images_are_rejected_before_decoding(): void
    {
        Storage::fake('local');
        $this->actingAs($this->owner($this->tenant()));
        $image = UploadedFile::fake()->image('large.png', 2050, 2050);
        $this->assertLessThan(2 * 1024 * 1024, filesize($image->getRealPath()));

        $this->patchJson(route('settings.general.update'), ['business_name' => 'Business', 'logo' => $image])
            ->assertUnprocessable()->assertJsonValidationErrors('logo');

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_upload_service_rejects_path_traversal_even_if_a_caller_supplies_an_unsafe_scope(): void
    {
        Storage::fake('local');
        try {
            app(SafeLogoUpload::class)->store(UploadedFile::fake()->image('logo.png'), '../../public');
            $this->fail('An unsafe scope was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('logo', $exception->errors());
        }

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_logo_reencoding_preserves_png_transparency(): void
    {
        Storage::fake('local');
        $source = imagecreatetruecolor(4, 4);
        imagealphablending($source, false);
        imagesavealpha($source, true);
        imagefill($source, 0, 0, imagecolorallocatealpha($source, 12, 34, 56, 127));
        ob_start();
        imagepng($source);
        $bytes = ob_get_clean();
        imagedestroy($source);
        $path = app(SafeLogoUpload::class)->store(UploadedFile::fake()->createWithContent('logo.png', $bytes), 'platform');
        $saved = imagecreatefromstring(Storage::disk('local')->get($path));
        $pixel = imagecolorsforindex($saved, imagecolorat($saved, 0, 0));
        imagedestroy($saved);

        $this->assertSame(127, $pixel['alpha']);
    }
}
