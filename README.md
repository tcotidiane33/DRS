# DRS

## Architecture

```
Laravel App
    ├── ProxmoxService       ← appels API REST Proxmox
    ├── NodeSelectorService  ← logique de choix du meilleur nœud
    ├── VmController         ← endpoints HTTP
    └── Vue/Blade UI         ← formulaire de création simplifié
```