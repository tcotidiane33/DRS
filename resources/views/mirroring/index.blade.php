<!DOCTYPE html>
<html lang="fr" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ceph RBD Mirroring Administration</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
    <style>
        .glass-panel {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .animate-fade-in {
            animation: fadeIn 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        /* Log console */
        #log-console {
            font-family: 'Fira Code', 'Cascadia Code', 'JetBrains Mono', monospace;
            font-size: 0.78rem;
            line-height: 1.6;
        }
        .log-line-info    { color: #93c5fd; }
        .log-line-success { color: #6ee7b7; }
        .log-line-error   { color: #fca5a5; }
        .log-line-warn    { color: #fde68a; }
        .log-line-default { color: #cbd5e1; }
        .log-line-sep     { color: #475569; }
        .log-line-drs     { color: #a78bfa; font-weight: 600; }
        .cursor-blink::after {
            content: '▌';
            animation: blink 1s step-start infinite;
            color: #6ee7b7;
        }
        @keyframes blink {
            50% { opacity: 0; }
        }
        .status-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 2px 10px; border-radius: 9999px; font-size: 0.7rem; font-weight: 600;
        }
    </style>
</head>
<body class="bg-slate-900 text-slate-200 min-h-screen font-sans antialiased overflow-x-hidden relative">

    <!-- Background Accents -->
    <div class="absolute top-0 -left-1/4 w-1/2 h-1/2 bg-indigo-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-30 animate-pulse"></div>
    <div class="absolute bottom-0 -right-1/4 w-1/2 h-1/2 bg-emerald-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-20"></div>

    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">

        <header class="mb-10 animate-fade-in text-center">
            <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 to-cyan-400 mb-4 drop-shadow-sm">
                Ceph RBD Mirroring
            </h1>
            <p class="text-lg text-slate-400 max-w-2xl mx-auto">
                Administration de la réplication site-à-site Proxmox — Setup, Failover & Logs en temps réel.
            </p>
        </header>

        <!-- ====================== FORMS ROW ====================== -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-10">

            <!-- Setup Form -->
            <section class="glass-panel rounded-2xl shadow-2xl p-8 animate-fade-in" style="animation-delay: 0.1s;">
                <div class="flex items-center gap-3 mb-6 border-b border-slate-700/50 pb-4">
                    <div class="p-2 bg-indigo-500/20 rounded-lg text-indigo-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <h2 class="text-2xl font-bold text-white">Automated Setup</h2>
                </div>

                <form id="form-setup" class="space-y-5">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-slate-300">Site A Node (IP/Host)</label>
                            <input type="text" name="site_a" required class="w-full bg-slate-800/50 border border-slate-700 text-white rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all placeholder-slate-500" placeholder="ex. 54.38.146.218">
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-slate-300">Site B Node (IP/Host)</label>
                            <input type="text" name="site_b" required class="w-full bg-slate-800/50 border border-slate-700 text-white rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all placeholder-slate-500" placeholder="ex. 54.38.146.211">
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-slate-300">Ceph Pool Name</label>
                        <input type="text" name="pool" required class="w-full bg-slate-800/50 border border-slate-700 text-white rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all placeholder-slate-500" placeholder="ex. cephrbd">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-slate-300">Mode</label>
                            <select name="mode" class="w-full bg-slate-800/50 border border-slate-700 text-white rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all">
                                <option value="snapshot">Snapshot (Recommandé)</option>
                                <option value="journal">Journal</option>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-slate-300">Image (optionnel)</label>
                            <input type="text" name="image" class="w-full bg-slate-800/50 border border-slate-700 text-white rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all placeholder-slate-500" placeholder="ex. vm-100-disk-0">
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-slate-300">Schedule Snapshot (optionnel)</label>
                        <input type="text" name="schedule" class="w-full bg-slate-800/50 border border-slate-700 text-white rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all placeholder-slate-500" placeholder="ex. 5m, 1h">
                        <p class="text-xs text-slate-500">Uniquement en mode Snapshot.</p>
                    </div>

                    <button type="submit" id="btn-setup" class="w-full mt-4 bg-gradient-to-r from-indigo-600 to-indigo-500 hover:from-indigo-500 hover:to-indigo-400 text-white font-semibold py-3 px-6 rounded-lg shadow-lg shadow-indigo-500/30 transform transition-all hover:-translate-y-0.5 active:translate-y-0 flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        Initialiser le Mirroring
                    </button>
                </form>
            </section>

            <!-- Failover / Recovery -->
            <section class="glass-panel rounded-2xl shadow-2xl p-8 animate-fade-in flex flex-col" style="animation-delay: 0.2s;">
                <div class="flex items-center gap-3 mb-6 border-b border-slate-700/50 pb-4">
                    <div class="p-2 bg-emerald-500/20 rounded-lg text-emerald-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    </div>
                    <h2 class="text-2xl font-bold text-white">Failover & Recovery</h2>
                </div>

                <p class="text-slate-400 mb-6 text-sm leading-relaxed">
                    Pour un basculement planifié : Demote sur Site A, puis Promote sur Site B. En cas de disaster : Force Promote.
                </p>

                <form id="form-failover" class="space-y-5 flex-grow">
                    @csrf
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-slate-300">Target Node (IP/Host)</label>
                        <input type="text" name="node" required class="w-full bg-slate-800/50 border border-slate-700 text-white rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-emerald-500 transition-all placeholder-slate-500" placeholder="ex. 54.38.146.211">
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-slate-300">Ceph Pool</label>
                        <input type="text" name="pool" required class="w-full bg-slate-800/50 border border-slate-700 text-white rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-emerald-500 transition-all placeholder-slate-500" placeholder="ex. cephrbd">
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-slate-300">Image (optionnel)</label>
                        <input type="text" name="image" class="w-full bg-slate-800/50 border border-slate-700 text-white rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-emerald-500 transition-all placeholder-slate-500" placeholder="Laisser vide = pool entier">
                    </div>

                    <div class="grid grid-cols-2 gap-4 pt-4">
                        <button type="submit" name="action-fo" data-action="demote"
                            class="w-full bg-slate-700 hover:bg-slate-600 border border-slate-600 text-white font-medium py-3 px-4 rounded-lg transition-colors flex justify-center items-center gap-2">
                            <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>
                            Demote
                        </button>
                        <button type="submit" name="action-fo" data-action="promote"
                            class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-medium py-3 px-4 rounded-lg shadow-lg shadow-emerald-500/20 transition-colors flex justify-center items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
                            Promote
                        </button>
                    </div>

                    <div class="mt-4 pt-4 border-t border-rose-500/20">
                        <label class="flex items-center gap-3 p-3 bg-rose-500/10 border border-rose-500/30 rounded-lg cursor-pointer hover:bg-rose-500/20 transition-colors">
                            <input type="checkbox" name="force" value="1" class="w-5 h-5 text-rose-600 rounded bg-slate-800 border-rose-500/50">
                            <span class="text-rose-400 font-medium text-sm">Force Action (Disaster Recovery uniquement)</span>
                        </label>
                    </div>
                </form>
            </section>
        </div>

        <!-- ====================== LIVE LOG CONSOLE ====================== -->
        <section class="glass-panel rounded-2xl shadow-2xl animate-fade-in" style="animation-delay: 0.3s;">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700/50">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-cyan-500/20 rounded-lg text-cyan-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                    <h2 class="text-xl font-bold text-white">Logs — Console en temps réel</h2>
                </div>
                <div class="flex items-center gap-3">
                    <span id="log-status-badge" class="status-badge bg-slate-700 text-slate-400">En attente</span>
                    <button id="btn-clear-console" onclick="clearConsole()" class="text-xs text-slate-500 hover:text-slate-300 transition-colors px-3 py-1 border border-slate-700 rounded-md">Effacer</button>
                </div>
            </div>

            <!-- Mac-style terminal chrome -->
            <div class="bg-slate-950/80 rounded-b-2xl overflow-hidden">
                <div class="flex items-center gap-1.5 px-4 py-2.5 bg-slate-800/60 border-b border-slate-700/50">
                    <div class="w-3 h-3 rounded-full bg-red-500/80"></div>
                    <div class="w-3 h-3 rounded-full bg-amber-400/80"></div>
                    <div class="w-3 h-3 rounded-full bg-emerald-500/80"></div>
                    <span class="ml-3 text-xs text-slate-500 font-mono">DRS — Ceph Mirror Output</span>
                </div>
                <div id="log-console" class="h-96 overflow-y-auto px-5 py-4 space-y-0.5" id="log-console">
                    <p class="text-slate-600 italic text-xs">Lancez une commande pour voir les logs apparaître ici...</p>
                </div>
            </div>

            <!-- History Panel -->
            <div class="px-6 py-4 border-t border-slate-700/50">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wide">Historique des exécutions</h3>
                    <button onclick="loadLogHistory()" class="text-xs text-indigo-400 hover:text-indigo-300 transition-colors">↻ Actualiser</button>
                </div>
                <div id="log-history" class="space-y-2 max-h-52 overflow-y-auto pr-1">
                    @foreach($logs as $log)
                    <div class="flex items-center justify-between bg-slate-800/50 rounded-lg px-4 py-2.5 hover:bg-slate-700/50 transition-colors cursor-pointer" onclick="reloadLog('{{ $log['log_id'] }}')">
                        <div class="flex items-center gap-3">
                            <span class="status-badge {{ $log['status'] === 'success' ? 'bg-emerald-500/20 text-emerald-400' : ($log['status'] === 'error' ? 'bg-rose-500/20 text-rose-400' : 'bg-amber-500/20 text-amber-400') }}">
                                {{ $log['status'] === 'running' ? '⟳ Running' : ($log['status'] === 'success' ? '✓ OK' : '✗ Erreur') }}
                            </span>
                            <span class="text-sm font-medium text-slate-300">{{ $log['label'] }}</span>
                            <span class="text-xs font-mono text-slate-500">{{ $log['log_id'] }}</span>
                        </div>
                        <span class="text-xs text-slate-500 shrink-0">{{ $log['date'] }}</span>
                    </div>
                    @endforeach
                    @if(empty($logs))
                        <p class="text-slate-600 text-sm italic text-center py-4">Aucun log disponible.</p>
                    @endif
                </div>
            </div>
        </section>

    </div>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
let activeEventSource = null;

// ── Colorize a log line ───────────────────────────────────────────────
function colorize(line) {
    const el = document.createElement('p');
    el.textContent = line;
    if (line.startsWith('[DRS]'))              el.className = 'log-line-drs';
    else if (/\[INFO\]/i.test(line))           el.className = 'log-line-info';
    else if (/\[SUCCESS\]|✓/i.test(line))     el.className = 'log-line-success';
    else if (/\[ERROR\]|failed|error/i.test(line)) el.className = 'log-line-error';
    else if (/\[WARN\]|warning/i.test(line))  el.className = 'log-line-warn';
    else if (/^─+$/.test(line.trim()))        el.className = 'log-line-sep';
    else                                       el.className = 'log-line-default';
    return el;
}

// ── Append a line to the console ─────────────────────────────────────
function appendLine(line) {
    const console = document.getElementById('log-console');
    // Remove placeholder
    const placeholder = console.querySelector('p.italic');
    if (placeholder) placeholder.remove();
    console.appendChild(colorize(line));
    console.scrollTop = console.scrollHeight;
}

// ── Clear console ────────────────────────────────────────────────────
function clearConsole() {
    const console = document.getElementById('log-console');
    console.innerHTML = '<p class="text-slate-600 italic text-xs">Console effacée.</p>';
    setStatusBadge('idle');
    if (activeEventSource) { activeEventSource.close(); activeEventSource = null; }
}

// ── Status badge ─────────────────────────────────────────────────────
function setStatusBadge(state) {
    const badge = document.getElementById('log-status-badge');
    const classes = {
        idle:    'bg-slate-700 text-slate-400',
        running: 'bg-amber-500/20 text-amber-400',
        success: 'bg-emerald-500/20 text-emerald-400',
        error:   'bg-rose-500/20 text-rose-400',
    };
    const labels = { idle: 'En attente', running: '⟳ En cours…', success: '✓ Succès', error: '✗ Erreur' };
    badge.className = `status-badge ${classes[state] ?? classes.idle}`;
    badge.textContent = labels[state] ?? state;
}

// ── Start streaming SSE for a logId ──────────────────────────────────
function streamLog(logId) {
    if (activeEventSource) activeEventSource.close();
    setStatusBadge('running');
    appendLine(`[DRS] Connexion au flux de logs : ${logId}`);

    const es = new EventSource(`/mirroring/logs/${logId}/stream`);
    activeEventSource = es;

    es.onmessage = (e) => {
        const data = JSON.parse(e.data);
        if (data.line) {
            appendLine(data.line);
        }
        if (data.done) {
            es.close();
            activeEventSource = null;
            setStatusBadge(data.status === 'success' ? 'success' : 'error');
            appendLine(`[DRS] ── Processus terminé (${data.status}) ──`);
            loadLogHistory();
        }
    };

    es.onerror = () => {
        es.close();
        setStatusBadge('error');
        appendLine('[DRS] Connexion SSE interrompue.');
    };
}

// ── Reload a past log ────────────────────────────────────────────────
async function reloadLog(logId) {
    clearConsole();
    appendLine(`[DRS] Chargement du log : ${logId}`);
    const res  = await fetch(`/mirroring/logs/${logId}`);
    const data = await res.json();
    if (data.error) { appendLine('[DRS] Erreur : ' + data.error); return; }
    data.content.split('\n').forEach(line => { if (line) appendLine(line); });
    setStatusBadge(data.status === 'success' ? 'success' : data.status === 'running' ? 'running' : 'error');
    if (data.status === 'running') streamLog(logId);
}

// ── Reload log history list ──────────────────────────────────────────
async function loadLogHistory() {
    const res  = await fetch('/mirroring/logs');
    const logs = await res.json();
    const el   = document.getElementById('log-history');
    if (logs.length === 0) {
        el.innerHTML = '<p class="text-slate-600 text-sm italic text-center py-4">Aucun log disponible.</p>';
        return;
    }
    el.innerHTML = logs.map(log => {
        const badgeCls = log.status === 'success'
            ? 'bg-emerald-500/20 text-emerald-400'
            : log.status === 'error'
                ? 'bg-rose-500/20 text-rose-400'
                : 'bg-amber-500/20 text-amber-400';
        const badgeTxt = log.status === 'running' ? '⟳ Running' : (log.status === 'success' ? '✓ OK' : '✗ Erreur');
        return `<div class="flex items-center justify-between bg-slate-800/50 rounded-lg px-4 py-2.5 hover:bg-slate-700/50 transition-colors cursor-pointer" onclick="reloadLog('${log.log_id}')">
            <div class="flex items-center gap-3">
                <span class="status-badge ${badgeCls}">${badgeTxt}</span>
                <span class="text-sm font-medium text-slate-300">${log.label}</span>
                <span class="text-xs font-mono text-slate-500">${log.log_id}</span>
            </div>
            <span class="text-xs text-slate-500 shrink-0">${log.date}</span>
        </div>`;
    }).join('');
}

// ── Generic form submit → POST JSON → stream logs ────────────────────
async function submitForm(formId, endpoint) {
    const form = document.getElementById(formId);
    const formData = new FormData(form);
    const body = {};
    formData.forEach((v, k) => { body[k] = v; });

    clearConsole();
    setStatusBadge('running');
    appendLine('[DRS] Envoi de la commande au serveur…');

    try {
        const res  = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify(body),
        });
        const data = await res.json();

        if (!res.ok) {
            const msg = data.message ?? JSON.stringify(data);
            appendLine('[DRS] Erreur serveur : ' + msg);
            if (data.errors) {
                Object.entries(data.errors).forEach(([k, v]) => appendLine(`  → ${k}: ${v}`));
            }
            setStatusBadge('error');
            return;
        }

        appendLine('[DRS] ' + data.message);
        streamLog(data.log_id);
    } catch (err) {
        appendLine('[DRS] Erreur réseau : ' + err.message);
        setStatusBadge('error');
    }
}

// ── Setup form submit ────────────────────────────────────────────────
document.getElementById('form-setup').addEventListener('submit', e => {
    e.preventDefault();
    submitForm('form-setup', '/mirroring/setup');
});

// ── Failover form submit (two buttons) ───────────────────────────────
document.getElementById('form-failover').addEventListener('submit', e => {
    e.preventDefault();
});
document.querySelectorAll('[data-action]').forEach(btn => {
    btn.addEventListener('click', () => {
        const action = btn.getAttribute('data-action');
        const form = document.getElementById('form-failover');
        const formData = new FormData(form);
        const body = {};
        formData.forEach((v, k) => { body[k] = v; });
        body['action'] = action;

        clearConsole();
        setStatusBadge('running');
        appendLine(`[DRS] Action '${action}' envoyée…`);

        fetch('/mirroring/failover', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify(body),
        })
        .then(r => r.json())
        .then(data => {
            appendLine('[DRS] ' + data.message);
            streamLog(data.log_id);
        })
        .catch(err => {
            appendLine('[DRS] Erreur réseau : ' + err.message);
            setStatusBadge('error');
        });
    });
});
</script>

</body>
</html>
