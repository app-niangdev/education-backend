<?php
// tests/Feature/HealthCheckTest.php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_endpoint_responds_ok(): void
    {
        $response = $this->get('/up');

        $response->assertStatus(200);
    }
}
