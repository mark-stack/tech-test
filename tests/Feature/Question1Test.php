<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class Question1Test extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_renders(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
