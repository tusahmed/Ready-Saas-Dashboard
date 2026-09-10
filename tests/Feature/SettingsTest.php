<?php

namespace Tests\Feature;

use App\Models\WorkspaceSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    public function test_settings_read_tenant_defaults_without_creating_records(): void
    {
        $tenant = $this->tenant([
            'name' => 'Existing business', 'email' => 'existing@example.test', 'phone' => '01001234567',
            'website' => 'https://example.test', 'description' => 'Existing description',
        ]);
        $owner = $this->owner($tenant);

        $this->actingAs($owner)->get(route('settings.general'))->assertOk()->assertViewHas('settings', function ($settings) use ($tenant) {
            return ! $settings->exists && $settings->scope === 'tenant-'.$tenant->id
                && $settings->tenant_id === $tenant->id && $settings->business_name === $tenant->name
                && $settings->business_email === $tenant->email && $settings->phone === $tenant->phone
                && $settings->website === $tenant->website && $settings->description === $tenant->description;
        });
        $this->actingAs($this->rootUser())->get(route('settings.general'))->assertOk()->assertViewHas('settings', function ($settings) {
            return ! $settings->exists && $settings->scope === 'platform' && $settings->tenant_id === null
                && $settings->business_name === 'Orbit' && $settings->business_email === null;
        });
        $this->assertDatabaseCount('workspace_settings', 0);
    }

    public function test_only_the_actors_workspace_is_updated_and_tenant_records_are_unchanged(): void
    {
        $tenant = $this->tenant(['name' => 'Original tenant name']);
        $owner = $this->owner($tenant);
        $otherTenant = $this->tenant();
        $otherOwner = $this->owner($otherTenant);
        $other = WorkspaceSetting::forUser($otherOwner);
        $other->business_name = 'Other workspace';
        $other->save();

        $this->actingAs($owner)->patch(route('settings.general.update'), [
            'business_name' => 'My registered business', 'business_email' => 'business@example.test',
            'phone' => '01001234567', 'website' => 'https://business.example.test', 'country' => 'Egypt',
            'address' => 'Cairo', 'tax_number' => 'TAX-123', 'registration_number' => 'REG-456',
            'description' => 'Business description', 'tenant_id' => $otherTenant->id, 'scope' => 'platform',
            'id' => $other->id, 'logo_path' => 'outside/forged.png',
        ])->assertRedirect(route('settings.general'))->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertDatabaseHas('workspace_settings', [
            'scope' => 'tenant-'.$tenant->id, 'tenant_id' => $tenant->id, 'business_name' => 'My registered business',
            'business_email' => 'business@example.test', 'phone' => '01001234567', 'website' => 'https://business.example.test',
            'country' => 'Egypt', 'address' => 'Cairo', 'tax_number' => 'TAX-123', 'registration_number' => 'REG-456',
            'description' => 'Business description', 'logo_path' => null,
        ]);
        $this->assertSame('Other workspace', $other->fresh()->business_name);
        $this->assertSame('Original tenant name', $tenant->fresh()->name);
        $this->assertDatabaseMissing('workspace_settings', ['scope' => 'platform']);
        $this->assertDatabaseCount('workspace_settings', 2);
    }

    public function test_platform_settings_stay_separate_from_tenant_settings(): void
    {
        $tenant = $this->tenant();
        $owner = $this->owner($tenant);
        $this->actingAs($owner)->patch(route('settings.general.update'), ['business_name' => 'Tenant brand'])->assertSessionHasNoErrors();
        $root = $this->rootUser();
        $this->actingAs($root)->patch(route('settings.general.update'), [
            'business_name' => 'Platform brand', 'tenant_id' => $tenant->id, 'scope' => 'tenant-'.$tenant->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Platform brand', WorkspaceSetting::forUser($root)->business_name);
        $this->assertSame('Tenant brand', WorkspaceSetting::forUser($owner)->business_name);
        $this->assertDatabaseHas('workspace_settings', ['scope' => 'platform', 'tenant_id' => null]);
        $this->assertDatabaseCount('workspace_settings', 2);
    }

    public function test_settings_permission_is_required_but_can_be_assigned_to_workspace_staff(): void
    {
        $tenant = $this->tenant();
        $reader = $this->user($tenant, $this->role($tenant, ['users.view']));
        $this->actingAs($reader)->get(route('settings.general'))->assertForbidden();
        $this->patch(route('settings.general.update'), ['business_name' => 'Forbidden'])->assertForbidden();
        $editor = $this->user($tenant, $this->role($tenant, ['settings.manage']));
        $this->actingAs($editor)->get(route('settings.general'))->assertOk();
        $this->patch(route('settings.general.update'), ['business_name' => 'Edited by staff'])->assertSessionHasNoErrors();

        $this->assertSame('Edited by staff', WorkspaceSetting::forUser($reader)->business_name);
        $platformStaff = $this->user(null, $this->role(null, ['settings.manage']));
        $this->actingAs($platformStaff)->patch(route('settings.general.update'), ['business_name' => 'Platform staff brand'])->assertSessionHasNoErrors();
        $this->assertSame('Platform staff brand', WorkspaceSetting::forUser($platformStaff)->business_name);
        $this->assertSame('Edited by staff', WorkspaceSetting::forUser($reader)->business_name);
    }

    public function test_invalid_business_fields_cannot_be_saved(): void
    {
        $this->actingAs($this->owner($this->tenant()));
        $invalid = [
            'business_name' => str_repeat('n', 151), 'business_email' => 'not-an-email', 'phone' => str_repeat('1', 41),
            'website' => 'javascript:alert(1)', 'address' => str_repeat('a', 1001), 'country' => str_repeat('c', 121),
            'tax_number' => str_repeat('t', 121), 'registration_number' => str_repeat('r', 121), 'description' => str_repeat('d', 2001),
        ];
        foreach ($invalid as $field => $value) {
            $this->patchJson(route('settings.general.update'), array_replace(['business_name' => 'Valid name'], [$field => $value]))
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->patchJson(route('settings.general.update'), ['business_name' => ''])->assertUnprocessable()->assertJsonValidationErrors('business_name');
        $this->assertDatabaseCount('workspace_settings', 0);
    }

    public function test_logo_upload_is_private_and_readable_only_inside_its_own_workspace(): void
    {
        Storage::fake('local');
        $tenant = $this->tenant();
        $owner = $this->owner($tenant);
        $reader = $this->user($tenant);
        $logo = UploadedFile::fake()->image('business.png', 120, 80);
        $bytes = file_get_contents($logo->getRealPath());

        $this->actingAs($owner)->patch(route('settings.general.update'), ['business_name' => 'Brand with a logo', 'logo' => $logo])
            ->assertSessionHasNoErrors()->assertRedirect(route('settings.general'));
        $settings = WorkspaceSetting::forUser($owner);
        $this->assertMatchesRegularExpression('/\Aworkspace-logos\/tenant-'.$tenant->id.'\/[A-Za-z0-9]{40}\.png\z/', $settings->logo_path);
        Storage::disk('local')->assertExists($settings->logo_path);
        $this->assertStringStartsWith(route('settings.logo'), $settings->logo_url);
        $this->assertStringNotContainsString('storage/', $settings->logo_url);

        $response = $this->actingAs($reader)->get(route('settings.logo', ['scope' => 'platform', 'tenant_id' => 99999]))
            ->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertStreamedContent($bytes);
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $other = $this->owner($this->tenant());
        $this->actingAs($other)->get(route('settings.logo', ['scope' => $settings->scope, 'tenant_id' => $tenant->id]))->assertNotFound();
        $this->actingAs($this->rootUser())->get(route('settings.logo', ['scope' => $settings->scope]))->assertNotFound();
    }

    public function test_logo_replacement_and_removal_clean_only_the_old_owned_file(): void
    {
        Storage::fake('local');
        $owner = $this->owner($this->tenant());
        $foreignPath = 'workspace-logos/tenant-999999/'.Str::random(40).'.png';
        Storage::disk('local')->put($foreignPath, 'Another workspace file');
        $this->actingAs($owner)->patch(route('settings.general.update'), [
            'business_name' => 'Business', 'logo' => UploadedFile::fake()->image('first.png', 30, 30),
        ])->assertSessionHasNoErrors();
        $firstPath = WorkspaceSetting::forUser($owner)->logo_path;

        $this->patch(route('settings.general.update'), [
            'business_name' => 'Business', 'logo' => UploadedFile::fake()->image('second.jpg', 40, 40),
            'logo_path' => $foreignPath, 'remove_logo' => '1',
        ])->assertSessionHasNoErrors();
        $secondPath = WorkspaceSetting::forUser($owner)->logo_path;
        $this->assertNotSame($firstPath, $secondPath);
        $this->assertStringEndsWith('.jpg', $secondPath);
        Storage::disk('local')->assertMissing($firstPath);
        Storage::disk('local')->assertExists($secondPath);

        $this->patch(route('settings.general.update'), ['business_name' => 'Business', 'remove_logo' => '1'])->assertSessionHasNoErrors();
        $this->assertNull(WorkspaceSetting::forUser($owner)->logo_path);
        $this->assertNull(WorkspaceSetting::forUser($owner)->logo_url);
        Storage::disk('local')->assertMissing($secondPath);
        Storage::disk('local')->assertExists($foreignPath);
        $this->get(route('settings.logo'))->assertNotFound();
    }

    public function test_saving_business_details_without_a_logo_change_keeps_the_current_logo(): void
    {
        Storage::fake('local');
        $owner = $this->owner($this->tenant());
        $this->actingAs($owner)->patch(route('settings.general.update'), [
            'business_name' => 'Before', 'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertSessionHasNoErrors();
        $path = WorkspaceSetting::forUser($owner)->logo_path;
        $this->patch(route('settings.general.update'), ['business_name' => 'After', 'remove_logo' => '0'])->assertSessionHasNoErrors();
        $this->assertSame($path, WorkspaceSetting::forUser($owner)->logo_path);
        Storage::disk('local')->assertExists($path);
    }

    public function test_svg_fake_images_oversized_files_and_excessive_dimensions_are_rejected(): void
    {
        Storage::fake('local');
        $this->actingAs($this->owner($this->tenant()));
        $validImage = UploadedFile::fake()->image('valid.png');
        $files = [
            UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            UploadedFile::fake()->createWithContent('fake.png', '<?php echo "not an image";'),
            UploadedFile::fake()->createWithContent('hidden-svg.png', '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"></svg>'),
            UploadedFile::fake()->createWithContent('valid-image.svg', file_get_contents($validImage->getRealPath())),
            UploadedFile::fake()->image('too-large.png', 20, 20)->size(2049),
            UploadedFile::fake()->image('too-wide.png', 4097, 2),
            UploadedFile::fake()->image('too-tall.jpg', 2, 4097),
        ];
        foreach ($files as $file) {
            $this->patch(route('settings.general.update'), ['business_name' => 'Valid business', 'logo' => $file])->assertSessionHasErrors('logo');
        }
        $this->assertDatabaseCount('workspace_settings', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_webp_logos_are_accepted_with_the_detected_content_type(): void
    {
        Storage::fake('local');
        $owner = $this->owner($this->tenant());
        $this->actingAs($owner)->patch(route('settings.general.update'), [
            'business_name' => 'WebP business', 'logo' => UploadedFile::fake()->image('logo.webp', 40, 40),
        ])->assertSessionHasNoErrors();

        $path = WorkspaceSetting::forUser($owner)->logo_path;
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('local')->assertExists($path);
        $this->get(route('settings.logo'))->assertOk()->assertHeader('Content-Type', 'image/webp');
    }

    public function test_failed_database_save_removes_the_new_upload_and_preserves_the_old_logo(): void
    {
        Storage::fake('local');
        $owner = $this->owner($this->tenant());
        $this->actingAs($owner)->patch(route('settings.general.update'), [
            'business_name' => 'Existing settings', 'logo' => UploadedFile::fake()->image('existing.png'),
        ])->assertSessionHasNoErrors();
        $oldPath = WorkspaceSetting::forUser($owner)->logo_path;
        $event = 'eloquent.saving: '.WorkspaceSetting::class;
        Event::listen($event, function ($settings) {
            if ($settings->business_name === 'Fail save') {
                throw new RuntimeException('Simulated settings database failure');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->patch(route('settings.general.update'), ['business_name' => 'Fail save', 'logo' => UploadedFile::fake()->image('new.png')]);
            $this->fail('The simulated database failure was not raised.');
        } catch (RuntimeException $error) {
            $this->assertSame('Simulated settings database failure', $error->getMessage());
        } finally {
            Event::forget($event);
            $this->withExceptionHandling();
        }

        $this->assertSame('Existing settings', WorkspaceSetting::forUser($owner)->business_name);
        $this->assertSame($oldPath, WorkspaceSetting::forUser($owner)->logo_path);
        $this->assertSame([$oldPath], Storage::disk('local')->allFiles());
    }

    public function test_logo_paths_cannot_read_or_delete_files_owned_by_another_scope(): void
    {
        Storage::fake('local');
        $owner = $this->owner($this->tenant());
        $foreignPath = 'workspace-logos/platform/'.Str::random(40).'.png';
        $file = UploadedFile::fake()->image('foreign.png');
        Storage::disk('local')->put($foreignPath, file_get_contents($file->getRealPath()));
        $settings = WorkspaceSetting::forUser($owner);
        $settings->forceFill(['logo_path' => $foreignPath])->save();

        $this->actingAs($owner)->get(route('settings.logo'))->assertNotFound();
        $this->assertNull($settings->logo_url);
        $this->patch(route('settings.general.update'), ['business_name' => 'Own workspace', 'remove_logo' => '1'])->assertSessionHasNoErrors();
        Storage::disk('local')->assertExists($foreignPath);
    }

    public function test_rolling_back_settings_deletion_retains_the_logo(): void
    {
        Storage::fake('local');
        $owner = $this->owner($this->tenant());
        $this->actingAs($owner)->patch(route('settings.general.update'), [
            'business_name' => 'Business', 'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertSessionHasNoErrors();
        $settings = WorkspaceSetting::forUser($owner);
        $path = $settings->logo_path;
        DB::beginTransaction();
        try {
            $settings->delete();
        } finally {
            DB::rollBack();
        }

        $this->assertDatabaseHas('workspace_settings', ['id' => $settings->id, 'logo_path' => $path]);
        Storage::disk('local')->assertExists($path);
    }

    public function test_committed_settings_deletion_removes_its_logo(): void
    {
        Storage::fake('local');
        $owner = $this->owner($this->tenant());
        $this->actingAs($owner)->patch(route('settings.general.update'), [
            'business_name' => 'Business', 'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertSessionHasNoErrors();
        $settings = WorkspaceSetting::forUser($owner);
        $path = $settings->logo_path;

        DB::transaction(fn () => $settings->delete());

        $this->assertDatabaseMissing('workspace_settings', ['id' => $settings->id]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_deleting_a_client_removes_its_settings_and_logo_after_commit(): void
    {
        Storage::fake('local');
        $tenant = $this->tenant();
        $owner = $this->owner($tenant);
        $this->actingAs($owner)->patch(route('settings.general.update'), [
            'business_name' => 'Business', 'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertSessionHasNoErrors();
        $settings = WorkspaceSetting::forUser($owner);
        $path = $settings->logo_path;

        $this->actingAs($this->rootUser())->delete(route('admin.clients.destroy', $tenant))->assertRedirect(route('admin.clients.index'));

        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
        $this->assertDatabaseMissing('workspace_settings', ['id' => $settings->id]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_inactive_accounts_and_suspended_workspaces_cannot_read_logos_or_update_settings(): void
    {
        $tenant = $this->tenant();
        $owner = $this->owner($tenant);
        $owner->update(['status' => 'inactive']);
        $this->actingAs($owner)->getJson(route('settings.logo'))->assertForbidden();
        $this->actingAs($owner)->patchJson(route('settings.general.update'), ['business_name' => 'Denied'])->assertForbidden();

        $owner->update(['status' => 'active']);
        $tenant->update(['status' => 'suspended']);
        $this->actingAs($owner->fresh())->getJson(route('settings.logo'))->assertForbidden();
        $this->actingAs($owner->fresh())->patchJson(route('settings.general.update'), ['business_name' => 'Denied'])->assertForbidden();
        $this->assertDatabaseCount('workspace_settings', 0);
    }
}
