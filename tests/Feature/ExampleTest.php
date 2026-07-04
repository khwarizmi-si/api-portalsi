<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_api_has_no_html_root_page(): void
    {
        $response = $this->get('/');

        $response->assertStatus(404);
    }
}
