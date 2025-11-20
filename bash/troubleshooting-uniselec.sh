#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'

# troubleshooting-uniselec.sh
# Automação de diagnóstico ponta-a-ponta (HAProxy -> Ingress -> API -> MariaDB Galera)
# Suporta 3 ambientes: development, staging, production
#
# Uso:
#   ./troubleshooting-uniselec.sh [env]
#   env: dev | stg | prd  (default: stg)
#
# Variáveis importantes (pode exportar antes de rodar para override):
#   HAPROXY_HOST    - IP do HAProxy externo (default 10.130.0.60)
#   SSH_PORT        - Porta SSH para HAProxy (default 37389)
#   SSH_USER        - Usuário SSH (default ansible)
#   MYSQL_ROOT_PASSWORD - senha do root do MariaDB (se não setado, script tentará pegar do secret)
#   KUBECTL_CONTEXT (opcional)

# ------------ CONFIGURAÇÃO PADRÃO ------------
ENV="${1:-stg}"   # dev | stg | prd
HAPROXY_HOST="${HAPROXY_HOST:-10.130.0.60}"
SSH_PORT="${SSH_PORT:-37389}"
SSH_USER="${SSH_USER:-ansible}"
KUBECTL_CONTEXT="${KUBECTL_CONTEXT:-}"  # opcional

# Map de namespaces e LB IPs (ajuste se necessário)
declare -A NS_MAP=( ["dev"]="uniselec-api-dev" ["stg"]="uniselec-api-stg" ["prd"]="uniselec-api-prd" )
declare -A LB_MAP=( ["dev"]="10.130.0.159" ["stg"]="10.130.0.157" ["prd"]="10.130.0.158" )
declare -A INGRESS_HOST=( ["dev"]="uniselec-api-development.unilab.edu.br" ["stg"]="uniselec-api-stg.unilab.edu.br" ["prd"]="uniselec-api.unilab.edu.br" )

NAMESPACE="${NS_MAP[$ENV]:-uniselec-api-stg}"
LB_IP="${LB_MAP[$ENV]:-10.130.0.157}"
ING_HOST="${INGRESS_HOST[$ENV]:-${INGRESS_HOST:-uniselec-api-development.unilab.edu.br}}"

# get MYSQL_ROOT_PASSWORD from env or k8s secret (best-effort)
MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-}"
if [ -z "$MYSQL_ROOT_PASSWORD" ]; then
  # attempt to read from secret
  if command -v kubectl >/dev/null 2>&1 ; then
    if [ -n "$KUBECTL_CONTEXT" ]; then
      KUBECTL_CTX_OPT="--context=$KUBECTL_CONTEXT"
    else
      KUBECTL_CTX_OPT=""
    fi
    # best-effort; will fail silently if secret not present
    set +e
    secret_pw=$(kubectl $KUBECTL_CTX_OPT get secret mariadb-credentials -n "$NAMESPACE" -o jsonpath='{.data.MYSQL_ROOT_PASSWORD}' 2>/dev/null || true)
    set -e
    if [ -n "$secret_pw" ]; then
      MYSQL_ROOT_PASSWORD="$(echo "$secret_pw" | base64 -d)"
    fi
  fi
fi

# fallback
MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-ChangeMe}"

# Helper de log
echolog() { printf "\n===== %s =====\n" "$1"; }

# Helper para executar comando remoto via SSH com timeout
ssh_exec() {
  local cmd="$1"
  ssh -o BatchMode=yes -o ConnectTimeout=8 -p "$SSH_PORT" "$SSH_USER@$HAPROXY_HOST" "$cmd"
}

# Se contexto kubectl fornecido, prefixa comandos
kubectl_cmd() {
  if [ -n "$KUBECTL_CONTEXT" ]; then
    kubectl --context="$KUBECTL_CONTEXT" "$@"
  else
    kubectl "$@"
  fi
}

