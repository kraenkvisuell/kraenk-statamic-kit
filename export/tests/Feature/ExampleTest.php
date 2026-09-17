<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Kraenkvisuell\StatamicKit\Database\Seeders\DemoPagesSeeder;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A smoke test for a fresh site: the kit's migrations run, the demo pages
     * seeder fills the database, and the start page renders.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->seed(DemoPagesSeeder::class);

        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
