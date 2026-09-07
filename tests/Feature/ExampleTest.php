<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // Upstream's stock assertion was assertStatus(200), which has never been
        // true here: routes/web.php puts / behind auth:sanctum and
        // redirectGuestsTo() sends guests to the login page.
        $response = $this->get('/');

        $response->assertRedirect(route('user.auth.index'));
    }
}