# ----------------- FASE 1: REDE EXTERNA (CLIENT -> HAProxy -> Ingress) -----------------
echolog "FASE 1: Testes externos (cliente -> HAProxy -> Ingress)"

# 1.1 ping e traceroute para HAProxy
echolog "1.1 Ping e traceroute para HAProxy $HAPROXY_HOST"
ping -c 4 "$HAPROXY_HOST" || true
if command -v traceroute >/dev/null 2>&1; then
  traceroute -n "$ING_HOST" || true
fi

# 1.2 curl timing para ingress host (se HTTPS)
echolog "1.2 curl timing para $ING_HOST (porta 443)"
if command -v curl >/dev/null 2>&1; then
  curl -s -o /dev/null -w "DNS:%{time_namelookup}s TCP:%{time_connect}s SSL:%{time_appconnect}s TTFB:%{time_starttransfer}s TOTAL:%{time_total}s\n" "https://$ING_HOST" || true
else
  echo "curl não instalado localmente, pulei curl timing"
fi

# ----------------- FASE 2: HAProxy EXTERNO -----------------
echolog "FASE 2: Verificar HAProxy externo (Ingress Controller externo)"

echolog "2.1 Ver processos HAProxy / HAProxy Ingress Controller"
ssh_exec "ps aux | grep -E 'haproxy-ingress-controller|/usr/local/sbin/haproxy -f|haproxy' | grep -v grep" || echo "SSH/ps falhou"

echolog "2.2 Contar conexões ESTABLISHED (no host HAProxy)"
ssh_exec "netstat -tpn 2>/dev/null | grep ESTABLISHED | wc -l" || echo "SSH/netstat falhou"

echolog "2.3 Monitorar ESTABLISHED por 10s"
ssh_exec 'for i in {1..10}; do netstat -tpn 2>/dev/null | grep ESTABLISHED | wc -l; sleep 1; done' || true

echolog "2.4 Listar config do ingress (tmp/haproxy-ingress)"
ssh_exec "sudo ls -la /tmp/haproxy-ingress/etc 2>/dev/null || true"
echolog "2.5 trechos do haproxy.cfg (se existir no /tmp)"
ssh_exec "sudo head -n 80 /tmp/haproxy-ingress/etc/haproxy.cfg 2>/dev/null || sudo head -n 80 /etc/haproxy/haproxy.cfg 2>/dev/null || echo 'haproxy.cfg não encontrado em locais esperados'"

echolog "2.6 Logs HAProxy Ingress - journalctl (últimas 80 linhas)"
ssh_exec "sudo journalctl -u haproxy-ingress -n 80 2>/dev/null || sudo journalctl -u haproxy -n 80 2>/dev/null || echo 'journalctl para haproxy* não disponível ou necessita de permissão'"

# Se houver diretório de logs em /tmp/haproxy-ingress/logs - tail 50
echolog "2.7 Logs via arquivo (se existir)"
ssh_exec "if [ -d /tmp/haproxy-ingress/logs ]; then sudo tail -n 50 /tmp/haproxy-ingress/logs/* 2>/dev/null; else echo 'no ingress log dir'; fi" || true

# 2.8 Teste de conexão do HAProxy até o IP do ingress (K8s ingress public IP)
# Usuário deve substituir <K8S_INGRESS_IP> se souber, aqui tentamos resolver via DNS
K8S_INGRESS_IP=""
if command -v dig >/dev/null 2>&1; then
  K8S_INGRESS_IP=$(dig +short "$ING_HOST" | head -n1 || true)
fi
if [ -n "$K8S_INGRESS_IP" ]; then
  echolog "2.8 Teste nc do HAProxy até K8S_INGRESS_IP=$K8S_INGRESS_IP (via ssh)"
  ssh_exec "nc -zv -w 3 $K8S_INGRESS_IP 80 2>/dev/null || nc -zv -w3 $K8S_INGRESS_IP 443 2>/dev/null || echo 'conexão falhou'"
else
  echo "Não resolvi K8S ingress IP para $ING_HOST; pulei teste nc."
