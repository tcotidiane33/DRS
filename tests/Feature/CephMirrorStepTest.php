<?php

namespace Tests\Feature;

use Tests\TestCase;

class CephMirrorStepTest extends TestCase
{
    /**
     * Test Step 1 — create-user validation
     */
    public function test_create_user_requires_site_a_and_pool(): void
    {
        $response = $this->postJson('/api/mirroring/steps/create-user', []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['site_a', 'pool']);
    }

    /**
     * Test Step 2 — transfer-keyring validation
     */
    public function test_transfer_keyring_requires_both_sites(): void
    {
        $response = $this->postJson('/api/mirroring/steps/transfer-keyring', []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['site_a', 'site_b']);
    }

    /**
     * Test Step 3 — configure-site-b validation
     */
    public function test_configure_site_b_requires_site_b(): void
    {
        $response = $this->postJson('/api/mirroring/steps/configure-site-b', []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['site_b']);
    }

    /**
     * Test Step 4 — enable-pool validation
     */
    public function test_enable_pool_requires_sites_and_pool(): void
    {
        $response = $this->postJson('/api/mirroring/steps/enable-pool', []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['site_a', 'site_b', 'pool']);
    }

    /**
     * Test Step 5 — configure-peer validation
     */
    public function test_configure_peer_requires_site_b_and_pool(): void
    {
        $response = $this->postJson('/api/mirroring/steps/configure-peer', []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['site_b', 'pool']);
    }

    /**
     * Test Step 6 — setup-daemon validation
     */
    public function test_setup_daemon_requires_site_b(): void
    {
        $response = $this->postJson('/api/mirroring/steps/setup-daemon', []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['site_b']);
    }

    /**
     * Test Step 7 — enable-image validation
     */
    public function test_enable_image_requires_fields(): void
    {
        $response = $this->postJson('/api/mirroring/steps/enable-image', []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['site_a', 'pool', 'image', 'mode']);
    }

    /**
     * Test enable-image mode must be snapshot or journal
     */
    public function test_enable_image_mode_validation(): void
    {
        $response = $this->postJson('/api/mirroring/steps/enable-image', [
            'site_a' => '10.0.0.1',
            'pool'   => 'rbd',
            'image'  => 'test-disk-0',
            'mode'   => 'invalid',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['mode']);
    }

    /**
     * Test Failover — demote validation
     */
    public function test_demote_requires_node_and_pool(): void
    {
        $response = $this->postJson('/api/mirroring/steps/demote', []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['node', 'pool']);
    }

    /**
     * Test Failover — promote validation
     */
    public function test_promote_requires_node_and_pool(): void
    {
        $response = $this->postJson('/api/mirroring/steps/promote', []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['node', 'pool']);
    }

    /**
     * Test Status — requires node and pool query params
     */
    public function test_pool_status_requires_node_and_pool(): void
    {
        $response = $this->getJson('/api/mirroring/steps/status');
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['node', 'pool']);
    }

    /**
     * Test dashboard renders with logs variable
     */
    public function test_mirroring_dashboard_renders(): void
    {
        $response = $this->get('/mirroring');
        $response->assertStatus(200);
        $response->assertSee('Ceph RBD Mirroring');
    }

    /**
     * Test GET /mirroring/setup redirects back to index
     */
    public function test_get_setup_redirects_to_index(): void
    {
        $response = $this->get('/mirroring/setup');
        $response->assertRedirect(route('mirroring.index'));
    }

    /**
     * Test GET /mirroring/failover redirects back to index
     */
    public function test_get_failover_redirects_to_index(): void
    {
        $response = $this->get('/mirroring/failover');
        $response->assertRedirect(route('mirroring.index'));
    }

    /**
     * Test logs list endpoint returns JSON array
     */
    public function test_logs_list_returns_json(): void
    {
        $response = $this->getJson('/mirroring/logs');
        $response->assertStatus(200);
        $response->assertJsonIsArray();
    }
}
