




## Iniciar Ambiente de desenvolvimento:

        composer install

        docker compose up -d

        sudo chmod -R 777 storage

        sudo chmod -R 777 bootstrap/cache

        sudo chown -R 1001:1001 docker/redis

        sudo chmod -R 777 docker/redis


    Docker compose:

        docker compose up -d

    Executar migrations e seeds:

        docker exec -it uniselec-api bash -c "php artisan migrate"
        docker exec -it uniselec-api bash -c "php artisan db:seed"

        docker exec -it uniselec-api bash -c "php artisan db:seed --class=UserSeeder"


        docker exec -it uniselec-api bash -c "php artisan route:cache"

        docker exec -it uniselec-api bash -c "php artisan route:clear"


        docker exec -it uniselec-api bash -c "php artisan storage:link"

### Arquitetura da solução
```mermaid
flowchart TD

A[GitLab Repository<br/>Codigo API<br/>Kustomize<br/>SealedSecrets stg-prd] --> B

B[GitLab CI-CD Pipeline<br/>Build Test<br/>Push Imagem<br/>Atualiza Manifest] --> C

C[ArgoCD Server<br/>AppProject<br/>ApplicationSet<br/>Sync Git para K8s] --> D

D[Cluster Kubernetes<br/>Control Plane HA<br/>Worker Nodes] --> E

D -.-> V[Virtualizador<br/>Proxmox VE<br/>ou Nutanix AHV]

E[Namespace uniselec-api<br/>dev stg prd] --> F

F[Laravel API<br/>Deployment initContainers<br/>Service ClusterIP<br/>Ingress<br/>HPA CPU Memory<br/>PVC NFS]

E --> G

G[MariaDB Galera Cluster<br/>StatefulSet 3 nos<br/>5 Services<br/>PVCs<br/>ConfigMaps<br/>SealedSecrets<br/>RBAC]

E --> H

H[Secrets Mgmt<br/>SealedSecrets stg-prd<br/>SecretGenerator dev]
```

## Segredos (Sealed Secrets)
```sh
kubectl apply -f https://github.com/bitnami-labs/sealed-secrets/releases/download/v0.33.1/controller.yaml
kubeseal -f regcred-secret.yaml -w base/sealed-secret-regcred.yaml --scope cluster-wide
kubeseal --validate < base/sealed-secret-regcred.yaml
```

### Pausar ArgoCD
argocd login argocd.unilab.edu.br --username admin --password "pass" --grpc-web
argocd app set uniselec-api-prd --sync-policy none

### Desprovisionar Deploy
```sh
argocd login argocd.unilab.edu.br --username admin --password "pass" --grpc-web
kubectl get applicationset -A
kubectl -n argocd patch applicationset uniselec-api-dev-as --type='merge' -p '{"spec":{"generators":[{"list":{"elements":[]}}]}}'
argocd app list | grep uniselec-api-dev
```

### Re-Provisionar Deproy
```text
┌───────────────────────────────────────────────────────────────────────────┐
│                       Inital Pipeline Execution Flow                      │
└───────────────────────────────────────────────────────────────────────────┘
┌──────────────┐  ┌───────────┐  ┌──────────┐  ┌──────────┐  ┌──────────────┐
│   validate   │─>│   tests   │─>│  build   │─>│ staging  │─>│ notification │
└──────────────┘  └───────────┘  └──────────┘  └──────────┘  └──────────────┘
│                 │              │             │             │
├─ docker         ├─ dependency  └─ docker     └─ deploy     └─ staging
├─ environment    ├─ sast_scan                 (re-run aqui)
└─ kustomize      ├─ sonarqube
                  └─ unit
```
**Ação necessária**: Rodar o Job `staging` da Pipeline GitLab CI/CD GitOps

