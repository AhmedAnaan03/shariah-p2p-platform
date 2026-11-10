<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    public function test_home_page_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertOk();
    }

    public function test_home_page_renders_the_welcome_react_page(): void
    {
        $response = $this->get('/');

        // component() also checks that resources/js/pages/Welcome.jsx exists on disk.
        $response->assertInertia(fn (Assert $page) => $page->component('Welcome'));
    }

    public function test_home_page_passes_the_three_platform_roles(): void
    {
        $response = $this->get('/');

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->has('roles', 3)
            ->where('roles.0.name', 'Borrower / SME')
            ->where('roles.1.name', 'Investor')
            ->where('roles.2.name', 'Administrator')
        );
    }

    public function test_unknown_url_returns_not_found(): void
    {
        $response = $this->get('/does-not-exist');

        $response->assertNotFound();
    }

    public function test_posting_to_the_home_page_is_not_allowed(): void
    {
        $response = $this->post('/');

        $response->assertMethodNotAllowed();
    }
}
