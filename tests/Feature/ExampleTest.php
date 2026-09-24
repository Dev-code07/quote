<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The root path sends the user to the authenticated dashboard, which itself
     * redirects guests to the login screen.
     */
    public function test_root_redirects_to_dashboard(): void
    {
        $this->get('/')->assertRedirect(route('dashboard'));
    }

    /**
     * Guests hitting the dashboard are sent to login.
     */
    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    /**
     * The application health endpoint is public.
     */
    public function test_health_check_endpoint_is_available(): void
    {
        $this->get('/up')->assertOk();
    }
}
