Bonne idée — voici l'architecture et le code complet pour intégrer ça dans Laravel.

## Architecture

```
Laravel App
    ├── ProxmoxService       ← appels API REST Proxmox
    ├── NodeSelectorService  ← logique de choix du meilleur nœud
    ├── VmController         ← endpoints HTTP
    └── Vue/Blade UI         ← formulaire de création simplifié
```

## 1. Variables d'environnement

Dans `.env` :

```env
PROXMOX_HOST=54.38.146.218
PROXMOX_PORT=8006
PROXMOX_USER=root@pam
PROXMOX_TOKEN_ID=proxlb
PROXMOX_TOKEN_SECRET=41c05576-7e33-4d27-a2d8-e2eb78649f13
PROXMOX_VERIFY_SSL=false
```

## 2. Config

```bash
php artisan make:config proxmox
```

`config/proxmox.php` :

```php
<?php
return [
    'host'         => env('PROXMOX_HOST'),
    'port'         => env('PROXMOX_PORT', 8006),
    'user'         => env('PROXMOX_USER'),
    'token_id'     => env('PROXMOX_TOKEN_ID'),
    'token_secret' => env('PROXMOX_TOKEN_SECRET'),
    'verify_ssl'   => env('PROXMOX_VERIFY_SSL', false),
];
```

## 3. ProxmoxService

`app/Services/ProxmoxService.php` :

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;

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
            'verify' => $config['verify_ssl'],
        ]);
    }

    public function get(string $path): array
    {
        $response = $this->client->get("{$this->baseUrl}{$path}");
        return $response->json('data', []);
    }

    public function post(string $path, array $data = []): array
    {
        $response = $this->client->post("{$this->baseUrl}{$path}", $data);
        return $response->json();
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
        return $this->post("/nodes/{$node}/qemu", $params);
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
        return $this->get("/nodes/{$node}/qemu");
    }
}
```

## 4. NodeSelectorService

`app/Services/NodeSelectorService.php` :

```php
<?php

namespace App\Services;

class NodeSelectorService
{
    public function __construct(private ProxmoxService $proxmox) {}

    /**
     * Retourne le nœud avec le plus de RAM libre (en %)
     */
    public function bestByMemory(): string
    {
        $nodes = $this->proxmox->getNodes();

        $online = array_filter($nodes, fn($n) => ($n['status'] ?? '') === 'online');

        if (empty($online)) {
            throw new \RuntimeException('Aucun nœud Proxmox disponible.');
        }

        usort($online, function ($a, $b) {
            $ratioA = ($a['mem'] ?? 0) / max($a['maxmem'] ?? 1, 1);
            $ratioB = ($b['mem'] ?? 0) / max($b['maxmem'] ?? 1, 1);
            return $ratioA <=> $ratioB; // croissant = moins chargé en premier
        });

        return $online[0]['node'];
    }

    /**
     * Retourne le nœud avec le moins de charge CPU
     */
    public function bestByCpu(): string
    {
        $nodes = $this->proxmox->getNodes();
        $online = array_filter($nodes, fn($n) => ($n['status'] ?? '') === 'online');

        if (empty($online)) {
            throw new \RuntimeException('Aucun nœud Proxmox disponible.');
        }

        usort($online, fn($a, $b) => ($a['cpu'] ?? 0) <=> ($b['cpu'] ?? 0));

        return $online[0]['node'];
    }

