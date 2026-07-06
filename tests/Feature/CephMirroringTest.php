<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Symfony\Component\Process\Process;
use Mockery;

class CephMirroringTest extends TestCase
{
    /**
     * Test the index route returns the dashboard view.
     */
    public function test_dashboard_renders_successfully(): void
    {
        $response = $this->get('/mirroring');

        $response->assertStatus(200);
        $response->assertSee('Ceph RBD Mirroring');
    }

    /**
     * Test setup validation logic.
     */
    public function test_setup_validates_input(): void
    {
        $response = $this->post('/mirroring/setup', []);

        $response->assertSessionHasErrors(['site_a', 'site_b', 'pool', 'mode']);
    }

    /**
     * Test failover validation logic.
     */
    public function test_failover_validates_input(): void
    {
        $response = $this->post('/mirroring/failover', []);

        $response->assertSessionHasErrors(['node', 'action', 'pool']);
    }
}
