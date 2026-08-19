<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\Process\Process;

class CephMirroringController extends Controller
{
    private string $logDir;

    public function __construct()
    {
        $this->logDir = storage_path('logs/ceph');
        if (! is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
    }

    public function index()
    {
        $logs = $this->getRecentLogs();
        return view('mirroring.index', compact('logs'));
    }

    /**
     * Launch the setup script asynchronously, write output to a log file.
     */
    public function setup(Request $request): JsonResponse
    {
        $request->validate([
            'site_a'   => 'required|string',
            'site_b'   => 'required|string',
            'pool'     => 'required|string',
            'mode'     => 'required|in:snapshot,journal',
            'image'    => 'nullable|string',
            'schedule' => 'nullable|string',
        ]);

        $cmd = [
            base_path('setup_rbd_mirror.sh'),
            '--site-a-node', $request->site_a,
            '--site-b-node', $request->site_b,
            '--pool',        $request->pool,
            '--mode',        $request->mode,
        ];

        if ($request->image) {
            $cmd[] = '--image';
            $cmd[] = $request->image;
        }

        if ($request->schedule && $request->mode === 'snapshot') {
            $cmd[] = '--schedule';
            $cmd[] = $request->schedule;
        }

        $logId  = 'setup_' . date('Ymd_His') . '_' . uniqid();
        $logFile = $this->logDir . "/{$logId}.log";

        $this->runAsyncWithLog($cmd, $logFile, 'RBD Mirroring Setup');

        return response()->json([
            'log_id'  => $logId,
            'message' => 'Script de mirroring lancé. Consultez les logs en temps réel.',
        ]);
    }

    /**
     * Launch the failover script asynchronously, write output to a log file.
     */
    public function failover(Request $request): JsonResponse
    {
        $request->validate([
            'node'   => 'required|string',
            'action' => 'required|in:promote,demote',
            'pool'   => 'required|string',
            'image'  => 'nullable|string',
            'force'  => 'nullable|boolean',
        ]);

        $cmd = [
            base_path('failover_recovery.sh'),
            '--node',   $request->node,
            '--action', $request->action,
            '--pool',   $request->pool,
        ];

        if ($request->image) {
            $cmd[] = '--image';
            $cmd[] = $request->image;
        }

        if ($request->force) {
            $cmd[] = '--force';
        }

        $logId   = 'failover_' . date('Ymd_His') . '_' . uniqid();
        $logFile = $this->logDir . "/{$logId}.log";

        $this->runAsyncWithLog($cmd, $logFile, 'Failover / Recovery');

        return response()->json([
            'log_id'  => $logId,
            'message' => "Action '{$request->action}' lancée sur {$request->node}.",
        ]);
    }

    /**
     * Stream a log file via Server-Sent Events (SSE).
     */
    public function streamLog(Request $request, string $logId): Response
    {
        $logId   = preg_replace('/[^a-zA-Z0-9_\-]/', '', $logId); // sanitize
        $logFile = $this->logDir . "/{$logId}.log";
        $doneFile = $this->logDir . "/{$logId}.done";

        return response()->stream(function () use ($logFile, $doneFile) {
            $offset = 0;
            $maxWait = 300; // 5 minute maximum stream time
            $waited  = 0;

            while ($waited < $maxWait) {
                if (file_exists($logFile)) {
                    $content = file_get_contents($logFile);
                    $newContent = substr($content, $offset);

                    if ($newContent !== '') {
                        $lines = explode("\n", $newContent);
                        foreach ($lines as $line) {
                            if ($line !== '') {
                                echo "data: " . json_encode(['line' => $line]) . "\n\n";
                            }
                        }
                        $offset += strlen($newContent);
                        ob_flush();
                        flush();
                    }
                }

                if (file_exists($doneFile)) {
                    $exitCode = trim(file_get_contents($doneFile));
                    $status   = $exitCode === '0' ? 'success' : 'error';
                    echo "data: " . json_encode(['done' => true, 'status' => $status, 'exit_code' => (int) $exitCode]) . "\n\n";
                    ob_flush();
                    flush();
                    break;
                }

                sleep(1);
                $waited++;
            }

            echo "data: " . json_encode(['done' => true, 'status' => 'timeout']) . "\n\n";
            ob_flush();
            flush();
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }

    /**
     * Return the list of recent log files as JSON.
     */
    public function listLogs(): JsonResponse
    {
        $logs = $this->getRecentLogs();
        return response()->json($logs);
    }

    /**
     * Return the full contents of a specific log file.
     */
    public function getLog(string $logId): JsonResponse
    {
        $logId   = preg_replace('/[^a-zA-Z0-9_\-]/', '', $logId);
        $logFile = $this->logDir . "/{$logId}.log";
        $doneFile = $this->logDir . "/{$logId}.done";

        if (! file_exists($logFile)) {
            return response()->json(['error' => 'Log introuvable.'], 404);
        }

        $exitCode = file_exists($doneFile) ? (int) trim(file_get_contents($doneFile)) : null;

        return response()->json([
            'log_id'    => $logId,
            'content'   => file_get_contents($logFile),
            'done'      => $exitCode !== null,
            'exit_code' => $exitCode,
            'status'    => $exitCode === null ? 'running' : ($exitCode === 0 ? 'success' : 'error'),
        ]);
    }

    // ---------------------  Private helpers  ------------------------

    private function runAsyncWithLog(array $cmd, string $logFile, string $label): void
    {
        $doneFile = str_replace('.log', '.done', $logFile);

        // Write header
        file_put_contents($logFile, "[DRS] {$label} — " . now()->format('Y-m-d H:i:s') . "\n");
        file_put_contents($logFile, "[DRS] Commande : " . implode(' ', $cmd) . "\n", FILE_APPEND);
        file_put_contents($logFile, str_repeat('─', 60) . "\n", FILE_APPEND);

        // Build a shell command that:
        //  1. Runs the script, appending stdout+stderr to the log file
        //  2. Captures the exit code
        //  3. Writes the exit code to the .done file
        // All in the background with nohup so it doesn't block PHP
        $escapedCmd = implode(' ', array_map('escapeshellarg', $cmd));
        $escapedLog = escapeshellarg($logFile);
        $escapedDone = escapeshellarg($doneFile);

        $shellCmd = sprintf(
            'nohup bash -c %s > /dev/null 2>&1 &',
            escapeshellarg(
                "{$escapedCmd} >> {$escapedLog} 2>&1; EXIT_CODE=\$?; "
                . "echo '' >> {$escapedLog}; "
                . "echo '────────────────────────────────────────────────────────────' >> {$escapedLog}; "
                . "echo \"[DRS] Terminé avec le code : \$EXIT_CODE\" >> {$escapedLog}; "
                . "echo \$EXIT_CODE > {$escapedDone}"
            )
        );

        // Launch and return immediately — does NOT block
        exec($shellCmd);
    }

    private function getRecentLogs(): array
    {
        $files = glob($this->logDir . '/*.log') ?: [];
        usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));

        return array_map(function (string $file) {
            $logId    = basename($file, '.log');
            $doneFile = str_replace('.log', '.done', $file);
            $exitCode = file_exists($doneFile) ? (int) trim(file_get_contents($doneFile)) : null;

            $prefix = str_starts_with($logId, 'setup_') ? 'Setup' : 'Failover';
            $ts     = filemtime($file);

            return [
                'log_id'  => $logId,
                'label'   => $prefix,
                'date'    => date('d/m/Y H:i:s', $ts),
                'status'  => $exitCode === null ? 'running' : ($exitCode === 0 ? 'success' : 'error'),
            ];
        }, array_slice($files, 0, 30));
    }
}
