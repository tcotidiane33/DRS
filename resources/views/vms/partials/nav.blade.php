<nav class="bg-white shadow-sm border-b">
    <div class="max-w-6xl mx-auto px-8 py-4 flex items-center justify-between">
        <a href="{{ route('vms.index') }}" class="font-bold text-gray-800 text-lg">DRS — Proxmox Manager</a>
        <div class="flex gap-4 text-sm">
            <a href="{{ route('vms.index') }}" class="text-gray-600 hover:text-blue-600">Tableau de bord</a>
            <a href="{{ route('vms.create') }}" class="text-gray-600 hover:text-blue-600">Créer une VM/CT</a>
        </div>
    </div>
</nav>
