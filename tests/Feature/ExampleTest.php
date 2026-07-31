<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_displays_the_cover_page(): void
    {
        $response = $this->get('/');

        // '/' redirects to the localized welcome page 'bienvenue'
        $response->assertRedirect(route('bienvenue'));

        // follow the redirect and assert the view
        $this->followingRedirects()
            ->get('/')
            ->assertStatus(200)
            ->assertViewIs('bienvenue');
    }
}
