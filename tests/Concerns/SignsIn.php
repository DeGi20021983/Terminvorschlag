<?php

namespace Tests\Concerns;

use App\Directory\DirectoryService;
use App\Models\User;
use Tests\Support\FakeDirectoryService;

/**
 * Meldet ein Testkonto über REMOTE_USER an, wie IIS es täte. Das AD ist ein Fake.
 */
trait SignsIn
{
    protected FakeDirectoryService $directory;

    protected function setUpSignsIn(): void
    {
        $this->directory = new FakeDirectoryService;
        $this->app->instance(DirectoryService::class, $this->directory);
    }

    protected function signInAs(User $user): static
    {
        $this->directory->add(
            $user->username,
            $user->display_name ?? $user->username,
            $user->department,
            $user->email,
            (bool) $user->is_organizer,
            (bool) $user->is_admin,
        );

        $this->withServerVariables(['REMOTE_USER' => 'CORP\\'.$user->username]);

        return $this;
    }
}
