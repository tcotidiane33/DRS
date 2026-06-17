<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer une VM / CT</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">

@include('vms.partials.nav')

<div class="max-w-4xl mx-auto p-8">

    <h1 class="text-2xl font-bold text-gray-800 mb-6">
        Nouvelle VM / Conteneur — Placement automatique
    </h1>

    @if($proxmoxError ?? false)
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            Connexion Proxmox impossible : {{ $proxmoxError }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        @foreach($nodes as $n)
        <div class="bg-white rounded-xl p-4 shadow border-l-4 {{ $n['status'] === 'online' ? 'border-green-400' : 'border-red-400' }}">
            <div class="flex justify-between items-center mb-2">
                <span class="font-bold text-gray-700">{{ $n['node'] }}</span>
                <span class="text-xs px-2 py-1 rounded-full {{ $n['status'] === 'online' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
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

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif

    <div id="job-tracker" class="hidden bg-white rounded-xl shadow p-4 mb-6">
        <h3 class="font-semibold text-gray-700 mb-2">Suivi du déploiement</h3>
        <div class="w-full bg-gray-200 rounded h-3 mb-2">
            <div id="job-progress-bar" class="h-3 rounded bg-blue-500 transition-all" style="width: 0%"></div>
        </div>
        <p id="job-message" class="text-sm text-gray-600"></p>
    </div>

    <form action="{{ route('vms.store') }}" method="POST" class="bg-white rounded-xl shadow p-6 space-y-5">
        @csrf

        <div class="flex flex-wrap gap-6">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" name="type" value="vm" checked onchange="toggleType(this.value)">
                <span class="font-medium">VM (QEMU)</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" name="type" value="ct" onchange="toggleType(this.value)">
                <span class="font-medium">Conteneur (LXC)</span>
            </label>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nom</label>
            <input type="text" name="name" value="{{ old('name') }}"
                   placeholder="ex: web-server-01"
                   class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400"
                   required>
            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

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

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Bridge réseau</label>
            <input type="text" name="bridge" value="vmbr0"
                   class="w-full border rounded-lg px-3 py-2">
        </div>

        <div id="vm-template" class="hidden">
            <label class="block text-sm font-medium text-gray-700 mb-1">Template VM (clone)</label>
            <select name="template" id="template-select-vm" class="w-full border rounded-lg px-3 py-2">
                <option value="">— Aucun (disque vierge) —</option>
            </select>
        </div>

        <div id="ct-template" class="hidden">
            <label class="block text-sm font-medium text-gray-700 mb-1">Template LXC</label>
            <select name="ostemplate" id="template-select-ct" class="w-full border rounded-lg px-3 py-2">
                <option value="">— Choisir un template —</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Méthode de sélection du nœud</label>
            <div class="flex flex-wrap gap-4">
                <label class="flex items-center gap-2">
                    <input type="radio" name="method" value="memory" checked>
                    <span class="text-sm">RAM libre</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="radio" name="method" value="cpu">
                    <span class="text-sm">CPU libre</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="radio" name="method" value="score">
                    <span class="text-sm">Score combiné (RAM 60% + CPU 40%)</span>
                </label>
            </div>
        </div>

        <div id="preview-node" class="hidden bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm text-blue-700"></div>

        @if($nextId)
        <p class="text-xs text-gray-400">Prochain VMID disponible : {{ $nextId }}</p>
        @endif

        <button type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition">
            Créer et déployer automatiquement
        </button>
    </form>
</div>

<script>
function toggleType(type) {
    document.getElementById('ct-template').classList.toggle('hidden', type !== 'ct');
    document.getElementById('vm-template').classList.toggle('hidden', type !== 'vm');
    loadTemplates(type);
}

document.querySelectorAll('input[name="method"]').forEach(radio => {
    radio.addEventListener('change', fetchBestNode);
});

async function fetchBestNode() {
    try {
        const method = document.querySelector('input[name="method"]:checked').value;
        const res = await fetch(`/api/best-node?method=${method}`);
        const data = await res.json();
        const el = document.getElementById('preview-node');
        el.classList.remove('hidden');
        el.textContent = `Nœud sélectionné automatiquement : ${data.node} (méthode : ${data.method})`;
        loadTemplates(document.querySelector('input[name="type"]:checked').value, data.node);
    } catch (e) {
        console.error(e);
    }
}

async function loadTemplates(type, node = null) {
    const url = `/api/templates?type=${type}${node ? `&node=${node}` : ''}`;
    try {
        const res = await fetch(url);
        const data = await res.json();
        const selectId = type === 'ct' ? 'template-select-ct' : 'template-select-vm';
        const select = document.getElementById(selectId);
        const defaultLabel = type === 'ct' ? '— Choisir un template —' : '— Aucun (disque vierge) —';
        select.innerHTML = `<option value="">${defaultLabel}</option>`;
        (data.templates || []).forEach(t => {
            const volid = t.volid || t.vmid || t.name;
            const label = t.name || t.volid || `VM ${t.vmid}`;
            select.innerHTML += `<option value="${volid}">${label}</option>`;
        });
    } catch (e) {
        console.error(e);
    }
}

fetchBestNode();

@if(session('job_id'))
const jobId = {{ session('job_id') }};
const tracker = document.getElementById('job-tracker');
const bar = document.getElementById('job-progress-bar');
const msg = document.getElementById('job-message');
tracker.classList.remove('hidden');

const poll = setInterval(async () => {
    const res = await fetch(`/api/jobs/${jobId}`);
    const job = await res.json();
    bar.style.width = `${job.progress}%`;
    msg.textContent = job.message || job.status;
    if (job.status === 'done' || job.status === 'error') clearInterval(poll);
}, 2000);
@endif
</script>
</body>
</html>
