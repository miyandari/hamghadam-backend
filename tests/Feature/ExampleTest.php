<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_homepage_redirects_to_admin(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }

    public function test_admin_panel_returns_successful_response(): void
    {
        $this->get('/admin')->assertOk();
    }

    public function test_student_portal_returns_successful_response(): void
    {
        $this->get('/student')->assertOk();
    }
}