fi

# ----------------- FASE 3: DENTRO DO K8S (Pods, Ingress, HPA, Events, Metrics) -----------------
echolog "FASE 3: Kubernetes checks para namespace $NAMESPACE"

# kubectl context
if [ -n "$KUBECTL_CONTEXT" ]; then
  echolog "Usando kubectl context: $KUBECTL_CONTEXT"
fi

echolog "3.1 Listar pods, ingress, services, endpoints"
kubectl_cmd get pods -n "$NAMESPACE" -o wide || echo "Falha ao listar pods (verifique namespace)"
kubectl_cmd get svc -n "$NAMESPACE" -o wide || true
kubectl_cmd get endpoints -n "$NAMESPACE" || true
kubectl_cmd get ingress -n "$NAMESPACE" -o wide || true

echolog "3.2 Descrever hpa (se existir)"
kubectl_cmd get hpa -n "$NAMESPACE" -o wide || true
kubectl_cmd describe hpa -n "$NAMESPACE" || true

echolog "3.3 Últimos eventos do namespace"
kubectl_cmd get events -n "$NAMESPACE" --sort-by='.lastTimestamp' | tail -n 30 || true

echolog "3.4 Recursos (top pods) - requer metrics-server"
if command -v kubectl >/dev/null 2>&1 ; then
  kubectl_cmd top pods -n "$NAMESPACE" --containers || echo "kubectl top falhou (metrics-server?)"
fi

# ----------------- FASE 4: MariaDB Galera Tests (internos e externos) -----------------
echolog "FASE 4: Testes MariaDB Galera (internos e externos)"

# 4.0: obter MYSQL_ROOT_PASSWORD já feito no topo
echo "Usando MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD:+[SET] (oculto)}"