    /**
     * Score combiné RAM + CPU
     */
    public function bestByScore(): string
    {
        $nodes = $this->proxmox->getNodes();
        $online = array_filter($nodes, fn($n) => ($n['status'] ?? '') === 'online');

        if (empty($online)) {
            throw new \RuntimeException('Aucun nœud Proxmox disponible.');
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

    /**
     * Résumé de tous les nœuds pour affichage
     */
    public function getNodesStatus(): array
    {
        $nodes = $this->proxmox->getNodes();

        return array_map(function ($node) {
            $memUsedGb  = round(($node['mem']    ?? 0) / 1073741824, 1);
            $memTotalGb = round(($node['maxmem'] ?? 0) / 1073741824, 1);
            $memPct     = $memTotalGb > 0 ? round($memUsedGb / $memTotalGb * 100) : 0;

            return [
                'node'       => $node['node'],
                'status'     => $node['status'] ?? 'unknown',
                'cpu_pct'    => round(($node['cpu'] ?? 0) * 100, 1),
                'mem_used'   => $memUsedGb,
                'mem_total'  => $memTotalGb,
                'mem_pct'    => $memPct,
                'vms'        => $node['running-vms'] ?? 0,
            ];
        }, $nodes);
    }
}
```

## 5. Controller

```bash
php artisan make:controller VmController
```

`app/Http/Controllers/VmController.php` :

```php
<?php

namespace App\Http\Controllers;

use App\Services\ProxmoxService;
use App\Services\NodeSelectorService;
use Illuminate\Http\Request;

class VmController extends Controller
{
    public function __construct(
        private ProxmoxService $proxmox,
        private NodeSelectorService $selector,
    ) {}

    // ── GET /vms/create ──────────────────────────────────────────
    public function create()
    {
        $nodes  = $this->selector->getNodesStatus();
        $nextId = $this->proxmox->getNextVmid();

        return view('vms.create', compact('nodes', 'nextId'));
    }

    // ── POST /vms ────────────────────────────────────────────────
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:64|regex:/^[a-zA-Z0-9\-]+$/',
            'memory'    => 'required|integer|min:256|max:131072',
            'cores'     => 'required|integer|min:1|max:64',
            'disk_size' => 'required|integer|min:1|max:2000',
            'storage'   => 'required|string',
            'bridge'    => 'required|string',
            'method'    => 'required|in:memory,cpu,score',
            'type'      => 'required|in:vm,ct',
            'ostemplate'=> 'required_if:type,ct|nullable|string',
        ]);

        // 1. Choisir le meilleur nœud
        $node = match ($validated['method']) {
            'cpu'   => $this->selector->bestByCpu(),
            'score' => $this->selector->bestByScore(),
            default => $this->selector->bestByMemory(),
        };

        // 2. Obtenir le prochain VMID libre
        $vmid = $this->proxmox->getNextVmid();

        // 3. Créer VM ou CT
        if ($validated['type'] === 'vm') {
            $result = $this->proxmox->createVm($node, [
                'vmid'    => $vmid,
                'name'    => $validated['name'],
                'memory'  => $validated['memory'],
                'cores'   => $validated['cores'],
                'sockets' => 1,
                'net0'    => "virtio,bridge={$validated['bridge']}",
                'scsi0'   => "{$validated['storage']}:{$validated['disk_size']}",
                'scsihw'  => 'virtio-scsi-pci',
                'ostype'  => 'l26',
                'agent'   => 1,
            ]);
        } else {
            $result = $this->proxmox->createContainer($node, [
                'vmid'       => $vmid,
                'hostname'   => $validated['name'],
                'memory'     => $validated['memory'],
                'cores'      => $validated['cores'],
                'rootfs'     => "{$validated['storage']}:{$validated['disk_size']}",
                'net0'       => "name=eth0,bridge={$validated['bridge']},ip=dhcp",
                'ostemplate' => $validated['ostemplate'],
                'unprivileged' => 1,
                'start'      => 0,
            ]);
        }

        return redirect()->route('vms.index')->with('success',
            "✅ {$validated['type']} \"{$validated['name']}\" (ID: {$vmid}) créée sur le nœud {$node}."
        );
    }

    // ── GET /vms ─────────────────────────────────────────────────
    public function index()
    {
        $nodes = $this->selector->getNodesStatus();
        return view('vms.index', compact('nodes'));
    }

    // ── GET /api/nodes ───────────────────────────────────────────
    public function apiNodes()
    {
        return response()->json($this->selector->getNodesStatus());
    }

    // ── GET /api/best-node ───────────────────────────────────────
    public function apiBestNode(Request $request)
    {
        $method = $request->query('method', 'memory');
        $node   = match ($method) {
            'cpu'   => $this->selector->bestByCpu(),
            'score' => $this->selector->bestByScore(),
            default => $this->selector->bestByMemory(),
        };

        return response()->json(['node' => $node, 'method' => $method]);
    }
}
```

## 6. Routes

`routes/web.php` :

```php
use App\Http\Controllers\VmController;

Route::prefix('vms')->name('vms.')->group(function () {
    Route::get('/',        [VmController::class, 'index'])->name('index');
    Route::get('/create',  [VmController::class, 'create'])->name('create');
    Route::post('/',       [VmController::class, 'store'])->name('store');
});

Route::prefix('api')->name('api.')->group(function () {
    Route::get('/nodes',     [VmController::class, 'apiNodes'])->name('nodes');
    Route::get('/best-node', [VmController::class, 'apiBestNode'])->name('best-node');
});
```

## 7. Vue Blade

`resources/views/vms/create.blade.php` :

```blade
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Créer une VM / CT</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">

