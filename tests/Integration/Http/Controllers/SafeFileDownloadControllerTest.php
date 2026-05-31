<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Integration\Http\Controllers;

use BlackParadise\LaravelAdmin\Support\BPAdminFileUrl;
use BlackParadise\LaravelAdmin\Tests\Integration\Concerns\BootsBPAdmin;
use BlackParadise\LaravelAdmin\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

final class SafeFileDownloadControllerTest extends TestCase
{
    use RefreshDatabase;
    use BootsBPAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBPAdmin();
    }

    public function test_authenticated_admin_can_download_file_with_attachment_disposition(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('docs/sample.pdf', 'fake-content');

        $this->actingAsAdmin()
            ->get(BPAdminFileUrl::signed('public', 'docs/sample.pdf'))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=sample.pdf');
    }

    public function test_unauthenticated_request_is_redirected_to_login(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('docs/sample.pdf', 'x');

        $this->get(BPAdminFileUrl::signed('public', 'docs/sample.pdf'))
            ->assertRedirect('/admin/auth/login');
    }

    public function test_unsigned_request_is_forbidden(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('docs/sample.pdf', 'x');

        $this->actingAsAdmin()
            ->get('/admin/files/download/public/docs/sample.pdf')
            ->assertForbidden();
    }

    public function test_tampered_signature_is_forbidden(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('docs/sample.pdf', 'x');

        $url = BPAdminFileUrl::signed('public', 'docs/sample.pdf') . 'tampered';

        $this->actingAsAdmin()
            ->get($url)
            ->assertForbidden();
    }

    public function test_path_traversal_attempt_is_rejected(): void
    {
        Storage::fake('public');

        // Generate a signed URL with a traversal path — controller must still 404
        // because traversal check fires AFTER signature verification.
        $url = URL::temporarySignedRoute(
            'bpadmin.files.download',
            now()->addMinutes(15),
            ['disk' => 'public', 'path' => '../../etc/passwd'],
        );

        $this->actingAsAdmin()
            ->get($url)
            ->assertNotFound();
    }

    public function test_unknown_disk_is_rejected(): void
    {
        $url = URL::temporarySignedRoute(
            'bpadmin.files.download',
            now()->addMinutes(15),
            ['disk' => 'nonexistent-disk', 'path' => 'foo.pdf'],
        );

        $this->actingAsAdmin()
            ->get($url)
            ->assertNotFound();
    }

    public function test_disk_not_in_allowlist_is_rejected_even_if_configured(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('secret.txt', 'sensitive');

        config(['bpadmin.allowed_download_disks' => ['public']]);

        $url = URL::temporarySignedRoute(
            'bpadmin.files.download',
            now()->addMinutes(15),
            ['disk' => 's3', 'path' => 'secret.txt'],
        );

        $this->actingAsAdmin()
            ->get($url)
            ->assertNotFound();
    }

    public function test_controller_guard_fires_before_storage_on_dot_dot_path(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('any.txt', 'content');

        $url = URL::temporarySignedRoute(
            'bpadmin.files.download',
            now()->addMinutes(15),
            ['disk' => 'public', 'path' => '../any.txt'],
        );

        $this->actingAsAdmin()
            ->get($url)
            ->assertNotFound();
    }

    public function test_url_encoded_dot_traversal_is_rejected(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('any.txt', 'content');

        $url = URL::temporarySignedRoute(
            'bpadmin.files.download',
            now()->addMinutes(15),
            ['disk' => 'public', 'path' => '%2e%2e/any.txt'],
        );

        $this->actingAsAdmin()
            ->get($url)
            ->assertNotFound();
    }
}
