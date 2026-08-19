<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\Process\Process;

/**
 * CephMirrorStepController
 *
 * Exposes each step of the Ceph RBD Mirroring setup as a dedicated REST endpoint.
 * Every method returns a JSON response with a log_id that can be streamed via SSE.
 *
 * API Reference:
 *   POST /api/mirroring/steps/create-user       → Step 1 : Create Ceph user on Site A
 *   POST /api/mirroring/steps/transfer-keyring  → Step 2 : Transfer keyring A→B
 *   POST /api/mirroring/steps/configure-site-b  → Step 3 : Configure Site B user + config
 *   POST /api/mirroring/steps/enable-pool       → Step 4 : Enable pool mirroring on A & B
 *   POST /api/mirroring/steps/configure-peer    → Step 5 : Add peer on Site B
 *   POST /api/mirroring/steps/setup-daemon      → Step 6 : Install & start rbd-mirror daemon
 *   POST /api/mirroring/steps/enable-image      → Step 7 : Enable image mirror + schedule
 *   POST /api/mirroring/steps/demote            → Failover: Demote pool/image on a node
 *   POST /api/mirroring/steps/promote           → Failover: Promote pool/image on a node
 *   GET  /api/mirroring/steps/status            → Pool mirror status (rbd mirror pool status)
 */
class CephMirrorStepController extends Controller
{
    private string $logDir;
    private string $SSH = 'ssh -o StrictHostKeyChecking=accept-new -o ConnectTimeout=10 root@';

