<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    private string $prefix = '/api/v1';

    private User $user;

    private string $token = '';

    private function getToken()
    {
        if ($this->token) {
            return $this->token;
        }

        $user = User::factory()->create();
        $this->token = $user->createToken('test')->plainTextToken;

        return $this->token;
    }

    public function test_register_success()
    {
        $response = $this->postJson("{$this->prefix}/register", [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(201)->assertJsonStructure(['token']);
    }

    public function test_login_success()
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->postJson("{$this->prefix}/login", [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200)->assertJsonStructure(['token']);
    }

    public function test_search_with_results()
    {
        $response = $this->getJson("{$this->prefix}/search?drug_name=paracetamol");

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => [
                    'rxcui',
                    'name',
                    'ingredient_base_names',
                    'dosage_forms',
                ],
            ]);
    }

    public function test_search_returns_empty()
    {
        $response = $this->getJson("{$this->prefix}/search?drug_name=paracetamols");

        $response->assertStatus(200)
            ->assertExactJson([]);
    }

    public function test_add_drug_success()
    {

        $token = $this->getToken();

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson("{$this->prefix}/drugs", ['rxcui' => '308068'])
            ->assertStatus(201);
    }

    public function test_add_drug_already_added_for_user()
    {

        $token = $this->getToken();

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson("{$this->prefix}/drugs", ['rxcui' => '308068']);

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson("{$this->prefix}/drugs", ['rxcui' => '308068'])
            ->assertStatus(400);
    }

    public function test_add_to_drugslist_drug_not_found()
    {

        $token = $this->getToken();

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson("{$this->prefix}/drugs", ['rxcui' => '308068a'])
            ->assertStatus(422);
    }

    public function test_add_to_drugslist_drug_invalid()
    {

        $token = $this->getToken();

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson("{$this->prefix}/drugs", ['rxcui' => '0'])
            ->assertStatus(422);
    }

    public function test_get_user_drug_list()
    {

        $token = $this->getToken();

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson("{$this->prefix}/drugs", ['rxcui' => '308068']);

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson("{$this->prefix}/drugs")
            ->assertOk()
            ->assertJsonIsArray();
    }

    public function test_delete_user_drug_success()
    {

        $token = $this->getToken();

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson("{$this->prefix}/drugs", ['rxcui' => '308068']);

        $this->withHeader('Authorization', "Bearer $token")
            ->deleteJson("{$this->prefix}/drugs", ['rxcui' => '308068'])
            ->assertOk();
    }

    public function test_delete_user_drug_invalid()
    {

        $token = $this->getToken();

        $this->withHeader('Authorization', "Bearer $token")
            ->deleteJson("{$this->prefix}/drugs", ['rxcui' => '308068'])
            ->assertStatus(422);
    }
}
