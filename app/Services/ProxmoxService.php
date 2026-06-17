<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ProxmoxService
{
    private string $baseUrl;

    private PendingRequest $client;

    public function __construct()
    {
        $config = config('proxmox');
        $this->baseUrl = "https://{$config['host']}:{$config['port']}/api2/json";

        $this->client = Http::withHeaders([
            'Authorization' => "PVEAPIToken={$config['user']}!{$config['token_id']}={$config['token_secret']}",
        ])->withOptions([
            'verify' => filter_var($config['verify_ssl'], FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    public function get(string $path): array
    {
        $response = $this->client->get("{$this->baseUrl}{$path}");
        $this->ensureSuccess($response);

        return $response->json('data', []);
    }

    public function post(string $path, array $data = []): array
    {
        $response = $this->client->post("{$this->baseUrl}{$path}", $data);
        $this->ensureSuccess($response);

        return $response->json() ?? [];
    }

    public function getNodes(): array
    {
        return $this->get('/nodes');
    }

    public function getNextVmid(): int
    {
        return (int) $this->get('/cluster/nextid');
    }

    public function createVm(string $node, array $params): array
    {
        return $this->post("/nodes/{$node}/qemu", array_filter($params, fn ($v) => $v !== null));
    }

    public function createContainer(string $node, array $params): array
    {
        return $this->post("/nodes/{$node}/lxc", $params);
    }

    public function getNodeStorage(string $node): array
    {
        return $this->get("/nodes/{$node}/storage");
    }

    public function getTemplates(string $node): array
    {
        $vms = $this->get("/nodes/{$node}/qemu");

        return array_values(array_filter($vms, fn ($vm) => ($vm['template'] ?? 0) == 1));
    }

    public function getTemplatesForSelect(string $node): array
    {
        return array_map(fn ($vm) => [
            'volid' => $vm['vmid'],
            'name'  => $vm['name'] ?? "VM {$vm['vmid']}",
            'vmid'  => $vm['vmid'],
        ], $this->getTemplates($node));
    }

    public function getContainerTemplates(string $node): array
    {
        $storage = $this->getNodeStorage($node);
        $templates = [];

        foreach ($storage as $store) {
            $content = explode(',', $store['content'] ?? '');

            if (! in_array('vztmpl', $content, true)) {
                continue;
            }

            $vols = $this->get("/nodes/{$node}/storage/{$store['storage']}/content?content=vztmpl");

            foreach ($vols as $vol) {
                $templates[] = [
                    'volid' => $vol['volid'] ?? '',
                    'name'  => basename($vol['volid'] ?? ''),
                    'size'  => $vol['size'] ?? 0,
                ];
            }
        }

        return $templates;
    }

    private function ensureSuccess(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $message = $response->json('errors')
            ?? $response->json('message')
            ?? ($response->body() ?: $response->reason());

        throw new RuntimeException(
            is_string($message) ? $message : json_encode($message)
        );
    }
}