# 4.1 Status wsrep em cada pod
echolog "4.1 wsrep status em cada pod mariadb.*"
set +e
mapfile -t pods < <(kubectl_cmd get pods -n "$NAMESPACE" -l app=mariadb -o name || true)
set -e
if [ ${#pods[@]} -eq 0 ]; then
  echo "Nenhum pod mariadb encontrado no namespace $NAMESPACE"
else
  for p in "${pods[@]}"; do
    echo ">>> $p"
    # tenta executar mysql dentro do pod; se mysql não existir, reporta
    if kubectl_cmd exec -n "$NAMESPACE" "$p" -- bash -c 'command -v mysql >/dev/null 2>&1' >/dev/null 2>&1; then
      kubectl_cmd exec -n "$NAMESPACE" "$p" -- bash -c "mysql -uroot -p'${MYSQL_ROOT_PASSWORD}' -e \"SHOW STATUS LIKE 'wsrep_cluster_size'; SHOW STATUS LIKE 'wsrep_local_state_comment';\" || true"
    else
      echo "mysql client NÃO disponível no pod $p (use client temporário a seguir)"
    fi
  done
fi

# 4.2 Teste de escrita/leitura via pods (usa client temporário se necessário)
echolog "4.2 Teste de escrita/leitura entre nós (cria DB temporário check_galera)"
if [ ${#pods[@]} -ge 1 ]; then
  # prefer mariadb-0 e mariadb-2 se existirem
  POD0="${pods[0]#pod/}"
  POD2="${pods[2]#pod/}" || POD2="$POD0"
  # detecta se pod tem mysql, senão usa pod ephemeral mysql:8
  if kubectl_cmd exec -n "$NAMESPACE" "$pods[0]" -- bash -c 'command -v mysql >/dev/null 2>&1' >/dev/null 2>&1; then
    kubectl_cmd exec -n "$NAMESPACE" "$pods[0]" -- bash -c "mysql -uroot -p'${MYSQL_ROOT_PASSWORD}' -e \"CREATE DATABASE IF NOT EXISTS check_galera; CREATE TABLE IF NOT EXISTS check_galera.t (id INT PRIMARY KEY); INSERT IGNORE INTO check_galera.t VALUES (1);\""
    kubectl_cmd exec -n "$NAMESPACE" "${POD2}" -- bash -c "mysql -uroot -p'${MYSQL_ROOT_PASSWORD}' -e \"SELECT * FROM check_galera.t;\" || true"
  else
    echolog "Usando pod temporário mysql:8 para executar testes SQL"
    kubectl_cmd run -n "$NAMESPACE" tmp-client --rm -i --restart=Never --image=mysql:8.0 -- \
      bash -c "mysql -h mariadb-app.$NAMESPACE.svc.cluster.local -P 3306 -uroot -p'${MYSQL_ROOT_PASSWORD}' -e \"CREATE DATABASE IF NOT EXISTS check_galera; CREATE TABLE IF NOT EXISTS check_galera.t (id INT PRIMARY KEY); INSERT IGNORE INTO check_galera.t VALUES (1); SELECT * FROM check_galera.t;\""
  fi
fi

# 4.3 Medir latência simples entre pods (query time)
echolog "4.3 Medir latência simples (executar SELECT 1 entre pods)"
if [ ${#pods[@]} -ge 2 ]; then
  SRC_POD="${pods[0]#pod/}"
  DST_HOST="mariadb-1.mariadb.$NAMESPACE.svc.cluster.local"
  # usa /usr/bin/time se disponível no pod ou usa client temporário
  if kubectl_cmd exec -n "$NAMESPACE" "${pods[0]}" -- bash -c 'command -v mysql >/dev/null 2>&1 && command -v /usr/bin/time >/dev/null 2>&1' >/dev/null 2>&1; then
    kubectl_cmd exec -n "$NAMESPACE" "${SRC_POD}" -- bash -c "TIMEFORMAT='%3R'; /usr/bin/time -f '%e s' bash -c 'mysql -uroot -p\"${MYSQL_ROOT_PASSWORD}\" -h $DST_HOST -e \"SELECT 1\"' || echo conn_failed"
  else
    echolog "Usando pod temporário mysql:8 para medir tempo remoto"
    kubectl_cmd run -n "$NAMESPACE" tmp-client --rm -i --restart=Never --image=mysql:8.0 -- \
      bash -c "time mysql -h $DST_HOST -P 3306 -uroot -p'${MYSQL_ROOT_PASSWORD}' -e 'SELECT 1' >/dev/null 2>&1 || echo conn_failed"
  fi
fi

# 4.4 Testes via Service inside cluster (clusterIP)
echolog "4.4 Teste via Service clusterIP mariadb-app"
kubectl_cmd run -n "$NAMESPACE" tmp-client --rm -i --restart=Never --image=mysql:8.0 -- \
  bash -c "mysql -h mariadb-app.$NAMESPACE.svc.cluster.local -P 3306 -uroot -p'${MYSQL_ROOT_PASSWORD}' -e 'SELECT @@hostname, NOW();' || echo 'service connect failed'"

# 4.5 Testes de fora do cluster (via LoadBalancer IP)
echolog "4.5 Testes via LoadBalancer IP $LB_IP"
echo "-> Teste TCP connect (nc) to $LB_IP:3306"
nc -zv -w 3 "$LB_IP" 3306 || echo "TCP connect falhou (verifique MetalLB/firewall)"

if command -v mysql >/dev/null 2>&1; then
  echolog "-> Teste mysql client externo (local machine -> LB)"
  time mysql -h "$LB_IP" -P 3306 -uroot -p"$MYSQL_ROOT_PASSWORD" -e "SELECT 1;" || echo "mysql connection failed"
else
  echolog "mysql client local não encontrado; pulando teste mysql local"
fi

# 4.6 DNS tests
echolog "4.6 DNS checks para $ING_HOST e serviço mariadb-app"
if command -v dig >/dev/null 2>&1; then
  dig +short "$ING_HOST" || true
fi
kubectl_cmd exec -n "$NAMESPACE" --namespace "$NAMESPACE" -- bash -c "echo 'DNS from inside cluster:'; nslookup mariadb-app.$NAMESPACE.svc.cluster.local 2>/dev/null || true" || true

# 4.7 Mostrar svc & endpoints do MariaDB
echolog "4.7 Mostrar services e endpoints relacionados ao MariaDB"
kubectl_cmd get svc -n "$NAMESPACE" -o wide | egrep 'mariadb|mariadb-lb' || true
kubectl_cmd get endpoints -n "$NAMESPACE" mariadb-app -o yaml || true

# ----------------- FASE 5: INGRESS / API CHECKS -----------------
echolog "FASE 5: Ingress e API checks"

echolog "5.1 Verificar ingress resource e regras"
kubectl_cmd get ingress -n "$NAMESPACE" -o yaml || true
kubectl_cmd describe ingress -n "$NAMESPACE" || true

echolog "5.2 Testar rota interna do serviço via curl (dentro do cluster)"
# busca um pod da api
API_POD=$(kubectl_cmd get pods -n "$NAMESPACE" -l app=uniselec-api -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || true)
if [ -n "$API_POD" ]; then
  echolog "Pod API: $API_POD - health endpoint /up"
  kubectl_cmd exec -n "$NAMESPACE" "$API_POD" -- bash -c "curl -s -o /dev/null -w '%{http_code} %{time_total}s\n' http://localhost/up || echo 'api-local-curl-failed'"
else
  echo "Nenhum pod API encontrado (app=uniselec-api)."
fi

echolog "5.3 Teste externo via ingress host (curl) - timing e status"
if command -v curl >/dev/null 2>&1; then
  curl -s -o /dev/null -w "HTTP:%{http_code} TOTAL:%{time_total}s\n" "https://$ING_HOST/api/health" || echo "curl externo falhou"
else
  echo "curl não disponível localmente"
fi

# ----------------- FASE 6: NETWORK / DNS / COREDNS -----------------
echolog "FASE 6: Verificar CoreDNS / DNS interno"

kubectl_cmd get pods -n kube-system -l k8s-app=kube-dns || kubectl_cmd get pods -n kube-system -l k8s-app=coredns || true
kubectl_cmd logs -n kube-system -l k8s-app=kube-dns --tail=50 2>/dev/null || kubectl_cmd logs -n kube-system -l k8s-app=coredns --tail=50 2>/dev/null || echo "Não encontrei logs do coredns no cluster"

echolog "FASE 6.1: Teste DNS latência dentro do cluster (nslookup timing)"
kubectl_cmd run -n "$NAMESPACE" tmp-dns --rm -i --restart=Never --image=busybox -- nslookup mariadb-app.$NAMESPACE.svc.cluster.local || true

# ----------------- FASE 7: COLETA RÁPIDA (EVENTOS / METRICS / TOP) -----------------
echolog "FASE 7: Coleta Rápida - eventos / uso recursos"

echolog "Eventos recentes (namespace)"
kubectl_cmd get events -n "$NAMESPACE" --sort-by='.lastTimestamp' | tail -n 50 || true

echolog "Top nodes/pods (se disponível)"
kubectl_cmd top nodes || true
kubectl_cmd top pods -n "$NAMESPACE" || true

# ----------------- FINAL: RESUMO E SUGESTÕES -----------------
echolog "RESUMO"
cat <<'EOF'
- Verifique mensagens de wsrep_local_state_comment (Synced/Donor/Joined).
- Se mysql client não existir nos pods, o script usou pod temporários mysql:8 para testes.
- Se LoadBalancer conecta externo mas consultas internas falham: verificar NetworkPolicy/CoreDNS/iptables no node.
- Se HAProxy mostra erros de config no journal, revise /tmp/haproxy-ingress/etc/haproxy.cfg e logs.
- Para análise de pcap, use tcpdump dentro de um pod e abra com Wireshark localmente.
EOF

echolog "FIM do diagnóstico automatizado."

# Exit normally
exit 0
