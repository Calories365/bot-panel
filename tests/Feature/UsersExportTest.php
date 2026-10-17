<?php

namespace Tests\Feature;

use App\Models\BotUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The export holds the usernames of every bot user. It used to be written into
 * storage/app/public — served as is through the public/storage link — and handed
 * out by /download/{filename} without a login.
 */
class UsersExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        BotUser::factory()->create(['username' => 'first_user']);
    }

    public function test_the_export_is_not_written_to_the_public_disk(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/users/export')->assertOk();

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertStringContainsString('first_user', Storage::disk('local')->get('exports/usernames.txt'));
    }

    public function test_a_new_export_removes_copies_left_on_the_public_disk(): void
    {
        Storage::disk('public')->put('exports/usernames.txt', 'old');
        Storage::disk('public')->put('public/exports/usernames.txt', 'old');
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/users/export')->assertOk();

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_guest_cannot_download_the_export(): void
    {
        Storage::disk('local')->put('exports/usernames.txt', 'first_user');

        $this->get('/download/usernames.txt')->assertRedirect('/login');
    }

    public function test_a_signed_in_admin_downloads_the_export(): void
    {
        Storage::disk('local')->put('exports/usernames.txt', 'first_user');

        $this->actingAs(User::factory()->create())
            ->get('/download/usernames.txt')
            ->assertOk()
            ->assertDownload('usernames.txt');
    }
}