<div class="max-w-4xl mx-auto">

    {{-- Header --}}
    <h1 class="text-2xl font-bold text-gray-800 mb-6">
        🖥️ Nouvelle VM / Conteneur — Placement automatique
    </h1>

    {{-- Statut des nœuds --}}
    <div class="grid grid-cols-3 gap-4 mb-8">
        @foreach($nodes as $n)
        <div class="bg-white rounded-xl p-4 shadow border-l-4 
            {{ $n['status'] === 'online' ? 'border-green-400' : 'border-red-400' }}">
            <div class="flex justify-between items-center mb-2">
                <span class="font-bold text-gray-700">{{ $n['node'] }}</span>
                <span class="text-xs px-2 py-1 rounded-full 
                    {{ $n['status'] === 'online' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                    {{ $n['status'] }}
                </span>
            </div>
            <div class="text-sm text-gray-600">
                <div class="flex justify-between mb-1">
                    <span>RAM</span>
                    <span>{{ $n['mem_used'] }} / {{ $n['mem_total'] }} Go ({{ $n['mem_pct'] }}%)</span>
                </div>
                <div class="w-full bg-gray-200 rounded h-2 mb-2">
                    <div class="h-2 rounded {{ $n['mem_pct'] > 80 ? 'bg-red-500' : ($n['mem_pct'] > 60 ? 'bg-yellow-400' : 'bg-green-400') }}"
                         style="width: {{ $n['mem_pct'] }}%"></div>
                </div>
                <div class="flex justify-between">
                    <span>CPU</span><span>{{ $n['cpu_pct'] }}%</span>
                </div>
                <div class="w-full bg-gray-200 rounded h-2 mt-1">
                    <div class="h-2 rounded {{ $n['cpu_pct'] > 80 ? 'bg-red-500' : 'bg-blue-400' }}"
                         style="width: {{ $n['cpu_pct'] }}%"></div>
                </div>
                <div class="mt-2 text-xs text-gray-400">{{ $n['vms'] }} VMs actives</div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Formulaire --}}
    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif

    <form action="{{ route('vms.store') }}" method="POST"
          class="bg-white rounded-xl shadow p-6 space-y-5">
        @csrf

        {{-- Type --}}
        <div class="flex gap-6">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" name="type" value="vm" checked
                       onchange="toggleType(this.value)">
                <span class="font-medium">🖥️ VM (QEMU)</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" name="type" value="ct"
                       onchange="toggleType(this.value)">
                <span class="font-medium">📦 Conteneur (LXC)</span>
            </label>
        </div>

        {{-- Nom --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nom</label>
            <input type="text" name="name" value="{{ old('name') }}"
                   placeholder="ex: web-server-01"
                   class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400"
                   required>
            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- RAM / CPU --}}
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">RAM (Mo)</label>
                <select name="memory" class="w-full border rounded-lg px-3 py-2">
                    <option value="512">512 Mo</option>
                    <option value="1024">1 Go</option>
                    <option value="2048" selected>2 Go</option>
                    <option value="4096">4 Go</option>
                    <option value="8192">8 Go</option>
                    <option value="16384">16 Go</option>
                    <option value="32768">32 Go</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">vCPU</label>
                <select name="cores" class="w-full border rounded-lg px-3 py-2">
                    @foreach([1,2,4,8,16] as $c)
                        <option value="{{ $c }}" {{ $c == 2 ? 'selected' : '' }}>
                            {{ $c }} cœur{{ $c > 1 ? 's' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Disque / Stockage --}}
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Disque (Go)</label>
                <input type="number" name="disk_size" value="20" min="1" max="2000"
                       class="w-full border rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Stockage</label>
                <input type="text" name="storage" value="local-zfs"
                       class="w-full border rounded-lg px-3 py-2">
            </div>
        </div>

        {{-- Réseau --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Bridge réseau</label>
            <input type="text" name="bridge" value="vmbr0"
                   class="w-full border rounded-lg px-3 py-2">
        </div>

        {{-- Template CT --}}
        <div id="ct-template" class="hidden">
            <label class="block text-sm font-medium text-gray-700 mb-1">Template LXC</label>
            <input type="text" name="ostemplate"
                   placeholder="ex: local:vztmpl/debian-12-standard_12.2-1_amd64.tar.zst"
                   class="w-full border rounded-lg px-3 py-2">
        </div>

        {{-- Méthode de placement --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Méthode de sélection du nœud
            </label>
            <div class="flex gap-4">
                <label class="flex items-center gap-2">
                    <input type="radio" name="method" value="memory" checked>
                    <span class="text-sm">💾 RAM libre</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="radio" name="method" value="cpu">
                    <span class="text-sm">⚡ CPU libre</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="radio" name="method" value="score">
                    <span class="text-sm">🎯 Score combiné (RAM 60% + CPU 40%)</span>
                </label>
            </div>
        </div>

        {{-- Aperçu nœud sélectionné --}}
        <div id="preview-node"
             class="hidden bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm text-blue-700">
        </div>

        <button type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition">
            🚀 Créer et déployer automatiquement
        </button>
    </form>
</div>

<script>
function toggleType(type) {
    document.getElementById('ct-template').classList.toggle('hidden', type !== 'ct');
}

// Aperçu du meilleur nœud en temps réel
document.querySelectorAll('input[name="method"]').forEach(radio => {
    radio.addEventListener('change', fetchBestNode);
});

async function fetchBestNode() {
    const method = document.querySelector('input[name="method"]:checked').value;
    const res    = await fetch(`/api/best-node?method=${method}`);
    const data   = await res.json();
    const el     = document.getElementById('preview-node');
    el.classList.remove('hidden');
    el.textContent = `📍 Nœud sélectionné automatiquement : ${data.node} (méthode : ${data.method})`;
}

fetchBestNode();
</script>
</body>
</html>
```

## 8. Enregistrer les services (optionnel mais propre)

`app/Providers/AppServiceProvider.php` :

```php
public function register(): void
{
    $this->app->singleton(ProxmoxService::class);
    $this->app->singleton(NodeSelectorService::class);
}
```

## Résumé du flux

```
Utilisateur remplit le formulaire
        ↓
Laravel reçoit le POST /vms
        ↓
NodeSelectorService::bestBy*()
  → appelle GET /api2/json/nodes
  → trie par RAM/CPU/score
  → retourne le meilleur nœud
        ↓
ProxmoxService::createVm() ou createContainer()
  → POST /api2/json/nodes/{node}/qemu ou /lxc
        ↓
Proxmox crée la VM sur le nœud optimal
```


Voici les trois fonctionnalités ajoutées : templates, suivi temps réel, et authentification.Je vais créer un artifact React complet avec les 3 fonctionnalités, puis te donner le code Laravel backend.Voici l'UI complète en React avec les 3 fonctionnalités. Ensuite le code Laravel backend.Voici maintenant le code Laravel backend complet pour les 3 fonctionnalités.

---

## 1. Authentification Laravel (Sanctum)

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

`app/Http/Controllers/AuthController.php` :

```php
<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|string',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($credentials)) {
            return response()->json(['message' => 'Identifiants invalides.'], 401);
        }

        $token = $request->user()->createToken('proxmox-manager')->plainTextToken;

        return response()->json(['token' => $token, 'user' => $request->user()]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Déconnecté.']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
```

---

## 2. Suivi temps réel des jobs (Events + Broadcasting)

```bash
composer require pusher/pusher-php-server
php artisan make:event VmJobUpdated
```

`app/Events/VmJobUpdated.php` :

```php
<?php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class VmJobUpdated implements ShouldBroadcast
{
    use SerializesModels;

    public function __construct(
        public readonly int    $jobId,
        public readonly string $status,    // queued | running | done | error
        public readonly int    $progress,  // 0-100
        public readonly string $message,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('proxmox-jobs');
    }

    public function broadcastAs(): string
    {
        return 'job.updated';
    }
}
```

---

## 3. JobService — création + suivi asynchrone

```bash
php artisan make:job CreateProxmoxVm
```

`app/Jobs/CreateProxmoxVm.php` :

```php
<?php
namespace App\Jobs;

use App\Events\VmJobUpdated;
use App\Models\VmJob;
use App\Services\ProxmoxService;
use App\Services\NodeSelectorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateProxmoxVm implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int   $jobId,
        private readonly array $params,
        private readonly string $method,
    ) {}

    public function handle(ProxmoxService $proxmox, NodeSelectorService $selector): void
    {
        $job = VmJob::findOrFail($this->jobId);

        try {
            // Étape 1 — sélection du nœud
            $this->progress($job, 10, 'running', 'Sélection du nœud optimal...');
            $node = match ($this->method) {
                'cpu'   => $selector->bestByCpu(),
                'score' => $selector->bestByScore(),
                default => $selector->bestByMemory(),
            };
            $job->update(['node' => $node]);

            // Étape 2 — VMID libre
            $this->progress($job, 25, 'running', "Nœud choisi : {$node} — réservation VMID...");
            $vmid = $proxmox->getNextVmid();
            $job->update(['vmid' => $vmid]);

            // Étape 3 — création
            $this->progress($job, 50, 'running', "Création de la machine (VMID {$vmid})...");

            if ($this->params['type'] === 'vm') {
                $proxmox->createVm($node, [
                    'vmid'    => $vmid,
                    'name'    => $this->params['name'],
                    'memory'  => $this->params['memory'],
                    'cores'   => $this->params['cores'],
                    'net0'    => "virtio,bridge={$this->params['bridge']}",
                    'scsi0'   => "{$this->params['storage']}:{$this->params['disk_size']}",
                    'scsihw'  => 'virtio-scsi-pci',
                    'ostype'  => 'l26',
                    'clone'   => $this->params['template'] ?? null,
                ]);
            } else {
                $proxmox->createContainer($node, [
                    'vmid'         => $vmid,
                    'hostname'     => $this->params['name'],
                    'memory'       => $this->params['memory'],
                    'cores'        => $this->params['cores'],
                    'rootfs'       => "{$this->params['storage']}:{$this->params['disk_size']}",
                    'net0'         => "name=eth0,bridge={$this->params['bridge']},ip=dhcp",
                    'ostemplate'   => $this->params['template'],
                    'unprivileged' => 1,
                ]);
            }

            // Terminé
            $this->progress($job, 100, 'done', "Machine \"{$this->params['name']}\" déployée sur {$node}.");
            $job->update(['status' => 'done', 'progress' => 100]);

        } catch (\Throwable $e) {
            $this->progress($job, $job->progress, 'error', "Erreur : {$e->getMessage()}");
            $job->update(['status' => 'error']);
        }
    }

    private function progress(VmJob $job, int $pct, string $status, string $msg): void
    {
        $job->update(['progress' => $pct, 'status' => $status]);
        broadcast(new VmJobUpdated($job->id, $status, $pct, $msg));
        sleep(1); // laisser le front recevoir les events
    }
}
```

---

## 4. Migration + Model VmJob

```bash
php artisan make:model VmJob -m
```

Migration :

```php
Schema::create('vm_jobs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->enum('type', ['vm', 'ct']);
    $table->string('node')->nullable();
    $table->integer('vmid')->nullable();
    $table->enum('status', ['queued','running','done','error'])->default('queued');
    $table->integer('progress')->default(0);
    $table->json('params');
    $table->timestamps();
});
```

---

## 5. Routes finales

```php
// routes/api.php
Route::post('/login',  [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post  ('/logout',      [AuthController::class, 'logout']);
    Route::get   ('/me',          [AuthController::class, 'me']);
    Route::get   ('/nodes',       [VmController::class, 'apiNodes']);
    Route::get   ('/best-node',   [VmController::class, 'apiBestNode']);
    Route::get   ('/jobs',        [VmController::class, 'jobs']);
    Route::post  ('/vms',         [VmController::class, 'store']);
    Route::get   ('/jobs/{id}',   [VmController::class, 'jobStatus']);
});
```

---

## 6. Polling côté front (si pas de WebSocket)

Si tu ne veux pas configurer Pusher/WebSocket, ajoute ce polling simple dans le composant Vue/Blade :

```javascript
// Polling toutes les 2s sur les jobs en cours
setInterval(async () => {
    const res  = await fetch('/api/jobs', { headers: { Authorization: `Bearer ${token}` } });
    const jobs = await res.json();
    updateJobList(jobs);
}, 2000);
```

---

## Résumé de l'architecture finale

```
Browser
  ├─ Login (Sanctum token)
  ├─ POST /api/vms
  │     → dispatch(CreateProxmoxVm::class)  [queue]
  │     → retourne jobId immédiatement
  └─ GET /api/jobs/{id}  ou  WebSocket channel proxmox-jobs
         → progress 0→100 + logs en temps réel
```

Veux-tu que je génère le tout sous forme d'un projet Laravel complet zippé, ou que j'ajoute la partie Vue.js/Inertia pour le frontend ?