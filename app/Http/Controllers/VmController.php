<?php

namespace App\Http\Controllers;

use App\Jobs\CreateProxmoxVm;
use App\Models\VmJob;
use App\Services\NodeSelectorService;
use App\Services\ProxmoxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class VmController extends Controller
{
    public function __construct(
        private ProxmoxService $proxmox,
        private NodeSelectorService $selector,
    ) {}

    public function create(): View
    {
        try {
            $nodes = $this->selector->getNodesStatus();
            $nextId = $this->proxmox->getNextVmid();
            $proxmoxError = null;
        } catch (RuntimeException $e) {
            $nodes = [];
            $nextId = null;
            $proxmoxError = $e->getMessage();
        }

        return view('vms.create', compact('nodes', 'nextId', 'proxmoxError'));
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:64|regex:/^[a-zA-Z0-9\-]+$/',
            'memory'     => 'required|integer|min:256|max:131072',
            'cores'      => 'required|integer|min:1|max:64',
            'disk_size'  => 'required|integer|min:1|max:2000',
            'storage'    => 'required|string',
            'bridge'     => 'required|string',
            'method'     => 'required|in:memory,cpu,score',
            'type'       => 'required|in:vm,ct',
            'ostemplate' => 'required_if:type,ct|nullable|string',
            'template'   => 'nullable|string',
        ]);

        $params = [
            'name'      => $validated['name'],
            'memory'    => $validated['memory'],
            'cores'     => $validated['cores'],
            'disk_size' => $validated['disk_size'],
            'storage'   => $validated['storage'],
            'bridge'    => $validated['bridge'],
            'type'      => $validated['type'],
            'template'  => $validated['template'] ?? $validated['ostemplate'] ?? null,
            'ostemplate'=> $validated['ostemplate'] ?? null,
        ];

        $job = VmJob::create([
            'user_id'  => $request->user()?->id,
            'name'     => $validated['name'],
            'type'     => $validated['type'],
            'status'   => 'queued',
            'progress' => 0,
            'message'  => 'En attente de traitement...',
            'params'   => $params,
        ]);

        CreateProxmoxVm::dispatch($job->id, $params, $validated['method']);

        if ($request->expectsJson()) {
            return response()->json([
                'job_id'  => $job->id,
                'status'  => $job->status,
                'message' => 'Job de création lancé.',
            ], 202);
        }

        return redirect()
            ->route('vms.create')
            ->with('job_id', $job->id)
            ->with('success', "Déploiement de \"{$validated['name']}\" lancé (job #{$job->id}).");
    }

    public function index(): View
    {
        try {
            $nodes = $this->selector->getNodesStatus();
            $proxmoxError = null;
        } catch (RuntimeException $e) {
            $nodes = [];
            $proxmoxError = $e->getMessage();
        }

        $jobs = VmJob::query()->latest()->limit(20)->get();

        return view('vms.index', compact('nodes', 'jobs', 'proxmoxError'));
    }

    public function apiNodes(): JsonResponse
    {
        try {
            return response()->json($this->selector->getNodesStatus());
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }

    public function apiBestNode(Request $request): JsonResponse
    {
        try {
            $method = $request->query('method', 'memory');
            $node = match ($method) {
                'cpu'   => $this->selector->bestByCpu(),
                'score' => $this->selector->bestByScore(),
                default => $this->selector->bestByMemory(),
            };

            return response()->json(['node' => $node, 'method' => $method]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }

    public function apiTemplates(Request $request): JsonResponse
    {
        try {
            $node = $request->query('node');

            if (! $node) {
                $node = $this->selector->bestByMemory();
            }

            $type = $request->query('type', 'vm');

            $templates = $type === 'ct'
                ? $this->proxmox->getContainerTemplates($node)
                : $this->proxmox->getTemplatesForSelect($node);

            return response()->json(['node' => $node, 'templates' => $templates]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }

    public function jobs(Request $request): JsonResponse
    {
        $query = VmJob::query()->latest();

        if ($request->user()) {
            $query->where('user_id', $request->user()->id);
        }

        return response()->json($query->limit(50)->get());
    }

    public function jobStatus(int $id): JsonResponse
    {
        $job = VmJob::findOrFail($id);

        return response()->json($job);
    }
}
