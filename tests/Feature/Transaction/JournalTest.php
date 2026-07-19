<?php

namespace Tests\Feature\Transaction;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;

class JournalTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_journals_endpoint()
    {
        $user = User::factory()->create(['role' => 'siswa']);

        $response = $this->actingAs($user)->getJson('/api/journals');

        $response->assertStatus(200);
    }
}
