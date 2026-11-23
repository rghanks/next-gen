<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Set to true in a test to prevent automatic user authentication.
     */
    protected bool $runAsGuest = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->runAsGuest) {
            $user = User::first();
            if ($user) {
                $this->actingAs($user);
            }
        }
    }
}
