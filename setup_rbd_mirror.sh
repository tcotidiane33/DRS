#!/usr/bin/env bash
#
# setup_rbd_mirror.sh
# Automates the setup of Ceph RBD mirroring between two Proxmox clusters (Site A to Site B)
#
# Reference: https://pve.proxmox.com/wiki/Ceph_RBD_Mirroring
#
set -euo pipefail

function usage() {
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  --site-a-node <hostname>    Hostname or IP of a node in Site A (Source)"
    echo "  --site-b-node <hostname>    Hostname or IP of a node in Site B (Target) where the daemon runs"
    echo "  --pool <pool_name>          Name of the Ceph pool to mirror (e.g., rbd)"
    echo "  --mode <snapshot|journal>   Mirror mode (snapshot recommended for LXC/KRBD)"
    echo "  --image <image_name>        (Optional) Specific image to enable mirroring on (e.g., vm-100-disk-0)"
    echo "                              If omitted, it sets up pool level config only."
    echo "  --schedule <interval>       (Optional) Snapshot schedule interval (e.g., 5m, 1h). Valid only with snapshot mode."
    echo ""
    exit 1
}

SITE_A=""
SITE_B=""
POOL=""
MODE="snapshot"
IMAGE=""
SCHEDULE=""

while [[ "$#" -gt 0 ]]; do
    case $1 in
        --site-a-node) SITE_A="$2"; shift ;;
        --site-b-node) SITE_B="$2"; shift ;;
        --pool) POOL="$2"; shift ;;
        --mode) MODE="$2"; shift ;;
        --image) IMAGE="$2"; shift ;;
        --schedule) SCHEDULE="$2"; shift ;;
        -h|--help) usage ;;
        *) echo "Unknown parameter passed: $1"; usage ;;
    esac
    shift
done

if [[ -z "$SITE_A" || -z "$SITE_B" || -z "$POOL" ]]; then
    echo "Error: --site-a-node, --site-b-node, and --pool are required."
    usage
fi

echo "[INFO] Starting Ceph RBD Mirroring Setup (Site A -> Site B)..."

# 1. Create users on Site A
echo "[INFO] Creating user on Site A ($SITE_A)..."
ssh root@"$SITE_A" "ceph auth get-or-create client.rbd-mirror-peer-a mon 'profile rbd' osd 'profile rbd' -o /etc/pve/priv/site-b.client.rbd-mirror-peer-a.keyring"

# 2. Transfer Site A keyring and config to Site B
echo "[INFO] Transferring Site A keyring and ceph config to Site B ($SITE_B)..."
ssh root@"$SITE_A" "scp /etc/pve/priv/site-b.client.rbd-mirror-peer-a.keyring root@${SITE_B}:/etc/pve/priv/site-a.client.rbd-mirror-peer-a.keyring"
ssh root@"$SITE_A" "scp /etc/pve/ceph.conf root@${SITE_B}:/etc/pve/site-a.conf"

# 3. Setup Site B user and symlink config
echo "[INFO] Setting up user and config on Site B ($SITE_B)..."
ssh root@"$SITE_B" << 'EOF'
    # Create symlink for site-a config
    ln -sf /etc/pve/site-a.conf /etc/ceph/site-a.conf
    # Create local user for rbd-mirror daemon
    ceph auth get-or-create client.rbd-mirror.$(hostname) mon 'profile rbd-mirror' osd 'profile rbd' -o /etc/pve/priv/ceph.client.rbd-mirror.$(hostname).keyring
EOF

# 4. Enable mirroring on pools on both sites
echo "[INFO] Enabling mirroring on pool '$POOL' on both sites..."
ssh root@"$SITE_A" "rbd mirror pool enable $POOL image"
ssh root@"$SITE_B" "rbd mirror pool enable $POOL image"

# 5. Configure Peers on Site B
echo "[INFO] Configuring peer on Site B connecting to Site A..."
ssh root@"$SITE_B" "rbd mirror pool peer add $POOL client.rbd-mirror-peer-a@site-a"

# 6. Setup the rbd-mirror daemon on Site B
echo "[INFO] Setting up rbd-mirror daemon on Site B..."
ssh root@"$SITE_B" << 'EOF'
    apt-get update && apt-get install -y rbd-mirror
    systemctl enable ceph-rbd-mirror.target
    cp /usr/lib/systemd/system/ceph-rbd-mirror@.service /etc/systemd/system/ceph-rbd-mirror@.service
    sed -i -e 's/setuser ceph.*/setuser root --setgroup root/' /etc/systemd/system/ceph-rbd-mirror@.service
    systemctl daemon-reload
    systemctl enable --now ceph-rbd-mirror@rbd-mirror.$(hostname).service
EOF

# 7. Configure image mirroring (if specified)
if [[ -n "$IMAGE" ]]; then
    echo "[INFO] Enabling mirror for image '$POOL/$IMAGE' in $MODE mode on Site A..."
    ssh root@"$SITE_A" "rbd mirror image enable $POOL/$IMAGE $MODE"

    if [[ "$MODE" == "snapshot" && -n "$SCHEDULE" ]]; then
        echo "[INFO] Adding snapshot schedule '$SCHEDULE' for pool '$POOL' on Site A..."
        ssh root@"$SITE_A" "rbd mirror snapshot schedule add --pool $POOL $SCHEDULE"
    fi
fi

echo "[SUCCESS] RBD Mirroring Setup Complete!"
echo "To check status on Site B, run: rbd mirror pool status $POOL --verbose"
