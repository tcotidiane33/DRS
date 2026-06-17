<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DRS — Tableau de bord</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">

@include('vms.partials.nav')

<div class="max-w-6xl mx-auto p-8">

    <h1 class="text-2xl font-bold text-gray-800 mb-6">Tableau de bord Proxmox</h1>

    @if($proxmoxError ?? false)
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            Connexion Proxmox impossible : {{ $proxmoxError }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        @forelse($nodes as $n)
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
        @empty
        <p class="text-gray-500 col-span-3">Aucun nœud disponible.</p>
        @endforelse
    </div>

    <h2 class="text-lg font-semibold text-gray-700 mb-4">Jobs récents</h2>
    <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600">
                <tr>
                    <th class="text-left px-4 py-3">#</th>
                    <th class="text-left px-4 py-3">Nom</th>
                    <th class="text-left px-4 py-3">Type</th>
                    <th class="text-left px-4 py-3">Nœud</th>
                    <th class="text-left px-4 py-3">VMID</th>
                    <th class="text-left px-4 py-3">Statut</th>
                    <th class="text-left px-4 py-3">Progression</th>
                </tr>
            </thead>
            <tbody>
                @forelse($jobs as $job)
                <tr class="border-t" data-job-id="{{ $job->id }}">
                    <td class="px-4 py-3">{{ $job->id }}</td>
                    <td class="px-4 py-3 font-medium">{{ $job->name }}</td>
                    <td class="px-4 py-3">{{ strtoupper($job->type) }}</td>
                    <td class="px-4 py-3">{{ $job->node ?? '—' }}</td>
                    <td class="px-4 py-3">{{ $job->vmid ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <span class="job-status px-2 py-1 rounded text-xs
                            @if($job->status === 'done') bg-green-100 text-green-700
                            @elseif($job->status === 'error') bg-red-100 text-red-700
                            @elseif($job->status === 'running') bg-blue-100 text-blue-700
                            @else bg-gray-100 text-gray-700 @endif">
                            {{ $job->status }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <div class="w-24 bg-gray-200 rounded h-2">
                                <div class="job-progress h-2 rounded bg-blue-500" style="width: {{ $job->progress }}%"></div>
                            </div>
                            <span class="job-pct text-xs text-gray-500">{{ $job->progress }}%</span>
                        </div>
                        <p class="job-message text-xs text-gray-400 mt-1">{{ $job->message }}</p>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-gray-400">Aucun job pour le moment.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
const activeJobs = document.querySelectorAll('[data-job-id]');
if (activeJobs.length) {
    setInterval(async () => {
        for (const row of activeJobs) {
            const id = row.dataset.jobId;
            const status = row.querySelector('.job-status')?.textContent.trim();
            if (status === 'done' || status === 'error') continue;

            const res = await fetch(`/api/jobs/${id}`);
            if (!res.ok) continue;
            const job = await res.json();

            row.querySelector('.job-status').textContent = job.status;
            row.querySelector('.job-progress').style.width = `${job.progress}%`;
            row.querySelector('.job-pct').textContent = `${job.progress}%`;
            if (job.message) row.querySelector('.job-message').textContent = job.message;
        }
    }, 2000);
}
</script>
</body>
</html>
