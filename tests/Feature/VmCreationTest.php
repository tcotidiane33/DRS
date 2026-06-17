<?php

namespace Tests\Feature;

use App\Jobs\CreateProxmoxVm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class VmCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_vm_create_form_is_accessible(): void
    {
        $response = $this->get(route('vms.create'));

        $response->assertStatus(200);
        $response->assertSee('Créer une VM/CT');
    }

    public function test_vm_creation_dispatches_job(): void
    {
        Bus::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('vms.store'), [
            'name'       => 'test-vm',
            'memory'     => 1024,
            'cores'      => 2,
            'disk_size'  => 20,
            'storage'    => 'local',
            'bridge'     => 'vmbr0',
            'method'     => 'memory',
            'type'       => 'vm',
            'template'   => 'local:100',
            'ostemplate' => '',
        ]);

        $response->assertRedirect(route('vms.create'));
        $response->assertSessionHas('success');

        Bus::assertDispatched(CreateProxmoxVm::class, function ($job) use ($user) {
            $reflection = new \ReflectionClass($job);
            $property = $reflection->getProperty('params');
            $property->setAccessible(true);
            $params = $property->getValue($job);

            return $params['name'] === 'test-vm'
                && $params['type'] === 'vm'
                && $params['storage'] === 'local';
        });
    }

    public function test_ct_creation_dispatches_job(): void
    {
        Bus::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('vms.store'), [
            'name'       => 'test-ct',
            'memory'     => 512,
            'cores'      => 1,
            'disk_size'  => 10,
            'storage'    => 'local',
            'bridge'     => 'vmbr0',
            'method'     => 'cpu',
            'type'       => 'ct',
            'ostemplate' => 'local:ubuntu-22.04-standard_22.04-1_amd64.tar.zst',
            'template'   => '',
        ]);

        $response->assertRedirect(route('vms.create'));
        $response->assertSessionHas('success');

        Bus::assertDispatched(CreateProxmoxVm::class, function ($job) {
            $reflection = new \ReflectionClass($job);
            $property = $reflection->getProperty('params');
            $property->setAccessible(true);
            $params = $property->getValue($job);

            return $params['name'] === 'test-ct'
                && $params['type'] === 'ct'
                && $params['ostemplate'] !== '';
        });
    }

    public function test_vm_creation_validation_errors(): void
    {
        $response = $this->post(route('vms.store'), [
            'name'      => 'invalid name',
            'memory'    => 'foo',
            'cores'     => 0,
            'disk_size' => -1,
            'storage'   => '',
            'bridge'    => '',
            'method'    => 'invalid',
            'type'      => 'unknown',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['name', 'memory', 'cores', 'disk_size', 'storage', 'bridge', 'method', 'type']);
    }
}
