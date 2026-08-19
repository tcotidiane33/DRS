<?php

namespace App\Services;

use Symfony\Component\Process\Process;
use RuntimeException;

class SshKeyService
{
    private string $keyPath;
    private string $pubKeyPath;
    private string $expectScript;

    public function __construct()
    {
        $home = getenv('HOME') ?: (isset($_SERVER['HOME']) ? $_SERVER['HOME'] : '/Users/apple');
        $this->keyPath = $home . '/.ssh/id_ed25519';
        $this->pubKeyPath = $home . '/.ssh/id_ed25519.pub';
        $this->expectScript = base_path('scripts/ssh_authorize.exp');
    }

    /**
     * Get or generate the local SSH public key.
     */
    public function getPublicKey(): string
    {
        $this->ensureKeyExists();

        if (!file_exists($this->pubKeyPath)) {
            throw new RuntimeException("La clé publique SSH est introuvable à l'emplacement : {$this->pubKeyPath}");
        }

        return trim(file_get_contents($this->pubKeyPath));
    }

    public function getPublicKeyPath(): string
    {
        return $this->pubKeyPath;
    }

    /**
     * Ensure local ED25519 key exists, generating it if necessary.
     */
    public function ensureKeyExists(): void
    {
        if (file_exists($this->pubKeyPath) && file_exists($this->keyPath)) {
            return;
        }

        $dir = dirname($this->keyPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        $process = new Process([
            'ssh-keygen',
            '-t', 'ed25519',
            '-N', '',
            '-f', $this->keyPath,
            '-C', 'drs-proxmox-manager'
        ]);
        $process->setTimeout(15);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException("Échec de la génération de la clé SSH : " . $process->getErrorOutput());
        }
    }

    /**
     * Test passwordless SSH connectivity to a specific node.
     */
    public function testConnection(string $node): array
    {
        $cmd = [
            'ssh',
            '-o', 'StrictHostKeyChecking=accept-new',
            '-o', 'BatchMode=yes',
            '-o', 'ConnectTimeout=5',
            "root@{$node}",
            'echo "DRS_SSH_SUCCESS"'
        ];

        $process = new Process($cmd);
        $process->setTimeout(10);
        $process->run();

        $output = trim($process->getOutput());
        $error = trim($process->getErrorOutput());

        if ($process->isSuccessful() && str_contains($output, 'DRS_SSH_SUCCESS')) {
            return [
                'node'      => $node,
                'connected' => true,
                'status'    => 'authorized',
                'message'   => 'Connecté sans mot de passe ✓',
                'error'     => null,
            ];
        }

        // Determine reason for failure
        $status = 'unauthorized';
        $message = 'Clé SSH non autorisée (mot de passe requis)';

        if (str_contains($error, 'Connection refused') || str_contains($error, 'timed out') || str_contains($error, 'No route to host')) {
            $status = 'unreachable';
            $message = 'Hôte injoignable ou port SSH fermé';
        }

        return [
            'node'      => $node,
            'connected' => false,
            'status'    => $status,
            'message'   => $message,
            'error'     => $error ?: ($process->getExitCode() ? "Exit code: {$process->getExitCode()}" : 'Échec de connexion'),
        ];
    }

    /**
     * Inject the public key onto the remote node using root password via expect script.
     */
    public function authorizeKey(string $node, string $password): array
    {
        $pubKey = $this->getPublicKey();

        if (!file_exists($this->expectScript)) {
            throw new RuntimeException("Script expect introuvable : {$this->expectScript}");
        }

        $cmd = [
            $this->expectScript,
            $node,
            $password,
            $pubKey
        ];

        $process = new Process($cmd);
        $process->setTimeout(25);
        $process->run();

        $output = $process->getOutput();
        $error = $process->getErrorOutput();

        if (!$process->isSuccessful() || str_contains($error, 'ERREUR_AUTH') || str_contains($error, 'ERREUR_TIMEOUT')) {
            $msg = 'Échec de l\'autorisation';
            if (str_contains($error, 'ERREUR_AUTH') || str_contains($output, 'Permission denied')) {
                $msg = 'Mot de passe root incorrect';
            } elseif (str_contains($error, 'ERREUR_TIMEOUT')) {
                $msg = 'Délai d\'attente dépassé (serveur injoignable)';
            }

            return [
                'node'      => $node,
                'success'   => false,
                'message'   => $msg,
                'detail'    => $error ?: $output,
            ];
        }

        // Immediately verify passwordless access
        $test = $this->testConnection($node);

        if ($test['connected']) {
            return [
                'node'      => $node,
                'success'   => true,
                'message'   => "Clé SSH autorisée avec succès sur {$node} ! ✓",
                'connected' => true,
            ];
        }

        return [
            'node'      => $node,
            'success'   => true,
            'message'   => "Clé injectée, mais le test sans mot de passe a retourné : " . $test['message'],
            'connected' => false,
        ];
    }

    /**
     * Test multiple nodes in batch.
     */
    public function testBatch(array $nodes): array
    {
        $results = [];
        foreach ($nodes as $node) {
            $node = trim($node);
            if (!empty($node)) {
                $results[$node] = $this->testConnection($node);
            }
        }
        return $results;
    }
}
