<?php

namespace App\Jobs;

use App\Events\VmJobUpdated;
use App\Models\VmJob;
use App\Services\NodeSelectorService;
use App\Services\ProxmoxService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class CreateProxmoxVm implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $jobId,
        private readonly array $params,
        private readonly string $method,
    ) {}

    public function handle(ProxmoxService $proxmox, NodeSelectorService $selector): void
    {
        $job = VmJob::findOrFail($this->jobId);

        try {
            $this->progress($job, 10, 'running', 'Sélection du nœud optimal...');

            $node = match ($this->method) {
                'cpu'   => $selector->bestByCpu(),
                'score' => $selector->bestByScore(),
                default => $selector->bestByMemory(),
            };
            $job->update(['node' => $node]);

            $this->progress($job, 25, 'running', "Nœud choisi : {$node} — réservation VMID...");

            $vmid = $proxmox->getNextVmid();
            $job->update(['vmid' => $vmid]);

            $this->progress($job, 50, 'running', "Création de la machine (VMID {$vmid})...");

            if ($this->params['type'] === 'vm') {
                $vmParams = [
                    'vmid'    => $vmid,
                    'name'    => $this->params['name'],
                    'memory'  => $this->params['memory'],
                    'cores'   => $this->params['cores'],
                    'sockets' => 1,
                    'net0'    => "virtio,bridge={$this->params['bridge']}",
                    'scsi0'   => "{$this->params['storage']}:{$this->params['disk_size']}",
                    'scsihw'  => 'virtio-scsi-pci',
                    'ostype'  => 'l26',
                    'agent'   => 1,
                ];

                if (! empty($this->params['template'])) {
                    $vmParams['clone'] = $this->params['template'];
                }

                $proxmox->createVm($node, $vmParams);
            } else {
                $proxmox->createContainer($node, [
                    'vmid'         => $vmid,
                    'hostname'     => $this->params['name'],
                    'memory'       => $this->params['memory'],
                    'cores'        => $this->params['cores'],
                    'rootfs'       => "{$this->params['storage']}:{$this->params['disk_size']}",
                    'net0'         => "name=eth0,bridge={$this->params['bridge']},ip=dhcp",
                    'ostemplate'   => $this->params['template'] ?? $this->params['ostemplate'] ?? '',
                    'unprivileged' => 1,
                    'start'        => 0,
                ]);
            }

            $this->progress(
                $job,
                100,
                'done',
                "Machine \"{$this->params['name']}\" déployée sur {$node}."
            );
        } catch (Throwable $e) {
            $this->progress($job, $job->progress, 'error', "Erreur : {$e->getMessage()}");
        }
    }

    private function progress(VmJob $job, int $pct, string $status, string $msg): void
    {
        $job->update(['progress' => $pct, 'status' => $status, 'message' => $msg]);
        broadcast(new VmJobUpdated($job->id, $status, $pct, $msg));
    }
}
