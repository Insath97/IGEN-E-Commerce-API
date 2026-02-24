<?php

namespace Tests\Feature\V1\Customer;

use App\Models\User;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test registration without address fields.
     */
    public function test_customer_can_register_without_address()
    {
        $response = $this->postJson('/api/v1/customer/register', [
            'name' => 'John Doe',
            'username' => 'johndoe',
            'email' => 'johndoe@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '0771234567',
        ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Registration successful! Please check your email to verify your account.',
                 ]);

        $this->assertDatabaseHas('users', [
            'email' => 'johndoe@example.com',
            'username' => 'johndoe',
        ]);

        $this->assertDatabaseHas('customers', [
            'phone' => '0771234567',
            'address_line_1' => null,
            'city' => null,
        ]);
    }

    /**
     * Test registration fails with missing mandatory fields.
     */
    public function test_customer_registration_fails_missing_fields()
    {
        $response = $this->postJson('/api/v1/customer/register', [
            'name' => 'John Doe',
            // missing email, password, etc.
        ]);

        $response->assertStatus(422)
                 ->assertJsonFragment([
                     'success' => false,
                 ]);
    }
}
