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

    public function test_register_fail()
    {
        $response = $this->postJson("{$this->prefix}/register", [
            'name' => 'Test User',
            'password' => 'test@example.com',
        ]);

        $response->assertStatus(422)->assertJsonStructure(['errors' => ['email']]);

        $response = $this->postJson("{$this->prefix}/register", [
            'email' => 'testexample.com',
        ]);

        $response->assertStatus(422)->assertJsonStructure(['errors' => ['name', 'password']]);

        $this->assertDatabaseEmpty('users');
    }

    public function test_login_success()
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->postJson("{$this->prefix}/login", [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonStructure(['token']);
        $this->assertDatabaseHas('users', ['email' => $user->email]);
    }

    public function test_login_fail()
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->postJson("{$this->prefix}/login", [
            'email' => $user->email,
            'password' => '123',
        ]);

        $response->assertStatus(401)->assertJsonStructure(['error']);
    }

    public function test_search_with_results()
    {
        $response = $this->getJson("{$this->prefix}/search?drug_name=paracetamol");

        $response->assertOk()
            ->assertJsonStructure([
                '*' => [
                    'rxcui',
                    'drugName',
                    'ingredientBaseNames',
                    'dosageForm',
                ],
            ]);
    }

    public function test_search_returns_validation_error()
    {
        $response = $this->getJson("{$this->prefix}/search?drug_name=");

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['drug_name']]);
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

        $this->assertDatabaseEmpty('medications');
    }

    public function test_get_user_drug_list_success()
    {

        $token = $this->getToken();

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson("{$this->prefix}/drugs", ['rxcui' => '308068']);

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson("{$this->prefix}/drugs")
            ->assertOk()
            ->assertJsonStructure([
                '*' => [
                    'rxID',
                    'drugName',
                    'baseNames',
                    'doseFormGroupName',
                ],
            ]);
    }

    public function test_a_drug_belongs_to_a_user()
    {
        $user = User::factory()->create();
        $drug = $user->medications()->create([
            'user_id' => $user->id,
            'rxcui' => '308068',
        ]);

        $this->assertInstanceOf(User::class, $drug->user);
        $this->assertTrue($drug->user->is($user));
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
