<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\Process\Process;

class CephMirroringController extends Controller
{
    public function index()
    {
        return view('mirroring.index');
    }

    public function setup(Request $request)
    {
        $request->validate([
            'site_a' => 'required|string',
            'site_b' => 'required|string',
            'pool' => 'required|string',
            'mode' => 'required|in:snapshot,journal',
            'image' => 'nullable|string',
            'schedule' => 'nullable|string',
        ]);

        $cmd = [
            base_path('setup_rbd_mirror.sh'),
            '--site-a-node', $request->site_a,
            '--site-b-node', $request->site_b,
            '--pool', $request->pool,
            '--mode', $request->mode,
        ];

        if ($request->image) {
            $cmd[] = '--image';
            $cmd[] = $request->image;
        }

        if ($request->schedule && $request->mode === 'snapshot') {
            $cmd[] = '--schedule';
            $cmd[] = $request->schedule;
        }

        $process = new Process($cmd);
        $process->run();

        if (!$process->isSuccessful()) {
            return back()->with('error', 'Setup failed: ' . $process->getErrorOutput());
        }

        return back()->with('success', "RBD Mirroring setup completed! Output: " . $process->getOutput());
    }

    public function failover(Request $request)
    {
        $request->validate([
            'node' => 'required|string',
            'action' => 'required|in:promote,demote',
            'pool' => 'required|string',
            'image' => 'nullable|string',
            'force' => 'nullable|boolean',
        ]);

        $cmd = [
            base_path('failover_recovery.sh'),
            '--node', $request->node,
            '--action', $request->action,
            '--pool', $request->pool,
        ];

        if ($request->image) {
            $cmd[] = '--image';
            $cmd[] = $request->image;
        }

        if ($request->force) {
            $cmd[] = '--force';
        }

        $process = new Process($cmd);
        $process->run();

        if (!$process->isSuccessful()) {
            return back()->with('error', 'Failover action failed: ' . $process->getErrorOutput());
        }

        return back()->with('success', "Failover action completed! Output: " . $process->getOutput());
    }
}
