<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    </style>
</head>
<body class="bg-slate-900 text-slate-200 min-h-screen selection:bg-indigo-500 selection:text-white font-sans antialiased overflow-x-hidden relative">
    
    <!-- Background Accents -->
    <div class="absolute top-0 -left-1/4 w-1/2 h-1/2 bg-indigo-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-30 animate-pulse"></div>
    <div class="absolute bottom-0 -right-1/4 w-1/2 h-1/2 bg-emerald-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-20"></div>

    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        
        <header class="mb-12 animate-fade-in text-center">
            <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 to-cyan-400 mb-4 drop-shadow-sm">
                Ceph RBD Mirroring
            </h1>
            <p class="text-lg text-slate-400 max-w-2xl mx-auto">
                Administer your Proxmox Site-to-Site replication with automated setup and disaster recovery management.
            </p>
        </header>

        @if(session('success'))
            <div class="glass-panel border-emerald-500/50 bg-emerald-900/30 text-emerald-300 px-6 py-4 rounded-xl mb-8 flex items-center gap-3 animate-fade-in">
                <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <p>{{ session('success') }}</p>
            </div>
        @endif

        @if(session('error') || $errors->any())
            <div class="glass-panel border-rose-500/50 bg-rose-900/30 text-rose-300 px-6 py-4 rounded-xl mb-8 flex items-center gap-3 animate-fade-in">
                <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <div>
                    <p class="font-medium">{{ session('error') ?? 'Please check the form for errors.' }}</p>
                    @if($errors->any())
                        <ul class="list-disc list-inside text-sm mt-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <!-- Setup Form -->
            <section class="glass-panel rounded-2xl shadow-2xl p-8 animate-fade-in" style="animation-delay: 0.1s;">
                <div class="flex items-center gap-3 mb-6 border-b border-slate-700/50 pb-4">
                    <div class="p-2 bg-indigo-500/20 rounded-lg text-indigo-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <h2 class="text-2xl font-bold text-white">Automated Setup</h2>
                </div>
                
                <form action="{{ route('mirroring.setup') }}" method="POST" class="space-y-5">
                    @csrf
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-slate-300">Site A Node (IP/Host)</label>
                            <input type="text" name="site_a" required class="w-full bg-slate-800/50 border border-slate-700 text-white rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all placeholder-slate-500" placeholder="e.g. 192.168.1.10">
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-slate-300">Site B Node (IP/Host)</label>
                            <input type="text" name="site_b" required class="w-full bg-slate-800/50 border border-slate-700 text-white rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all placeholder-slate-500" placeholder="e.g. 192.168.2.10">
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-slate-300">Ceph Pool Name</label>
                        <input type="text" name="pool" required class="w-full bg-slate-800/50 border border-slate-700 text-white rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all placeholder-slate-500" placeholder="e.g. rbd">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-slate-300">Mirroring Mode</label>
                            <select name="mode" class="w-full bg-slate-800/50 border border-slate-700 text-white rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all">
                                <option value="snapshot">Snapshot (Recommended for KRBD)</option>
                                <option value="journal">Journal</option>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-slate-300">Specific Image (Optional)</label>
                            <input type="text" name="image" class="w-full bg-slate-800/50 border border-slate-700 text-white rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all placeholder-slate-500" placeholder="e.g. vm-100-disk-0">
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-slate-300">Snapshot Schedule Interval (Optional)</label>
                        <input type="text" name="schedule" class="w-full bg-slate-800/50 border border-slate-700 text-white rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all placeholder-slate-500" placeholder="e.g. 5m, 1h">
                        <p class="text-xs text-slate-500 mt-1">Only applies if Snapshot mode is selected.</p>
                    </div>

                    <button type="submit" class="w-full mt-6 bg-gradient-to-r from-indigo-600 to-indigo-500 hover:from-indigo-500 hover:to-indigo-400 text-white font-semibold py-3 px-6 rounded-lg shadow-lg shadow-indigo-500/30 transform transition-all hover:-translate-y-0.5 active:translate-y-0">
                        Initialize Mirroring
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
                    Use these actions to migrate workloads between clusters. For planned maintenance, demote the active site first, then promote the standby site. In a disaster, Force Promote the standby site.
                </p>

                <form action="{{ route('mirroring.failover') }}" method="POST" class="space-y-5 flex-grow">
                    @csrf
                    
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-slate-300">Target Node (IP/Host)</label>
                        <input type="text" name="node" required class="w-full bg-slate-800/50 border border-slate-700 text-white rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-all placeholder-slate-500" placeholder="e.g. 192.168.2.10">
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-slate-300">Ceph Pool</label>
                        <input type="text" name="pool" required class="w-full bg-slate-800/50 border border-slate-700 text-white rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-all placeholder-slate-500" placeholder="e.g. rbd">
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-slate-300">Specific Image (Optional)</label>
                        <input type="text" name="image" class="w-full bg-slate-800/50 border border-slate-700 text-white rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-all placeholder-slate-500" placeholder="Leave empty for whole pool">
                    </div>

                    <div class="grid grid-cols-2 gap-4 pt-4">
                        <button type="submit" name="action" value="demote" class="w-full bg-slate-700 hover:bg-slate-600 border border-slate-600 text-white font-medium py-3 px-4 rounded-lg transition-colors flex justify-center items-center gap-2">
                            <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>
                            Demote
                        </button>
                        
                        <button type="submit" name="action" value="promote" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-medium py-3 px-4 rounded-lg shadow-lg shadow-emerald-500/20 transition-colors flex justify-center items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
                            Promote
                        </button>
                    </div>

                    <div class="mt-6 pt-4 border-t border-rose-500/20">
                        <label class="flex items-center gap-3 p-3 bg-rose-500/10 border border-rose-500/30 rounded-lg cursor-pointer hover:bg-rose-500/20 transition-colors">
                            <input type="checkbox" name="force" value="1" class="w-5 h-5 text-rose-600 rounded bg-slate-800 border-rose-500/50 focus:ring-rose-500 focus:ring-offset-slate-900">
                            <span class="text-rose-400 font-medium text-sm">Force Action (Use during disaster recovery only)</span>
                        </label>
                    </div>
                </form>
            </section>
        </div>
    </div>
</body>
</html>
