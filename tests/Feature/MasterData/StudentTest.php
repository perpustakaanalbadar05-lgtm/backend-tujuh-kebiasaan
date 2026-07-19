<?php

namespace Tests\Feature\MasterData;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;

class StudentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_students_list()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->getJson('/api/master/students');

        $response->assertStatus(200);
    }
}
