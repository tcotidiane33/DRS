#!/usr/bin/env bash
#
# failover_recovery.sh
# Handles failover promotion and planned demotion of Ceph RBD images.
#
set -euo pipefail

function usage() {
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  --node <hostname>           Target node to run the command on"
    echo "  --action <promote|demote>   Action to perform"
    echo "  --pool <pool_name>          Name of the Ceph pool"
    echo "  --image <image_name>        (Optional) Specific image. If omitted, operates on the whole pool"
    echo "  --force                     (Optional) Force promotion (used during disaster recovery when source is down)"
    echo ""
    exit 1
}

NODE=""
ACTION=""
POOL=""
IMAGE=""
FORCE=""

while [[ "$#" -gt 0 ]]; do
    case $1 in
        --node) NODE="$2"; shift ;;
        --action) ACTION="$2"; shift ;;
        --pool) POOL="$2"; shift ;;
        --image) IMAGE="$2"; shift ;;
        --force) FORCE="--force" ;;
        -h|--help) usage ;;
        *) echo "Unknown parameter passed: $1"; usage ;;
    esac
    shift
done

if [[ -z "$NODE" || -z "$ACTION" || -z "$POOL" ]]; then
    echo "Error: --node, --action, and --pool are required."
    usage
fi

if [[ "$ACTION" != "promote" && "$ACTION" != "demote" ]]; then
    echo "Error: Invalid action. Use 'promote' or 'demote'."
    exit 1
fi

CMD="rbd mirror"
if [[ -n "$IMAGE" ]]; then
    CMD="$CMD image $ACTION $POOL/$IMAGE $FORCE"
else
    CMD="$CMD pool $ACTION $POOL $FORCE"
fi

echo "[INFO] Running command on $NODE: $CMD"
ssh -o StrictHostKeyChecking=accept-new root@"$NODE" "$CMD"

echo "[SUCCESS] Action completed."
