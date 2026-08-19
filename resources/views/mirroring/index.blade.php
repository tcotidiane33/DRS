<!DOCTYPE html>
<html lang="fr" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ceph RBD Mirroring &amp; Clés SSH — DRS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
    <style>
        .glass-panel {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .animate-fade-in { animation: fadeIn 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
        #log-console { font-family: 'Fira Code', 'JetBrains Mono', monospace; font-size: 0.78rem; line-height: 1.65; }
        .log-info    { color: #93c5fd; }
        .log-success { color: #6ee7b7; }
        .log-error   { color: #fca5a5; }
        .log-warn    { color: #fde68a; }
        .log-sep     { color: #475569; }
        .log-drs     { color: #a78bfa; font-weight: 600; }
        .log-default { color: #cbd5e1; }
        .tab-active  { border-bottom: 2px solid #818cf8; color: #e0e7ff; }
        .tab-inactive{ border-bottom: 2px solid transparent; color: #64748b; }
        .step-idle     { border-color: #334155; }
        .step-running  { border-color: #f59e0b; box-shadow: 0 0 0 1px #f59e0b44; }
        .step-success  { border-color: #10b981; box-shadow: 0 0 0 1px #10b98144; }
        .step-error    { border-color: #ef4444; box-shadow: 0 0 0 1px #ef444444; }
        .badge { display:inline-flex; align-items:center; gap:4px; padding:2px 10px; border-radius:9999px; font-size:0.72rem; font-weight:600; }
        .node-card { transition: all 0.2s ease; }
        .node-card:hover { border-color: #4f46e5; }
    </style>
</head>
<body class="bg-slate-900 text-slate-200 min-h-screen font-sans antialiased overflow-x-hidden relative">

<div class="absolute top-0 -left-1/4 w-1/2 h-1/2 bg-indigo-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-25 animate-pulse pointer-events-none"></div>
<div class="absolute bottom-0 -right-1/4 w-1/2 h-1/2 bg-emerald-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-15 pointer-events-none"></div>

<div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

    <header class="mb-8 text-center animate-fade-in">
        <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 to-cyan-400 mb-3">
            Ceph RBD Mirroring &amp; DRS
        </h1>
        <p class="text-slate-400 max-w-2xl mx-auto">Administration de la réplication site-à-site Proxmox — Gestion des Clés SSH, All-in-one, Wizard &amp; API REST.</p>
    </header>

    <!-- ═══════════ TABS ═══════════ -->
    <div class="flex gap-4 sm:gap-6 border-b border-slate-700/60 mb-8 px-1 overflow-x-auto">
        <button id="tab-ssh"      onclick="switchTab('ssh')"      class="tab-active pb-2 text-sm font-semibold transition-colors flex items-center gap-2 shrink-0">🔑 Clés SSH &amp; Nœuds</button>
        <button id="tab-allinone" onclick="switchTab('allinone')" class="tab-inactive pb-2 text-sm font-semibold transition-colors flex items-center gap-2 shrink-0">🚀 All-in-one</button>
        <button id="tab-wizard"   onclick="switchTab('wizard')"   class="tab-inactive pb-2 text-sm font-semibold transition-colors flex items-center gap-2 shrink-0">🧙 Wizard Step-by-step</button>
        <button id="tab-failover" onclick="switchTab('failover')" class="tab-inactive pb-2 text-sm font-semibold transition-colors flex items-center gap-2 shrink-0">⚡ Failover</button>
        <button id="tab-api"      onclick="switchTab('api')"      class="tab-inactive pb-2 text-sm font-semibold transition-colors flex items-center gap-2 shrink-0">📡 Référence API</button>
    </div>

    <!-- ═══════════ TAB: SSH KEYS & NODE MATRIX (NEW) ═══════════ -->
    <div id="panel-ssh" class="animate-fade-in space-y-8">
        
        <!-- Public Key Display Card -->
        <section class="glass-panel rounded-2xl shadow-2xl p-6 sm:p-8">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4 pb-4 border-b border-slate-700/50">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 bg-amber-500/20 rounded-xl text-amber-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-white">Clé Publique SSH du Serveur DRS</h2>
                        <p class="text-xs text-slate-400" id="ssh-key-path">Emplacement : ~/.ssh/id_ed25519.pub</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="copySshKey()" id="btn-copy-key" class="btn-secondary text-xs py-2 px-4 flex items-center gap-1.5 hover:border-indigo-500 hover:text-white">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                        <span>Copier la clé publique</span>
                    </button>
                    <button onclick="loadSshKey()" title="Recharger" class="p-2 bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-400 hover:text-white rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    </button>
                </div>
            </div>

            <p class="text-slate-400 text-sm mb-3">
                Pour exécuter les scripts sans mot de passe, cette clé doit être autorisée sur chaque nœud Proxmox. Vous pouvez utiliser le bouton <strong class="text-indigo-400">« Autoriser en 1 clic »</strong> ci-dessous pour l'injecter automatiquement via le mot de passe root.
            </p>

            <div class="relative">
                <textarea id="ssh-public-key-text" readonly rows="2" class="w-full bg-slate-950/80 border border-slate-700/80 text-emerald-400 font-mono text-xs rounded-xl p-3.5 select-all focus:outline-none focus:border-indigo-500 transition-colors">Chargement de la clé publique...</textarea>
            </div>
        </section>

        <!-- Node Connectivity Matrix -->
        <section class="glass-panel rounded-2xl shadow-2xl p-6 sm:p-8">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-4 border-b border-slate-700/50">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 bg-indigo-500/20 rounded-xl text-indigo-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-white">Matrice d'Autorisation &amp; Connectivité SSH</h2>
                        <p class="text-xs text-slate-400">Vérifiez l'accès sans mot de passe et autorisez la clé en 1 clic sur chaque nœud Proxmox.</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button onclick="testAllNodes()" id="btn-test-all" class="btn-primary py-2 px-4 text-xs font-semibold flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        <span>Tester tous les nœuds</span>
                    </button>
                </div>
            </div>

            <!-- Site A Nodes Section -->
            <div class="mb-6">
                <h3 class="text-sm font-bold text-indigo-300 uppercase tracking-wider mb-3 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-indigo-400"></span> Site A — Source Cluster
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4" id="site-a-nodes-grid">
                    <!-- Cards will be populated by JS or static fallback -->
                </div>
            </div>

            <!-- Site B Nodes Section -->
            <div class="mb-6">
                <h3 class="text-sm font-bold text-emerald-300 uppercase tracking-wider mb-3 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span> Site B — Target Cluster
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4" id="site-b-nodes-grid">
                    <!-- Cards will be populated by JS -->
                </div>
            </div>

            <!-- Custom IP Section -->
            <div class="pt-4 border-t border-slate-700/50">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Ajouter un autre nœud personnalisé</h3>
                <div class="flex flex-col sm:flex-row gap-3">
                    <input type="text" id="custom-node-ip" placeholder="ex: 192.168.10.50" class="field flex-1 text-sm" />
                    <button onclick="addCustomNode()" class="btn-secondary text-xs px-5 py-2 whitespace-nowrap">
                        + Ajouter à la liste
                    </button>
                </div>
                <div id="custom-nodes-grid" class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3"></div>
            </div>
        </section>

    </div>

    <!-- ═══════════ TAB: ALL-IN-ONE ═══════════ -->
    <div id="panel-allinone" class="hidden animate-fade-in">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <section class="glass-panel rounded-2xl shadow-2xl p-8">
                <div class="flex items-center gap-3 mb-6 border-b border-slate-700/50 pb-4">
                    <div class="p-2 bg-indigo-500/20 rounded-lg text-indigo-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <h2 class="text-xl font-bold text-white">Automated Setup (script complet)</h2>
                </div>
                <form id="form-setup" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="label">Site A Node</label><input type="text" name="site_a" id="aio-site-a" required class="field" placeholder="54.38.146.218"></div>
                        <div><label class="label">Site B Node</label><input type="text" name="site_b" id="aio-site-b" required class="field" placeholder="54.38.146.211"></div>
                    </div>
                    <div><label class="label">Pool</label><input type="text" name="pool" required class="field" placeholder="cephrbd"></div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label">Mode</label>
                            <select name="mode" class="field">
                                <option value="snapshot">Snapshot (Recommandé)</option>
                                <option value="journal">Journal</option>
                            </select>
                        </div>
                        <div><label class="label">Image (optionnel)</label><input type="text" name="image" class="field" placeholder="vm-100-disk-0"></div>
                    </div>
                    <div><label class="label">Schedule (optionnel)</label><input type="text" name="schedule" class="field" placeholder="5m, 1h"></div>
                    <button type="submit" class="w-full mt-2 btn-primary">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        Lancer le script complet
                    </button>
                </form>
            </section>

            <section class="glass-panel rounded-2xl shadow-2xl p-8">
                <div class="flex items-center gap-3 mb-4 border-b border-slate-700/50 pb-4">
                    <div class="p-2 bg-cyan-500/20 rounded-lg text-cyan-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                    <h2 class="text-xl font-bold text-white">Statut du Pool</h2>
                </div>
                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="label">Nœud</label><input type="text" id="status-node" class="field" placeholder="54.38.146.211"></div>
                        <div><label class="label">Pool</label><input type="text" id="status-pool" class="field" placeholder="cephrbd"></div>
                    </div>
                    <button onclick="fetchStatus()" class="w-full btn-secondary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Actualiser le statut
                    </button>
                    <div id="status-output" class="bg-slate-950/60 rounded-xl p-4 text-xs font-mono text-slate-400 min-h-24 max-h-64 overflow-y-auto">Cliquez pour charger le statut...</div>
                </div>
            </section>
        </div>
    </div>

    <!-- ═══════════ TAB: WIZARD ═══════════ -->
    <div id="panel-wizard" class="hidden animate-fade-in">
        <div class="glass-panel rounded-2xl shadow-2xl p-8 mb-8">
            <h2 class="text-xl font-bold text-white mb-2">Configuration Globale du Wizard</h2>
            <p class="text-slate-400 text-sm mb-5">Ces paramètres seront utilisés par toutes les étapes. Remplissez-les une seule fois.</p>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <div><label class="label">Site A Node</label><input type="text" id="wz-site-a" class="field" placeholder="54.38.146.218"></div>
                <div><label class="label">Site B Node</label><input type="text" id="wz-site-b" class="field" placeholder="54.38.146.211"></div>
                <div><label class="label">Pool</label><input type="text" id="wz-pool" class="field" placeholder="cephrbd"></div>
                <div><label class="label">Image (optionnel)</label><input type="text" id="wz-image" class="field" placeholder="vm-100-disk-0"></div>
                <div>
                    <label class="label">Mode</label>
                    <select id="wz-mode" class="field">
                        <option value="snapshot">Snapshot</option>
                        <option value="journal">Journal</option>
                    </select>
                </div>
                <div><label class="label">Schedule (optionnel)</label><input type="text" id="wz-schedule" class="field" placeholder="5m"></div>
            </div>
        </div>

        <div class="space-y-4">
            <!-- Step 1 -->
            <div id="step-1" class="glass-panel step-idle border rounded-2xl p-5 transition-all">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-full bg-slate-800 border-2 border-slate-600 flex items-center justify-center text-sm font-bold text-slate-300">1</div>
                        <div>
                            <p class="font-semibold text-white">Créer l'utilisateur Ceph sur Site A</p>
                            <p class="text-xs text-slate-500 mt-0.5">ceph auth get-or-create client.rbd-mirror-peer-a</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span id="step-1-badge" class="badge bg-slate-700/60 text-slate-400">En attente</span>
                        <button onclick="runStep(1)" class="btn-step">▶ Lancer</button>
                    </div>
                </div>
            </div>

            <!-- Step 2 -->
            <div id="step-2" class="glass-panel step-idle border rounded-2xl p-5 transition-all">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-full bg-slate-800 border-2 border-slate-600 flex items-center justify-center text-sm font-bold text-slate-300">2</div>
                        <div>
                            <p class="font-semibold text-white">Transférer le Keyring A → B</p>
                            <p class="text-xs text-slate-500 mt-0.5">scp keyring + ceph.conf de Site A vers Site B</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span id="step-2-badge" class="badge bg-slate-700/60 text-slate-400">En attente</span>
                        <button onclick="runStep(2)" class="btn-step">▶ Lancer</button>
                    </div>
                </div>
            </div>

            <!-- Step 3 -->
            <div id="step-3" class="glass-panel step-idle border rounded-2xl p-5 transition-all">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-full bg-slate-800 border-2 border-slate-600 flex items-center justify-center text-sm font-bold text-slate-300">3</div>
                        <div>
                            <p class="font-semibold text-white">Configurer Site B (symlink + user local)</p>
                            <p class="text-xs text-slate-500 mt-0.5">ln -sf site-a.conf + ceph auth get-or-create rbd-mirror</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span id="step-3-badge" class="badge bg-slate-700/60 text-slate-400">En attente</span>
                        <button onclick="runStep(3)" class="btn-step">▶ Lancer</button>
                    </div>
                </div>
            </div>

            <!-- Step 4 -->
            <div id="step-4" class="glass-panel step-idle border rounded-2xl p-5 transition-all">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-full bg-slate-800 border-2 border-slate-600 flex items-center justify-center text-sm font-bold text-slate-300">4</div>
                        <div>
                            <p class="font-semibold text-white">Activer le mirroring du pool (A &amp; B)</p>
                            <p class="text-xs text-slate-500 mt-0.5">rbd mirror pool enable &lt;pool&gt; image</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span id="step-4-badge" class="badge bg-slate-700/60 text-slate-400">En attente</span>
                        <button onclick="runStep(4)" class="btn-step">▶ Lancer</button>
                    </div>
                </div>
            </div>

            <!-- Step 5 -->
            <div id="step-5" class="glass-panel step-idle border rounded-2xl p-5 transition-all">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-full bg-slate-800 border-2 border-slate-600 flex items-center justify-center text-sm font-bold text-slate-300">5</div>
                        <div>
                            <p class="font-semibold text-white">Configurer le Peer sur Site B</p>
                            <p class="text-xs text-slate-500 mt-0.5">rbd mirror pool peer add client.rbd-mirror-peer-a@site-a</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span id="step-5-badge" class="badge bg-slate-700/60 text-slate-400">En attente</span>
                        <button onclick="runStep(5)" class="btn-step">▶ Lancer</button>
                    </div>
                </div>
            </div>

            <!-- Step 6 -->
            <div id="step-6" class="glass-panel step-idle border rounded-2xl p-5 transition-all">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-full bg-slate-800 border-2 border-slate-600 flex items-center justify-center text-sm font-bold text-slate-300">6</div>
                        <div>
                            <p class="font-semibold text-white">Installer &amp; Démarrer le daemon rbd-mirror</p>
                            <p class="text-xs text-slate-500 mt-0.5">apt-get install rbd-mirror + systemctl enable --now</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span id="step-6-badge" class="badge bg-slate-700/60 text-slate-400">En attente</span>
                        <button onclick="runStep(6)" class="btn-step">▶ Lancer</button>
                    </div>
                </div>
            </div>

            <!-- Step 7 -->
            <div id="step-7" class="glass-panel step-idle border rounded-2xl p-5 transition-all">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-full bg-slate-800 border-2 border-slate-600 flex items-center justify-center text-sm font-bold text-slate-300">7</div>
                        <div>
                            <p class="font-semibold text-white">Activer le mirroring de l'image + Schedule</p>
                            <p class="text-xs text-slate-500 mt-0.5">rbd mirror image enable + snapshot schedule add</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span id="step-7-badge" class="badge bg-slate-700/60 text-slate-400">En attente</span>
                        <button onclick="runStep(7)" class="btn-step" id="btn-step-7">▶ Lancer</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════ TAB: FAILOVER ═══════════ -->
    <div id="panel-failover" class="hidden animate-fade-in">
        <div class="glass-panel rounded-2xl shadow-2xl p-8 mb-8">
            <div class="flex items-center gap-3 mb-6 border-b border-slate-700/50 pb-4">
                <div class="p-2 bg-rose-500/20 rounded-lg text-rose-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <h2 class="text-xl font-bold text-white">Failover &amp; Recovery — Step by Step</h2>
            </div>
            <p class="text-slate-400 text-sm mb-6 leading-relaxed">
                <strong class="text-amber-400">Basculement planifié :</strong> Demote Site A → Promote Site B.<br>
                <strong class="text-rose-400">Disaster recovery :</strong> Force Promote Site B directement.
            </p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div><label class="label">Nœud cible</label><input type="text" id="fo-node" class="field" placeholder="54.38.146.211"></div>
                <div><label class="label">Pool</label><input type="text" id="fo-pool" class="field" placeholder="cephrbd"></div>
                <div><label class="label">Image (optionnel)</label><input type="text" id="fo-image" class="field" placeholder="vm-100-disk-0"></div>
            </div>
            <div class="flex items-center gap-3 mb-6">
                <label class="flex items-center gap-2 cursor-pointer bg-rose-500/10 border border-rose-500/30 px-4 py-2 rounded-lg hover:bg-rose-500/20 transition-colors">
                    <input type="checkbox" id="fo-force" class="w-4 h-4 rounded text-rose-500 bg-slate-800 border-rose-500">
                    <span class="text-rose-400 text-sm font-medium">Force (Disaster Recovery)</span>
                </label>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <button onclick="runFailoverStep('demote')" class="w-full bg-amber-600/20 hover:bg-amber-600/40 border border-amber-500/40 text-amber-300 font-semibold py-3 rounded-xl transition-colors flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                    Demote
                </button>
                <button onclick="runFailoverStep('promote')" class="w-full bg-emerald-600/20 hover:bg-emerald-600/40 border border-emerald-500/40 text-emerald-300 font-semibold py-3 rounded-xl transition-colors flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                    Promote
                </button>
            </div>
        </div>
    </div>

    <!-- ═══════════ TAB: API REFERENCE ═══════════ -->
    <div id="panel-api" class="hidden animate-fade-in">
        <div class="glass-panel rounded-2xl shadow-2xl p-8 mb-8">
            <h2 class="text-xl font-bold text-white mb-2">Référence API REST — Step-by-Step &amp; SSH</h2>
            <p class="text-slate-400 text-sm mb-6">Tous les endpoints acceptent et retournent du <code class="text-indigo-400">application/json</code>.</p>

            <div class="space-y-4">
                @php
                $endpoints = [
                    ['GET',  '/api/ssh/public-key',                   'SSH',     'aucun',                             'Récupère la clé publique locale'],
                    ['POST', '/api/ssh/test',                         'SSH',     'node',                              'Teste l\'accès sans mot de passe'],
                    ['POST', '/api/ssh/authorize',                    'SSH',     'node, password',                    'Injecte la clé publique avec mot de passe'],
                    ['POST', '/api/mirroring/steps/create-user',      'Step 1', 'site_a, pool',                      'Crée le user Ceph sur Site A'],
                    ['POST', '/api/mirroring/steps/transfer-keyring', 'Step 2', 'site_a, site_b',                    'Transfère le keyring A→B'],
                    ['POST', '/api/mirroring/steps/configure-site-b', 'Step 3', 'site_b',                            'Symlink config + user local Site B'],
                    ['POST', '/api/mirroring/steps/enable-pool',      'Step 4', 'site_a, site_b, pool',              'Active le mirroring du pool'],
                    ['POST', '/api/mirroring/steps/configure-peer',   'Step 5', 'site_b, pool',                      'Ajoute le peer Site A sur Site B'],
                    ['POST', '/api/mirroring/steps/setup-daemon',     'Step 6', 'site_b',                            'Installe et démarre rbd-mirror'],
                    ['POST', '/api/mirroring/steps/enable-image',     'Step 7', 'site_a, pool, image, mode, schedule?', 'Active image + schedule'],
                    ['POST', '/api/mirroring/steps/demote',           'Failover', 'node, pool, image?, force?',      'Demote pool/image'],
                    ['POST', '/api/mirroring/steps/promote',          'Failover', 'node, pool, image?, force?',      'Promote pool/image'],
                    ['GET',  '/api/mirroring/steps/status',           'Statut',  'node, pool (query params)',         'Statut du pool (JSON)'],
                ];
                @endphp
                @foreach($endpoints as $ep)
                <div class="flex items-start gap-4 bg-slate-800/40 rounded-xl px-5 py-3 border border-slate-700/40">
                    <span class="badge mt-0.5 shrink-0 {{ $ep[0]==='GET' ? 'bg-sky-500/20 text-sky-400' : 'bg-indigo-500/20 text-indigo-400' }}">{{ $ep[0] }}</span>
                    <div class="flex-1 min-w-0">
                        <code class="text-slate-200 text-sm font-mono">{{ $ep[1] }}</code>
                        <p class="text-slate-500 text-xs mt-0.5">{{ $ep[4] }}</p>
                    </div>
                    <span class="badge bg-slate-700/60 text-slate-400 shrink-0">{{ $ep[2] }}</span>
                    <code class="text-xs text-emerald-400 font-mono shrink-0 hidden md:block">{{ $ep[3] }}</code>
                </div>
                @endforeach
            </div>

            <div class="mt-8">
                <h3 class="text-base font-semibold text-white mb-3">Exemple curl — Tester l'accès SSH</h3>
                <pre class="bg-slate-950/80 rounded-xl p-4 text-xs text-emerald-300 font-mono overflow-x-auto">curl -X POST http://localhost:8000/api/ssh/test \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: &lt;token&gt;" \
  -d '{"node":"54.38.146.218"}'

# Réponse
{
  "node": "54.38.146.218",
  "connected": true,
  "status": "authorized",
  "message": "Connecté sans mot de passe ✓"
}</pre>
            </div>
        </div>
    </div>

    <!-- ═══════════ LOG CONSOLE (Always Available) ═══════════ -->
    <section class="glass-panel rounded-2xl shadow-2xl mt-8">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700/50">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-cyan-500/20 rounded-lg text-cyan-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <h2 class="text-lg font-bold text-white">Console — Logs en temps réel</h2>
            </div>
            <div class="flex items-center gap-3">
                <span id="log-status-badge" class="badge bg-slate-700 text-slate-400">En attente</span>
                <button onclick="clearConsole()" class="text-xs text-slate-500 hover:text-slate-300 border border-slate-700 rounded-md px-3 py-1 transition-colors">Effacer</button>
            </div>
        </div>
        <div class="bg-slate-950/80 rounded-b-2xl overflow-hidden">
            <div class="flex items-center gap-1.5 px-4 py-2.5 bg-slate-800/60 border-b border-slate-700/50">
                <div class="w-3 h-3 rounded-full bg-red-500/80"></div>
                <div class="w-3 h-3 rounded-full bg-amber-400/80"></div>
                <div class="w-3 h-3 rounded-full bg-emerald-500/80"></div>
                <span class="ml-3 text-xs text-slate-500 font-mono">DRS — Ceph Mirror Output</span>
            </div>
            <div id="log-console" class="h-80 overflow-y-auto px-5 py-4 space-y-0.5">
                <p class="text-slate-600 italic text-xs">Lancez une commande ou testez vos nœuds pour voir les logs ici...</p>
            </div>
        </div>
        <!-- History -->
        <div class="px-6 py-4 border-t border-slate-700/50">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Historique</h3>
                <button onclick="loadLogHistory()" class="text-xs text-indigo-400 hover:text-indigo-300">↻ Actualiser</button>
            </div>
            <div id="log-history" class="space-y-1.5 max-h-44 overflow-y-auto">
                @foreach($logs as $log)
                <div class="flex items-center justify-between bg-slate-800/40 rounded-lg px-4 py-2 hover:bg-slate-700/40 cursor-pointer transition-colors" onclick="reloadLog('{{ $log['log_id'] }}')">
                    <div class="flex items-center gap-2">
                        <span class="badge {{ $log['status']==='success' ? 'bg-emerald-500/20 text-emerald-400' : ($log['status']==='error' ? 'bg-rose-500/20 text-rose-400' : 'bg-amber-500/20 text-amber-400') }}">
                            {{ $log['status']==='running' ? '⟳' : ($log['status']==='success' ? '✓' : '✗') }} {{ $log['status'] }}
                        </span>
                        <span class="text-sm text-slate-300">{{ $log['label'] }}</span>
                        <code class="text-xs text-slate-600">{{ $log['log_id'] }}</code>
                    </div>
                    <span class="text-xs text-slate-600">{{ $log['date'] }}</span>
                </div>
                @endforeach
                @if(empty($logs))<p class="text-slate-600 text-xs italic text-center py-3">Aucun log.</p>@endif
            </div>
        </div>
    </section>

</div>

<style>
.label { display:block; font-size:0.8rem; font-weight:500; color:#94a3b8; margin-bottom:4px; }
.field { width:100%; background:rgba(15,23,42,0.5); border:1px solid #334155; color:white; border-radius:8px; padding:8px 14px; font-size:0.875rem; outline:none; transition:border-color 0.15s; }
.field:focus { border-color:#6366f1; box-shadow:0 0 0 2px rgba(99,102,241,0.2); }
.btn-primary { display:flex; align-items:center; justify-content:center; gap:8px; width:100%; background:linear-gradient(to right,#4f46e5,#6366f1); color:white; font-weight:600; padding:12px; border-radius:10px; transition:all 0.15s; cursor:pointer; }
.btn-primary:hover { background:linear-gradient(to right,#4338ca,#4f46e5); transform:translateY(-1px); }
.btn-secondary { display:flex; align-items:center; justify-content:center; gap:8px; background:#1e293b; border:1px solid #334155; color:#94a3b8; font-weight:500; padding:9px; border-radius:10px; transition:all 0.15s; font-size:0.875rem; cursor:pointer; }
.btn-secondary:hover { background:#334155; color:#e2e8f0; }
.btn-step { font-size:0.8rem; font-weight:600; background:#1e293b; border:1px solid #334155; color:#94a3b8; padding:6px 14px; border-radius:8px; transition:all 0.15s; white-space:nowrap; cursor:pointer; }
.btn-step:hover { background:#334155; color:#e2e8f0; }
</style>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
let activePolling = null;
let lastLogOffset = 0;

// Default Proxmox Node Clusters
const DEFAULT_NODES_A = ['54.38.146.218', '54.38.146.219', '54.38.146.220'];
const DEFAULT_NODES_B = ['54.38.146.210', '54.38.146.211', '54.38.146.212'];
let customNodes = JSON.parse(localStorage.getItem('drs_custom_nodes') || '[]');

// ══ TAB SWITCHING ─────────────────────────────────────────────
const tabs = ['ssh', 'allinone', 'wizard', 'failover', 'api'];
function switchTab(name) {
    tabs.forEach(t => {
        const p = document.getElementById('panel-' + t);
        const b = document.getElementById('tab-' + t);
        if (p) p.classList.toggle('hidden', t !== name);
        if (b) b.className = t === name 
            ? 'tab-active pb-2 text-sm font-semibold transition-colors flex items-center gap-2 shrink-0' 
            : 'tab-inactive pb-2 text-sm font-semibold transition-colors flex items-center gap-2 shrink-0';
    });
}

// ══ SSH KEY MANAGEMENT ────────────────────────────────────────
async function loadSshKey() {
    try {
        const res = await fetch('/api/ssh/public-key');
        const data = await res.json();
        if (data.public_key) {
            document.getElementById('ssh-public-key-text').value = data.public_key;
            if (data.path) document.getElementById('ssh-key-path').textContent = `Emplacement : ${data.path}`;
        }
    } catch (e) {
        document.getElementById('ssh-public-key-text').value = "Erreur lors du chargement de la clé publique.";
    }
}

function copySshKey() {
    const text = document.getElementById('ssh-public-key-text').value;
    navigator.clipboard.writeText(text).then(() => {
        const btn = document.getElementById('btn-copy-key');
        const orig = btn.innerHTML;
        btn.innerHTML = `<span class="text-emerald-400 font-bold">✓ Clé copiée !</span>`;
        setTimeout(() => btn.innerHTML = orig, 2000);
    });
}

// Render Node Cards
function createNodeCardHtml(ip, group) {
    const safeId = ip.replace(/\./g, '-');
    return `
    <div id="node-card-${safeId}" class="node-card bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 flex flex-col justify-between gap-3">
        <div>
            <div class="flex items-center justify-between mb-1.5">
                <span class="font-mono text-sm font-bold text-white">${ip}</span>
                <span id="node-badge-${safeId}" class="badge bg-slate-700/60 text-slate-400">Non testé</span>
            </div>
            <p id="node-msg-${safeId}" class="text-xs text-slate-500 truncate">Cliquez sur Tester pour vérifier</p>
        </div>

        <div class="flex items-center gap-2 pt-2 border-t border-slate-700/40">
            <button onclick="testSingleNode('${ip}')" id="btn-test-${safeId}" class="flex-1 btn-secondary text-xs py-1.5 px-2.5 flex items-center justify-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/></svg>
                Tester
            </button>
            <button onclick="toggleAuthForm('${ip}')" id="btn-auth-${safeId}" class="flex-1 bg-indigo-600/30 hover:bg-indigo-600/50 border border-indigo-500/40 text-indigo-200 text-xs font-semibold py-1.5 px-2.5 rounded-lg transition-colors flex items-center justify-center gap-1">
                🔑 Autoriser
            </button>
        </div>

        <!-- Inline Password Auth Form -->
        <div id="node-auth-box-${safeId}" class="hidden pt-2 border-t border-indigo-500/30 bg-slate-900/60 p-2.5 rounded-lg mt-1 space-y-2">
            <p class="text-[11px] text-indigo-300 font-medium">Mot de passe root pour ${ip} :</p>
            <div class="flex gap-1.5">
                <input type="password" id="node-pwd-${safeId}" placeholder="Mot de passe root" class="field text-xs py-1 px-2 flex-1" />
                <button onclick="submitAuthorizeKey('${ip}')" id="btn-inject-${safeId}" class="btn-primary text-xs py-1 px-3 whitespace-nowrap">Injecter</button>
            </div>
            <div id="node-inject-feedback-${safeId}" class="text-[11px] hidden"></div>
        </div>
    </div>`;
}

function renderNodeMatrices() {
    const gridA = document.getElementById('site-a-nodes-grid');
    const gridB = document.getElementById('site-b-nodes-grid');
    const gridC = document.getElementById('custom-nodes-grid');

    if (gridA) gridA.innerHTML = DEFAULT_NODES_A.map(ip => createNodeCardHtml(ip, 'Site A')).join('');
    if (gridB) gridB.innerHTML = DEFAULT_NODES_B.map(ip => createNodeCardHtml(ip, 'Site B')).join('');
    if (gridC) gridC.innerHTML = customNodes.map(ip => createNodeCardHtml(ip, 'Custom')).join('');
}

function addCustomNode() {
    const input = document.getElementById('custom-node-ip');
    const ip = input.value.trim();
    if (!ip) return;
    if (!customNodes.includes(ip) && !DEFAULT_NODES_A.includes(ip) && !DEFAULT_NODES_B.includes(ip)) {
        customNodes.push(ip);
        localStorage.setItem('drs_custom_nodes', JSON.stringify(customNodes));
        renderNodeMatrices();
    }
    input.value = '';
}

function toggleAuthForm(ip) {
    const safeId = ip.replace(/\./g, '-');
    const box = document.getElementById(`node-auth-box-${safeId}`);
    if (box) box.classList.toggle('hidden');
}

function updateNodeCardState(ip, status, message) {
    const safeId = ip.replace(/\./g, '-');
    const badge = document.getElementById(`node-badge-${safeId}`);
    const msgEl = document.getElementById(`node-msg-${safeId}`);
    const card = document.getElementById(`node-card-${safeId}`);

    if (!badge || !msgEl) return;

    msgEl.textContent = message || '';

    if (status === 'testing') {
        badge.className = 'badge bg-amber-500/20 text-amber-300';
        badge.innerHTML = '⟳ Test en cours...';
    } else if (status === 'authorized') {
        badge.className = 'badge bg-emerald-500/20 text-emerald-400';
        badge.innerHTML = '✓ Autorisé (SSH OK)';
        if (card) card.style.borderColor = '#10b981';
    } else if (status === 'unauthorized') {
        badge.className = 'badge bg-rose-500/20 text-rose-400';
        badge.innerHTML = '✗ Clé non autorisée';
        if (card) card.style.borderColor = '#ef4444';
    } else if (status === 'unreachable') {
        badge.className = 'badge bg-yellow-500/20 text-yellow-400';
        badge.innerHTML = '⚠ Injoignable';
        if (card) card.style.borderColor = '#f59e0b';
    } else {
        badge.className = 'badge bg-slate-700/60 text-slate-400';
        badge.innerHTML = 'Non testé';
    }
}

async function testSingleNode(ip) {
    updateNodeCardState(ip, 'testing', 'Vérification de la clé...');
    try {
        const res = await fetch('/api/ssh/test', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ node: ip }),
        });
        const data = await res.json();
        updateNodeCardState(ip, data.status, data.message);
        appendLine(`[DRS] Test SSH vers ${ip} : ${data.message}`);
    } catch (e) {
        updateNodeCardState(ip, 'unreachable', 'Erreur réseau');
    }
}

async function testAllNodes() {
    const all = [...DEFAULT_NODES_A, ...DEFAULT_NODES_B, ...customNodes];
    all.forEach(ip => updateNodeCardState(ip, 'testing', 'En attente...'));

    for (const ip of all) {
        await testSingleNode(ip);
    }
}

async function submitAuthorizeKey(ip) {
    const safeId = ip.replace(/\./g, '-');
    const pwdInput = document.getElementById(`node-pwd-${safeId}`);
    const feedback = document.getElementById(`node-inject-feedback-${safeId}`);
    const btn = document.getElementById(`btn-inject-${safeId}`);
    const password = pwdInput ? pwdInput.value : '';

    if (!password) {
        alert('Veuillez saisir le mot de passe root pour ' + ip);
        return;
    }

    if (feedback) {
        feedback.className = 'text-[11px] text-amber-300 block';
        feedback.textContent = 'Injection de la clé en cours via expect...';
    }
    if (btn) btn.disabled = true;

    try {
        const res = await fetch('/api/ssh/authorize', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ node: ip, password }),
        });
        const data = await res.json();

        if (res.ok && data.success) {
            if (feedback) {
                feedback.className = 'text-[11px] text-emerald-400 font-bold block';
                feedback.textContent = '✓ Clé injectée avec succès !';
            }
            if (pwdInput) pwdInput.value = '';
            setTimeout(() => toggleAuthForm(ip), 2000);
            updateNodeCardState(ip, 'authorized', 'Connecté sans mot de passe ✓');
            appendLine(`[DRS] [SUCCESS] Clé SSH injectée et vérifiée sur ${ip} ✓`);
        } else {
            if (feedback) {
                feedback.className = 'text-[11px] text-rose-400 block';
                feedback.textContent = '✗ ' + (data.message || 'Échec de l\'autorisation');
            }
            appendLine(`[DRS] [ERROR] Échec sur ${ip} : ${data.message || ''}`);
        }
    } catch (e) {
        if (feedback) {
            feedback.className = 'text-[11px] text-rose-400 block';
            feedback.textContent = '✗ Erreur de communication avec le serveur';
        }
    } finally {
        if (btn) btn.disabled = false;
    }
}

// ══ LOG CONSOLE ──────────────────────────────────────────────
function colorize(line) {
    const el = document.createElement('p');
    el.textContent = line;
    if (line.startsWith('[DRS]'))                      el.className = 'log-drs';
    else if (/\[INFO\]/i.test(line))                   el.className = 'log-info';
    else if (/\[SUCCESS\]/i.test(line))                el.className = 'log-success';
    else if (/\[ERROR\]|error|failed/i.test(line))     el.className = 'log-error';
    else if (/\[WARN\]|warning/i.test(line))           el.className = 'log-warn';
    else if (/^─+$/.test(line.trim()))                 el.className = 'log-sep';
    else                                               el.className = 'log-default';
    return el;
}
function appendLine(line) {
    const c = document.getElementById('log-console');
    const ph = c.querySelector('p.italic');
    if (ph) ph.remove();
    c.appendChild(colorize(line));
    c.scrollTop = c.scrollHeight;
}
function clearConsole() {
    document.getElementById('log-console').innerHTML = '<p class="text-slate-600 italic text-xs">Console effacée.</p>';
    setStatus('idle');
    if (activePolling) { clearInterval(activePolling); activePolling = null; }
    lastLogOffset = 0;
}
function setStatus(s) {
    const b = document.getElementById('log-status-badge');
    const m = {idle:'bg-slate-700 text-slate-400',running:'bg-amber-500/20 text-amber-400',success:'bg-emerald-500/20 text-emerald-400',error:'bg-rose-500/20 text-rose-400'};
    const l = {idle:'En attente',running:'⟳ En cours…',success:'✓ Succès',error:'✗ Erreur'};
    b.className = 'badge '+(m[s]||m.idle);
    b.textContent = l[s]||s;
}

// ══ POLL LOG ─────────────────────────────────────────────────
function startPolling(logId) {
    if (activePolling) clearInterval(activePolling);
    setStatus('running');
    lastLogOffset = 0;

    activePolling = setInterval(async () => {
        try {
            const res  = await fetch('/mirroring/logs/' + logId);
            const data = await res.json();
            if (data.error) return;

            const fullContent = data.content || '';
            const newContent  = fullContent.substring(lastLogOffset);
            if (newContent) {
                newContent.split('\n').forEach(line => { if (line.trim()) appendLine(line); });
                lastLogOffset = fullContent.length;
            }

            if (data.done) {
                clearInterval(activePolling);
                activePolling = null;
                setStatus(data.status === 'success' ? 'success' : 'error');
                loadLogHistory();
            }
        } catch (e) {}
    }, 1500);
}

// ══ GENERIC POST ─────────────────────────────────────────────
async function postStep(endpoint, body) {
    clearConsole();
    appendLine('[DRS] Envoi → ' + endpoint);
    const res  = await fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify(body),
    });
    const data = await res.json();
    if (data.log_id) {
        appendLine('[DRS] ' + (data.message || ''));
        startPolling(data.log_id);
    } else {
        appendLine('[DRS] ' + (data.message || JSON.stringify(data)));
        setStatus('error');
    }
    return data;
}

// ══ ALL-IN-ONE SETUP ─────────────────────────────────────────
document.getElementById('form-setup').addEventListener('submit', e => {
    e.preventDefault();
    const f = new FormData(e.target);
    const b = {}; f.forEach((v,k)=>b[k]=v);
    postStep('/mirroring/setup', b);
});

// ══ WIZARD STEPS ─────────────────────────────────────────────
const stepConfig = {
    1: { endpoint: '/api/mirroring/steps/create-user',      keys: ['site_a','pool'] },
    2: { endpoint: '/api/mirroring/steps/transfer-keyring', keys: ['site_a','site_b'] },
    3: { endpoint: '/api/mirroring/steps/configure-site-b', keys: ['site_b'] },
    4: { endpoint: '/api/mirroring/steps/enable-pool',      keys: ['site_a','site_b','pool'] },
    5: { endpoint: '/api/mirroring/steps/configure-peer',   keys: ['site_b','pool'] },
    6: { endpoint: '/api/mirroring/steps/setup-daemon',     keys: ['site_b'] },
    7: { endpoint: '/api/mirroring/steps/enable-image',     keys: ['site_a','pool','image','mode','schedule'] },
};
const wzMap = { site_a:'wz-site-a', site_b:'wz-site-b', pool:'wz-pool', image:'wz-image', mode:'wz-mode', schedule:'wz-schedule' };

function getWzValues(keys) {
    const b = {};
    keys.forEach(k => { const v = document.getElementById(wzMap[k])?.value; if (v) b[k] = v; });
    return b;
}

function setStepStatus(n, state) {
    const el = document.getElementById('step-'+n);
    const badge = document.getElementById('step-'+n+'-badge');
    if (!el || !badge) return;
    el.className = el.className.replace(/step-\w+/, 'step-'+state);
    const labels = { idle:'En attente', running:'⟳ En cours', success:'✓ Succès', error:'✗ Erreur' };
    const colors  = {
        idle:    'bg-slate-700/60 text-slate-400',
        running: 'bg-amber-500/20 text-amber-400',
        success: 'bg-emerald-500/20 text-emerald-400',
        error:   'bg-rose-500/20 text-rose-400',
    };
    badge.className = 'badge '+(colors[state]||colors.idle);
    badge.textContent = labels[state]||state;
    const saved = JSON.parse(localStorage.getItem('drs_wizard_steps')||'{}');
    saved[n] = state;
    localStorage.setItem('drs_wizard_steps', JSON.stringify(saved));
}

async function runStep(n) {
    const cfg = stepConfig[n];
    const body = getWzValues(cfg.keys);
    setStepStatus(n, 'running');
    clearConsole();
    appendLine('[DRS] Step '+n+' → '+cfg.endpoint);
    const res  = await fetch(cfg.endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify(body),
    });
    const data = await res.json();
    appendLine('[DRS] '+(data.message||''));
    if (data.log_id) startPolling(data.log_id);
    setStepStatus(n, data.success ? 'success' : 'error');
}

// ══ FAILOVER ─────────────────────────────────────────────────
function runFailoverStep(action) {
    const body = {
        node:  document.getElementById('fo-node').value,
        pool:  document.getElementById('fo-pool').value,
        image: document.getElementById('fo-image').value || undefined,
        force: document.getElementById('fo-force').checked || undefined,
        action,
    };
    postStep('/api/mirroring/steps/'+action, body);
}

// ══ STATUS ───────────────────────────────────────────────────
async function fetchStatus() {
    const node = document.getElementById('status-node').value;
    const pool = document.getElementById('status-pool').value;
    const out  = document.getElementById('status-output');
    out.textContent = '⟳ Chargement...';
    try {
        const res  = await fetch(`/api/mirroring/steps/status?node=${encodeURIComponent(node)}&pool=${encodeURIComponent(pool)}`);
        const data = await res.json();
        out.textContent = JSON.stringify(data, null, 2);
    } catch (e) {
        out.textContent = 'Erreur : ' + e.message;
    }
}

// ══ LOG HISTORY ──────────────────────────────────────────────
async function loadLogHistory() {
    const res  = await fetch('/mirroring/logs');
    const logs = await res.json();
    const el   = document.getElementById('log-history');
    if (!logs.length) { el.innerHTML = '<p class="text-slate-600 text-xs italic text-center py-3">Aucun log.</p>'; return; }
    el.innerHTML = logs.map(l => {
        const cls = l.status==='success' ? 'bg-emerald-500/20 text-emerald-400' : l.status==='error' ? 'bg-rose-500/20 text-rose-400' : 'bg-amber-500/20 text-amber-400';
        return `<div class="flex items-center justify-between bg-slate-800/40 rounded-lg px-4 py-2 hover:bg-slate-700/40 cursor-pointer transition-colors" onclick="reloadLog('${l.log_id}')">
            <div class="flex items-center gap-2">
                <span class="badge ${cls}">${l.status==='running'?'⟳':l.status==='success'?'✓':'✗'} ${l.status}</span>
                <span class="text-sm text-slate-300">${l.label}</span>
                <code class="text-xs text-slate-600">${l.log_id}</code>
            </div>
            <span class="text-xs text-slate-600">${l.date}</span>
        </div>`;
    }).join('');
}

async function reloadLog(logId) {
    clearConsole();
    appendLine('[DRS] Chargement du log : '+logId);
    const res  = await fetch('/mirroring/logs/'+logId);
    const data = await res.json();
    if (data.error) { appendLine('[DRS] ' + data.error); return; }
    data.content.split('\n').forEach(l => { if (l) appendLine(l); });
    setStatus(data.status === 'success' ? 'success' : data.status === 'running' ? 'running' : 'error');
    if (data.status === 'running') startPolling(logId);
}

// ══ INITIALIZATION ───────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    loadSshKey();
    renderNodeMatrices();
    // Auto-test all known nodes on load
    testAllNodes();

    // Restore wizard state
    const saved = JSON.parse(localStorage.getItem('drs_wizard_steps')||'{}');
    Object.entries(saved).forEach(([n, state]) => { try { setStepStatus(n, state); } catch(e){} });
});
</script>

</body>
</html>
