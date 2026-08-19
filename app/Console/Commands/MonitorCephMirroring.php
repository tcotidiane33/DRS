<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class MonitorCephMirroring extends Command
{
    protected $signature = 'ceph:monitor-mirroring';
    protected $description = 'Vérifie le statut du mirroring Ceph et alerte en cas de problème.';

    public function handle()
    {
        $node = config('ceph.monitor_node');
        $pool = config('ceph.monitor_pool');

        if (empty($node) || empty($pool)) {
            $this->error("CEPH_MONITOR_NODE ou CEPH_MONITOR_POOL non défini.");
            return Command::FAILURE;
        }

        $cmd = [
            'ssh', '-o', 'StrictHostKeyChecking=accept-new', '-o', 'ConnectTimeout=10',
            "root@{$node}",
            "rbd mirror pool status {$pool} --verbose --format json"
        ];

        $process = new Process($cmd);
        $process->setTimeout(30);
        $process->run();

        if (!$process->isSuccessful()) {
            $error = $process->getErrorOutput();
            Log::emergency("Ceph Mirroring Alert: Impossible de vérifier le statut sur {$node} pour le pool {$pool}.", [
                'error' => $error
            ]);
            $this->error("Échec de connexion ou d'exécution de la commande.");
            return Command::FAILURE;
        }

        $output = $process->getOutput();
        $data = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            Log::emergency("Ceph Mirroring Alert: Réponse JSON invalide depuis le nœud {$node}.", [
                'output' => $output
            ]);
            $this->error("Réponse JSON invalide.");
            return Command::FAILURE;
        }

        // Vérification de la santé globale (summary.health)
        $health = $data['summary']['health'] ?? 'UNKNOWN';
        if ($health !== 'OK') {
            Log::emergency("Ceph Mirroring Alert: État de santé du pool {$pool} est {$health} sur {$node}.", [
                'summary' => $data['summary'] ?? []
            ]);
            $this->error("Le statut de santé du pool est {$health}.");
            return Command::FAILURE;
        }

        // Vérifier les images s'il y a des erreurs
        $images = $data['images'] ?? [];
        $failedImages = [];
        foreach ($images as $image) {
            $state = $image['state'] ?? '';
            // Les états normaux dépendent si c'est master ou slave. 'error' est le mot-clé à chercher.
            if ($state === 'error' || str_contains($state, 'error')) {
                $failedImages[] = $image['name'] ?? 'unknown_image';
            }
        }

        if (count($failedImages) > 0) {
            Log::emergency("Ceph Mirroring Alert: Certaines images sont en erreur sur le pool {$pool}.", [
                'images' => $failedImages
            ]);
            $this->error("Des images sont en erreur : " . implode(', ', $failedImages));
            return Command::FAILURE;
        }

        $this->info("Statut Ceph Mirroring OK.");
        return Command::SUCCESS;
    }
}