    public function __construct()
    {
        $this->logDir = storage_path('logs/ceph');
        if (! is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
    }

    // ════════════════════════════════════════════════════════════
    //  SETUP STEPS
    // ════════════════════════════════════════════════════════════

    /**
     * Step 1 — Create Ceph mirroring user on Site A.
     *
     * POST /api/mirroring/steps/create-user
     * Body: { site_a: "IP", pool: "name" }
     */
    public function createUser(Request $request): JsonResponse
    {
        $data = $request->validate([
            'site_a' => 'required|string',
            'pool'   => 'required|string',
        ]);

        $cmd = [
            'ssh', '-o', 'StrictHostKeyChecking=accept-new', '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=10',
            "root@{$data['site_a']}",
            "ceph auth get-or-create client.rbd-mirror-peer-a mon 'profile rbd' osd 'profile rbd' -o /etc/pve/priv/site-b.client.rbd-mirror-peer-a.keyring",
        ];

        return $this->runStep($cmd, 'step1_create_user', 'Step 1 — Create Ceph user on Site A');
    }

    /**
     * Step 2 — Transfer keyring and ceph.conf from Site A to Site B.
     *
     * POST /api/mirroring/steps/transfer-keyring
     * Body: { site_a: "IP", site_b: "IP" }
     */
    public function transferKeyring(Request $request): JsonResponse
    {
        $data = $request->validate([
            'site_a' => 'required|string',
            'site_b' => 'required|string',
        ]);

        $siteA = $data['site_a'];
        $siteB = $data['site_b'];

        // Build a compound remote command: copy keyring + ceph.conf from A to B
        $remoteCmd = implode(' && ', [
            "scp -o StrictHostKeyChecking=accept-new /etc/pve/priv/site-b.client.rbd-mirror-peer-a.keyring root@{$siteB}:/etc/pve/priv/site-a.client.rbd-mirror-peer-a.keyring",
            "scp -o StrictHostKeyChecking=accept-new /etc/pve/ceph.conf root@{$siteB}:/etc/pve/site-a.conf",
        ]);

        $cmd = [
            'ssh', '-o', 'StrictHostKeyChecking=accept-new', '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=10',
            "root@{$siteA}", $remoteCmd,
        ];

        return $this->runStep($cmd, 'step2_transfer_keyring', 'Step 2 — Transfer keyring & config A→B');
    }

    /**
     * Step 3 — Configure Site B: symlink config + create local rbd-mirror user.
     *
     * POST /api/mirroring/steps/configure-site-b
     * Body: { site_b: "IP" }
     */
    public function configureSiteB(Request $request): JsonResponse
    {
        $data = $request->validate(['site_b' => 'required|string']);

        $remoteCmd = implode(' && ', [
            'ln -sf /etc/pve/site-a.conf /etc/ceph/site-a.conf',
            "ceph auth get-or-create client.rbd-mirror.\$(hostname) mon 'profile rbd-mirror' osd 'profile rbd' -o /etc/pve/priv/ceph.client.rbd-mirror.\$(hostname).keyring",
        ]);

        $cmd = [
            'ssh', '-o', 'StrictHostKeyChecking=accept-new', '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=10',
            "root@{$data['site_b']}", $remoteCmd,
        ];

        return $this->runStep($cmd, 'step3_configure_site_b', 'Step 3 — Configure Site B user & config symlink');
    }

    /**
     * Step 4 — Enable pool-level mirroring on both sites.
     *
     * POST /api/mirroring/steps/enable-pool
     * Body: { site_a: "IP", site_b: "IP", pool: "name" }
     */
    public function enablePool(Request $request): JsonResponse
    {
        $data = $request->validate([
            'site_a' => 'required|string',
            'site_b' => 'required|string',
            'pool'   => 'required|string',
        ]);

        // Two SSH commands; chain them in a shell -c wrapper
        $compound = implode(' && ', [
            "ssh -o StrictHostKeyChecking=accept-new -o BatchMode=yes -o ConnectTimeout=10 root@{$data['site_a']} 'rbd mirror pool enable {$data['pool']} image'",
            "ssh -o StrictHostKeyChecking=accept-new -o BatchMode=yes -o ConnectTimeout=10 root@{$data['site_b']} 'rbd mirror pool enable {$data['pool']} image'",
        ]);

        $cmd = ['bash', '-c', $compound];

        return $this->runStep($cmd, 'step4_enable_pool', "Step 4 — Enable pool mirroring on {$data['pool']}");
    }

    /**
     * Step 5 — Configure peer on Site B (connect it to Site A).
     *
     * POST /api/mirroring/steps/configure-peer
     * Body: { site_b: "IP", pool: "name" }
     */
    public function configurePeer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'site_b' => 'required|string',
            'pool'   => 'required|string',
        ]);

        $cmd = [
            'ssh', '-o', 'StrictHostKeyChecking=accept-new', '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=10',
            "root@{$data['site_b']}",
            "rbd mirror pool peer add {$data['pool']} client.rbd-mirror-peer-a@site-a",
        ];

        return $this->runStep($cmd, 'step5_configure_peer', 'Step 5 — Configure peer Site A on Site B');
    }

    /**
     * Step 6 — Install and start the rbd-mirror daemon on Site B.
     *
     * POST /api/mirroring/steps/setup-daemon
     * Body: { site_b: "IP" }
     */
    public function setupDaemon(Request $request): JsonResponse
    {
        $data = $request->validate(['site_b' => 'required|string']);

        $remoteCmd = implode(' && ', [
            'apt-get update',
            'apt-get install -y rbd-mirror',
            'systemctl enable ceph-rbd-mirror.target',
            'cp /usr/lib/systemd/system/ceph-rbd-mirror@.service /etc/systemd/system/ceph-rbd-mirror@.service',
            "sed -i -e 's/setuser ceph.*/setuser root --setgroup root/' /etc/systemd/system/ceph-rbd-mirror@.service",
            'systemctl daemon-reload',
            'systemctl enable --now ceph-rbd-mirror@rbd-mirror.$(hostname).service',
        ]);

        $cmd = [
            'ssh', '-o', 'StrictHostKeyChecking=accept-new', '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=10',
            "root@{$data['site_b']}", $remoteCmd,
        ];

        return $this->runStep($cmd, 'step6_setup_daemon', 'Step 6 — Install & start rbd-mirror daemon on Site B', 180);
    }

    /**
     * Step 7 — Enable image-level mirroring and optional snapshot schedule.
     *
     * POST /api/mirroring/steps/enable-image
     * Body: { site_a: "IP", pool: "name", image: "name", mode: "snapshot|journal", schedule?: "5m" }
     */
    public function enableImage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'site_a'   => 'required|string',
            'pool'     => 'required|string',
            'image'    => 'required|string',
            'mode'     => 'required|in:snapshot,journal',
            'schedule' => 'nullable|string',
        ]);

        $commands = [
            "rbd mirror image enable {$data['pool']}/{$data['image']} {$data['mode']}",
        ];

        if (! empty($data['schedule']) && $data['mode'] === 'snapshot') {
            $commands[] = "rbd mirror snapshot schedule add --pool {$data['pool']} {$data['schedule']}";
        }

        $cmd = [
            'ssh', '-o', 'StrictHostKeyChecking=accept-new', '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=10',
            "root@{$data['site_a']}", implode(' && ', $commands),
        ];

        return $this->runStep($cmd, 'step7_enable_image', "Step 7 — Enable image mirror {$data['pool']}/{$data['image']}");
    }

    // ════════════════════════════════════════════════════════════
    //  FAILOVER STEPS
    // ════════════════════════════════════════════════════════════

    /**
     * Demote a pool or image on a node.
     *
     * POST /api/mirroring/steps/demote
     * Body: { node: "IP", pool: "name", image?: "name", force?: true }
     */
    public function demote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'node'  => 'required|string',
            'pool'  => 'required|string',
            'image' => 'nullable|string',
            'force' => 'nullable|boolean',
        ]);

        $target = ! empty($data['image'])
            ? "image {$data['pool']}/{$data['image']}"
            : "pool {$data['pool']}";

        $force = ! empty($data['force']) ? '--force' : '';
        $cmd = [
            'ssh', '-o', 'StrictHostKeyChecking=accept-new', '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=10',
            "root@{$data['node']}", "rbd mirror {$target} demote {$force}",
        ];

        return $this->runStep($cmd, 'failover_demote', "Failover — Demote on {$data['node']}");
    }

    /**
     * Promote a pool or image on a node.
     *
     * POST /api/mirroring/steps/promote
     * Body: { node: "IP", pool: "name", image?: "name", force?: true }
     */
    public function promote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'node'  => 'required|string',
            'pool'  => 'required|string',
            'image' => 'nullable|string',
            'force' => 'nullable|boolean',
        ]);

        $target = ! empty($data['image'])
            ? "image {$data['pool']}/{$data['image']}"
            : "pool {$data['pool']}";

        $force = ! empty($data['force']) ? '--force' : '';
        $cmd = [
            'ssh', '-o', 'StrictHostKeyChecking=accept-new', '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=10',
            "root@{$data['node']}", "rbd mirror {$target} promote {$force}",
        ];

        return $this->runStep($cmd, 'failover_promote', "Failover — Promote on {$data['node']}");
    }

    // ════════════════════════════════════════════════════════════
    //  STATUS
    // ════════════════════════════════════════════════════════════

    /**
     * Get current mirror pool status.
     *
     * GET /api/mirroring/steps/status?node=IP&pool=NAME
     */
    public function poolStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'node' => 'required|string',
            'pool' => 'required|string',
        ]);

        $process = new Process([
            'ssh', '-o', 'StrictHostKeyChecking=accept-new', '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=10',
            "root@{$data['node']}",
            "rbd mirror pool status {$data['pool']} --verbose --format json",
        ]);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            return response()->json([
                'error'  => 'Impossible de récupérer le statut.',
                'detail' => $process->getErrorOutput(),
            ], 500);
        }

        $raw = json_decode($process->getOutput(), true);

        return response()->json([
            'node'    => $data['node'],
            'pool'    => $data['pool'],
            'summary' => $raw['summary'] ?? [],
            'images'  => $raw['images'] ?? [],
            'raw'     => $raw,
        ]);
    }

    // ════════════════════════════════════════════════════════════
    //  Private helpers (shared with CephMirroringController)
    // ════════════════════════════════════════════════════════════

    private function runStep(array $cmd, string $prefix, string $label, int $timeout = 60): JsonResponse
    {
        $logId   = $prefix . '_' . date('Ymd_His') . '_' . uniqid();
        $logFile = $this->logDir . "/{$logId}.log";
        $doneFile = $this->logDir . "/{$logId}.done";

        // Write header
        file_put_contents($logFile, "[DRS] {$label}\n");
        file_put_contents($logFile, "[DRS] " . now()->format('Y-m-d H:i:s') . "\n", FILE_APPEND);
        file_put_contents($logFile, "[DRS] Cmd: " . implode(' ', $cmd) . "\n", FILE_APPEND);
        file_put_contents($logFile, str_repeat('─', 60) . "\n", FILE_APPEND);

        $process = new Process($cmd);
        $process->setTimeout($timeout);

        $process->start(function (string $type, string $buffer) use ($logFile) {
            file_put_contents($logFile, $buffer, FILE_APPEND);
        });

        $process->wait(function (string $type, string $buffer) use ($logFile) {
            file_put_contents($logFile, $buffer, FILE_APPEND);
        });

        $exitCode = $process->getExitCode() ?? 1;
        $success  = $exitCode === 0;

        file_put_contents($logFile, "\n" . str_repeat('─', 60) . "\n", FILE_APPEND);
        file_put_contents($logFile,
            ($success ? '[SUCCESS]' : '[ERROR]') . " Code de sortie : {$exitCode}\n",
            FILE_APPEND
        );
        file_put_contents($doneFile, (string) $exitCode);

        return response()->json([
            'log_id'  => $logId,
            'success' => $success,
            'message' => $success
                ? "{$label} → Succès ✓"
                : "{$label} → Échec ✗ (exit {$exitCode})",
            'output'  => $process->getOutput(),
            'error'   => $process->getErrorOutput() ?: null,
        ], $success ? 200 : 500);
    }
}
