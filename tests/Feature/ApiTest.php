<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    public string $prefix = '/api/v1';

    public function test_register()
    {
        $response = $this->postJson("{$this->prefix}/register", [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password'
        ]);

        $response->assertStatus(201)->assertJsonStructure(['token']);
    }

    public function test_login()
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->postJson("{$this->prefix}/login", [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200)->assertJsonStructure(['token']);
    }

}
