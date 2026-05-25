#!/bin/bash

check_vault_init_containers() {
    status=$(kubectl get pods -n $APP_NAMESPACE -o jsonpath='{.status.initContainerStatuses[*].ready}' | grep -q false && echo "false" || echo "true")
    if [[ $status == "false" ]]; then
        return 1
    else
        return 0
    fi
}

check_vault_secrets() {
    if [ -f /vault/secrets/config ]; then
        return 0
    else
        return 1
    fi
}

if check_vault_init_containers && check_vault_secrets; then
    exit 0
else
    exit 1
fi