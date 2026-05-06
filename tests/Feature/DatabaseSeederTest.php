<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_database_seeder_does_not_create_boxes(): void
    {
        $this->seed();

        $this->assertSame(0, Box::query()->count());
        $this->assertGreaterThan(0, User::query()->count());
        $this->assertGreaterThan(0, PaymentMethod::query()->count());
    }
}
