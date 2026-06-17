<?php

namespace App\Services;

use RuntimeException;

class NodeSelectorService
{
    public function __construct(private ProxmoxService $proxmox) {}

    public function bestByMemory(): string
    {
        $nodes = $this->proxmox->getNodes();
        $online = array_filter($nodes, fn ($n) => ($n['status'] ?? '') === 'online');

        if (empty($online)) {
            throw new RuntimeException('Aucun nœud Proxmox disponible.');
        }

        usort($online, function ($a, $b) {
            $ratioA = ($a['mem'] ?? 0) / max($a['maxmem'] ?? 1, 1);
            $ratioB = ($b['mem'] ?? 0) / max($b['maxmem'] ?? 1, 1);

            return $ratioA <=> $ratioB;
        });

        return $online[0]['node'];
    }

    public function bestByCpu(): string
    {
        $nodes = $this->proxmox->getNodes();
        $online = array_filter($nodes, fn ($n) => ($n['status'] ?? '') === 'online');

        if (empty($online)) {
            throw new RuntimeException('Aucun nœud Proxmox disponible.');
        }

        usort($online, fn ($a, $b) => ($a['cpu'] ?? 0) <=> ($b['cpu'] ?? 0));

        return $online[0]['node'];
    }

    public function bestByScore(): string
    {
        $nodes = $this->proxmox->getNodes();
        $online = array_filter($nodes, fn ($n) => ($n['status'] ?? '') === 'online');

        if (empty($online)) {
            throw new RuntimeException('Aucun nœud Proxmox disponible.');
        }

        usort($online, function ($a, $b) {
            $scoreA = (($a['mem'] ?? 0) / max($a['maxmem'] ?? 1, 1)) * 0.6
                    + (($a['cpu'] ?? 0)) * 0.4;
            $scoreB = (($b['mem'] ?? 0) / max($b['maxmem'] ?? 1, 1)) * 0.6
                    + (($b['cpu'] ?? 0)) * 0.4;

            return $scoreA <=> $scoreB;
        });

        return $online[0]['node'];
    }

    public function getNodesStatus(): array
    {
        $nodes = $this->proxmox->getNodes();

        return array_map(function ($node) {
            $memUsedGb = round(($node['mem'] ?? 0) / 1073741824, 1);
            $memTotalGb = round(($node['maxmem'] ?? 0) / 1073741824, 1);
            $memPct = $memTotalGb > 0 ? round($memUsedGb / $memTotalGb * 100) : 0;

            return [
                'node'      => $node['node'],
                'status'    => $node['status'] ?? 'unknown',
                'cpu_pct'   => round(($node['cpu'] ?? 0) * 100, 1),
                'mem_used'  => $memUsedGb,
                'mem_total' => $memTotalGb,
                'mem_pct'   => $memPct,
                'vms'       => $node['running-vms'] ?? 0,
            ];
        }, $nodes);
    }
}
