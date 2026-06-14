<?php

namespace Tests\Feature;

use Illuminate\Support\ServiceProvider;
use Tests\TestCase;

class PackageBootsTest extends TestCase
{
    public function test_config_is_published_tag_registered(): void
    {
        $this->assertContains('agents-config', array_keys(
            ServiceProvider::$publishGroups
        ));
    }

    public function test_users_table_migrates(): void
    {
        $user = $this->createUser();

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}
