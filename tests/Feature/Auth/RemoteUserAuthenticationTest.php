<?php

namespace Tests\Feature\Auth;

use App\Directory\DirectoryService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Support\FakeDirectoryService;
use Tests\TestCase;

class RemoteUserAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private FakeDirectoryService $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = new FakeDirectoryService;
        $this->app->instance(DirectoryService::class, $this->directory);
    }

    public function test_request_without_remote_user_is_rejected(): void
    {
        $this->get('/')->assertStatus(401);
    }

    public function test_dev_user_is_ignored_outside_local_environment(): void
    {
        config(['terminvorschlag.dev_user' => 'anna.schmidt']);
        $this->directory->add('anna.schmidt');

        $this->get('/')->assertStatus(401);
    }

    public function test_known_windows_account_is_signed_in_and_mirrored_locally(): void
    {
        $this->directory->add('anna.schmidt', 'Anna Schmidt', 'Bereich Nord', 'anna.schmidt@example.de');

        $this->withServerVariables(['REMOTE_USER' => 'CORP\\Anna.Schmidt'])
            ->get('/')
            ->assertOk()
            ->assertSee('Anna Schmidt');

        $this->assertDatabaseHas('users', [
            'username' => 'anna.schmidt',
            'display_name' => 'Anna Schmidt',
            'department' => 'Bereich Nord',
            'email' => 'anna.schmidt@example.de',
        ]);
    }

    public function test_user_principal_name_format_is_accepted(): void
    {
        $this->directory->add('anna.schmidt');

        $this->withServerVariables(['REMOTE_USER' => 'anna.schmidt@corp.example.de'])
            ->get('/')
            ->assertOk();

        $this->assertDatabaseHas('users', ['username' => 'anna.schmidt']);
    }

    public function test_same_person_with_different_spelling_is_one_account(): void
    {
        $this->directory->add('anna.schmidt');

        $this->withServerVariables(['REMOTE_USER' => 'CORP\\ANNA.SCHMIDT'])->get('/')->assertOk();
        $this->withServerVariables(['REMOTE_USER' => 'anna.schmidt'])->get('/')->assertOk();

        $this->assertSame(1, User::query()->count());
    }

    public function test_account_missing_in_directory_is_forbidden(): void
    {
        $this->withServerVariables(['REMOTE_USER' => 'CORP\\unbekannt'])
            ->get('/')
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['username' => 'unbekannt']);
    }

    public function test_invalid_username_characters_are_forbidden(): void
    {
        $this->directory->add('anna');

        $this->withServerVariables(['REMOTE_USER' => 'CORP\\anna;drop'])
            ->get('/')
            ->assertForbidden();
    }

    public function test_directory_outage_returns_service_unavailable(): void
    {
        $this->directory->add('anna.schmidt')->setUnavailable();

        $this->withServerVariables(['REMOTE_USER' => 'CORP\\anna.schmidt'])
            ->get('/')
            ->assertStatus(503);
    }

    public function test_organizer_group_grants_organizer_gate_only(): void
    {
        $this->directory->add('olaf.organizer', isOrganizer: true);

        $this->withServerVariables(['REMOTE_USER' => 'CORP\\olaf.organizer'])->get('/')->assertOk();

        $user = User::query()->where('username', 'olaf.organizer')->firstOrFail();

        $this->assertTrue(Gate::forUser($user)->allows('organizer'));
        $this->assertFalse(Gate::forUser($user)->allows('admin'));
    }

    public function test_admin_group_grants_admin_gate_only(): void
    {
        $this->directory->add('ada.admin', isAdmin: true);

        $this->withServerVariables(['REMOTE_USER' => 'CORP\\ada.admin'])->get('/')->assertOk();

        $user = User::query()->where('username', 'ada.admin')->firstOrFail();

        $this->assertTrue(Gate::forUser($user)->allows('admin'));
        $this->assertFalse(Gate::forUser($user)->allows('organizer'));
    }

    public function test_plain_employee_has_no_special_roles(): void
    {
        $this->directory->add('eva.mitarbeiterin');

        $this->withServerVariables(['REMOTE_USER' => 'CORP\\eva.mitarbeiterin'])->get('/')->assertOk();

        $user = User::query()->where('username', 'eva.mitarbeiterin')->firstOrFail();

        $this->assertFalse(Gate::forUser($user)->allows('organizer'));
        $this->assertFalse(Gate::forUser($user)->allows('admin'));
    }

    public function test_removed_group_membership_takes_effect_on_next_sign_in(): void
    {
        $this->directory->add('olaf.organizer', isOrganizer: true);
        $this->withServerVariables(['REMOTE_USER' => 'CORP\\olaf.organizer'])->get('/')->assertOk();

        $this->directory->add('olaf.organizer', isOrganizer: false);
        $this->withServerVariables(['REMOTE_USER' => 'CORP\\olaf.organizer'])->get('/')->assertOk();

        $this->assertFalse(User::query()->where('username', 'olaf.organizer')->firstOrFail()->is_organizer);
    }
}
