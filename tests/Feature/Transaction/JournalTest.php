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
        $school = \App\Models\School::create([
            'name' => 'Sekolah Test',
            'npsn' => '12345678',
            'status' => 'active',
        ]);
        $user = User::factory()->create(['role' => 'siswa']);
        \App\Models\Student::create([
            'user_id' => $user->id,
            'school_id' => $school->id,
            'name' => $user->name,
            'nis' => '12345',
            'nisn' => '1234567890',
            'gender' => 'L'
        ]);

        $response = $this->actingAs($user)->getJson('/api/journals');

        $response->assertStatus(200);
    }
}
