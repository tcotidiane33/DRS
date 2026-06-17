# DRS — Dynamic Resource Scheduler

Application Laravel pour déployer des VMs et conteneurs LXC sur un cluster Proxmox avec sélection automatique du nœud optimal.

## Architecture

```
Laravel App
    ├── ProxmoxService       ← appels API REST Proxmox
    ├── NodeSelectorService  ← logique de choix du meilleur nœud
    ├── VmController         ← endpoints HTTP (web + API)
    ├── CreateProxmoxVm      ← job asynchrone de création
    └── Blade UI             ← formulaire de création + tableau de bord
```

## Prérequis

- PHP 8.2+
- Composer
- Extension SQLite (ou MySQL/PostgreSQL)

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configurer Proxmox dans `.env` :

```env
PROXMOX_HOST=votre-serveur.proxmox
PROXMOX_PORT=8006
PROXMOX_USER=root@pam
PROXMOX_TOKEN_ID=votre-token
PROXMOX_TOKEN_SECRET=votre-secret
PROXMOX_VERIFY_SSL=false
```

Puis :

```bash
php artisan migrate
php artisan db:seed
```

## Démarrage

Terminal 1 — serveur web :

```bash
php artisan serve
```

Terminal 2 — worker de queue (création asynchrone) :

```bash
php artisan queue:work
```

Interface web : http://localhost:8000/vms

## API (Sanctum)

Compte par défaut après seed : `admin@drs.local` / `password`

```bash
# Login
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@drs.local","password":"password"}'

# Créer une VM (token Bearer)
curl -X POST http://localhost:8000/api/vms \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "web-01",
    "type": "vm",
    "memory": 2048,
    "cores": 2,
    "disk_size": 20,
    "storage": "local-zfs",
    "bridge": "vmbr0",
    "method": "score"
  }'

# Suivi du job
curl http://localhost:8000/api/jobs/1 \
  -H "Authorization: Bearer TOKEN"
```

## Endpoints

| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/vms` | Tableau de bord |
| GET | `/vms/create` | Formulaire de création |
| POST | `/vms` | Lancer un déploiement |
| GET | `/api/nodes` | Statut des nœuds (public web) |
| GET | `/api/best-node` | Meilleur nœud selon méthode |
| GET | `/api/templates` | Templates VM/CT disponibles |
| POST | `/api/login` | Authentification Sanctum |
| POST | `/api/vms` | Création via API (auth) |
| GET | `/api/jobs/{id}` | Statut d'un job |

## Méthodes de placement

- **memory** — nœud avec le plus de RAM libre
- **cpu** — nœud avec la charge CPU la plus faible
- **score** — score combiné RAM (60%) + CPU (40%)
