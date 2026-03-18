#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
RESULTS_DIR="$SCRIPT_DIR/results"

SCENARIO="users"
MODE="${1:-compare}"
SCENARIO_FILE="$SCRIPT_DIR/scenarios/${SCENARIO}.js"

FPM_PORT=8080
FRANKEN_PORT=8081
K6_IMAGE="grafana/k6:latest"
SETTLE_SECONDS=5

if [ ! -f "$SCENARIO_FILE" ]; then
  echo "ERROR: Scenario not found: $SCENARIO_FILE"
  echo "Available: $(ls "$SCRIPT_DIR/scenarios/"*.js 2>/dev/null | xargs -I{} basename {} .js | tr '\n' ' ')"
  exit 1
fi

mkdir -p "$RESULTS_DIR"

fpm_up() {
  echo "[stack] Starting Nginx + PHP-FPM..."
  cd "$PROJECT_DIR" && docker compose up -d
  wait_for_service "http://localhost:$FPM_PORT" "PHP-FPM"
}

fpm_down() {
  echo "[stack] Stopping Nginx + PHP-FPM..."
  cd "$PROJECT_DIR" && docker compose down
}

fpm_db_fresh() {
  echo "[db] Resetting FPM database..."
  cd "$PROJECT_DIR" && docker compose exec php php bin/console cache:clear
  cd "$PROJECT_DIR" && docker compose exec php php bin/console doctrine:database:drop --force --if-exists
  cd "$PROJECT_DIR" && docker compose exec php php bin/console doctrine:database:create
  cd "$PROJECT_DIR" && docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
}

franken_up() {
  echo "[stack] Starting FrankenPHP..."
  cd "$PROJECT_DIR" && docker compose -f docker-compose.franken.yml up -d
  wait_for_service "http://localhost:$FRANKEN_PORT" "FrankenPHP"
}

franken_down() {
  echo "[stack] Stopping FrankenPHP..."
  cd "$PROJECT_DIR" && docker compose -f docker-compose.franken.yml down
}

franken_db_fresh() {
  echo "[db] Resetting FrankenPHP database..."
  cd "$PROJECT_DIR" && docker compose -f docker-compose.franken.yml exec frankenphp php bin/console cache:clear
  cd "$PROJECT_DIR" && docker compose -f docker-compose.franken.yml exec frankenphp php bin/console doctrine:database:drop --force --if-exists
  cd "$PROJECT_DIR" && docker compose -f docker-compose.franken.yml exec frankenphp php bin/console doctrine:database:create
  cd "$PROJECT_DIR" && docker compose -f docker-compose.franken.yml exec frankenphp php bin/console doctrine:migrations:migrate --no-interaction
}

wait_for_service() {
  local url="$1"
  local label="$2"
  local max_wait=60
  local waited=0

  echo -n "  Waiting for $label at $url "
  while ! curl -sf "$url/api/health" > /dev/null 2>&1; do
    sleep 1
    waited=$((waited + 1))
    if [ $waited -ge $max_wait ]; then
      echo " TIMEOUT (${max_wait}s)"
      echo "ERROR: $label not responding."
      exit 1
    fi
    echo -n "."
  done
  echo " OK (${waited}s)"
}

run_k6() {
  local label="$1"
  local base_url="$2"
  local log_file="$RESULTS_DIR/${label}_${SCENARIO}.log"

  echo ""
  echo "============================================"
  echo "  k6: $label | scenario: $SCENARIO"
  echo "  target: $base_url"
  echo "============================================"
  echo ""

  docker run --rm \
    --add-host=host.docker.internal:host-gateway \
    -v "$SCRIPT_DIR/scenarios:/scenarios:ro" \
    -e "BASE_URL=$base_url" \
    "$K6_IMAGE" run \
    --summary-trend-stats="avg,min,med,max,p(90),p(95),p(99)" \
    "/scenarios/${SCENARIO}.js" \
    2>&1 | tee "$log_file" || true

  echo ""
  echo "  Log: $log_file"
}

settle() {
  echo ""
  echo "[wait] Settling for ${SETTLE_SECONDS}s..."
  sleep "$SETTLE_SECONDS"
}

print_separator() {
  echo ""
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
}

extract_metric() {
  local file="$1" pattern="$2" field="$3"
  local escaped_field
  escaped_field=$(echo "$field" | sed 's/[()]/\\&/g')
  grep -E "^\s+${pattern}" "$file" 2>/dev/null \
    | grep -v 'expected_response' \
    | head -1 \
    | grep -oE "${escaped_field}=[^ ]+" \
    | head -1 \
    | sed 's/^.*=//'
}

extract_rate_metric() {
  local file="$1" pattern="$2"
  grep -E "^\s+${pattern}" "$file" 2>/dev/null \
    | head -1 \
    | awk '{for(i=1;i<=NF;i++) if($i ~ /^[0-9]+\.[0-9]+%$/ || $i ~ /^[0-9]+%$/) {print $i; exit}}'
}

extract_error_count() {
  local file="$1" pattern="$2"
  grep -E "^\s+${pattern}" "$file" 2>/dev/null \
    | head -1 \
    | grep -oE '[0-9]+ out of' \
    | awk '{print $1}'
}

extract_count_metric() {
  local file="$1" pattern="$2"
  grep -E "^\s+${pattern}" "$file" 2>/dev/null \
    | grep -v 'expected_response' \
    | head -1 \
    | awk -F':' '{print $NF}' \
    | awk '{print $1}'
}

extract_rps() {
  local file="$1" pattern="$2"
  grep -E "^\s+${pattern}" "$file" 2>/dev/null \
    | grep -v 'expected_response' \
    | head -1 \
    | grep -oE '[0-9]+\.[0-9]+/s' \
    | head -1
}

to_ms() {
  local val="$1"
  if echo "$val" | grep -qE 'ms$'; then
    echo "$val" | sed 's/ms$//'
  elif echo "$val" | grep -qE 's$'; then
    echo "$val" | sed 's/s$//' | awk '{printf "%.2f", $1 * 1000}'
  elif echo "$val" | grep -qE '%$'; then
    echo "$val" | sed 's/%$//'
  else
    echo "$val"
  fi
}

# calc_delta old_val new_val [lower_is_better]
# - higher is better (rps, reqs): d = (new - old) / old
# - lower is better (latency):   d = (old - new) / new  → "old is X% slower"
calc_delta() {
  local v1="$1" v2="$2" lower="${3:-}"
  if [ -z "$v1" ] || [ -z "$v2" ]; then echo "—"; return; fi
  local n1 n2
  n1=$(to_ms "$v1")
  n2=$(to_ms "$v2")
  if [ -z "$n1" ] || [ -z "$n2" ]; then echo "—"; return; fi
  if [ "$lower" = "lower" ]; then
    awk -v a="$n1" -v b="$n2" 'BEGIN {
      if (b == 0) { print "—"; exit }
      d = ((a - b) / b) * 100
      if (d > 0) printf "+%.0f%%\n", d
      else if (d < 0) printf "%.0f%%\n", d
      else print "0%"
    }'
  else
    awk -v a="$n1" -v b="$n2" 'BEGIN {
      if (a == 0) { print "—"; exit }
      d = ((b - a) / a) * 100
      if (d > 0) printf "+%.0f%%\n", d
      else if (d < 0) printf "%.0f%%\n", d
      else print "0%"
    }'
  fi
}

print_row() {
  local label="$1" fpm_val="$2" frank_val="$3" delta="$4"
  printf "  %-28s  %14s  %14s  %8s\n" "$label" "${fpm_val:---}" "${frank_val:---}" "${delta:---}"
}

print_comparison() {
  local fpm_log="$RESULTS_DIR/fpm_${SCENARIO}.log"
  local franken_log="$RESULTS_DIR/franken_${SCENARIO}.log"

  if [ ! -f "$fpm_log" ] || [ ! -f "$franken_log" ]; then
    echo "  ERROR: Missing log files in $RESULTS_DIR/"
    return
  fi

  local fpm_reqs franken_reqs fpm_rps franken_rps fpm_err franken_err
  fpm_reqs=$(extract_count_metric "$fpm_log" "http_reqs\.")
  franken_reqs=$(extract_count_metric "$franken_log" "http_reqs\.")
  fpm_rps=$(extract_rps "$fpm_log" "http_reqs\.")
  franken_rps=$(extract_rps "$franken_log" "http_reqs\.")
  fpm_err=$(extract_rate_metric "$fpm_log" "http_req_failed\.")
  franken_err=$(extract_rate_metric "$franken_log" "http_req_failed\.")
  local fpm_err_cnt franken_err_cnt
  fpm_err_cnt=$(extract_error_count "$fpm_log" "http_req_failed\.")
  franken_err_cnt=$(extract_error_count "$franken_log" "http_req_failed\.")

  echo ""
  print_separator
  echo ""
  echo "  BENCHMARK COMPARISON — scenario: $SCENARIO"
  echo ""
  printf "  %-28s  %14s  %14s  %8s\n" "" "Nginx+FPM" "FrankenPHP" "Delta"
  echo "  ────────────────────────────────────────────────────────────────────"

  print_row "Total requests" "$fpm_reqs" "$franken_reqs" "$(calc_delta "$fpm_reqs" "$franken_reqs")"
  print_row "Requests/sec" "$fpm_rps" "$franken_rps" "$(calc_delta "$fpm_rps" "$franken_rps")"
  print_row "Errors" "${fpm_err_cnt:-0}req ${fpm_err}" "${franken_err_cnt:-0}req ${franken_err}" "$(calc_delta "$fpm_err" "$franken_err")"

  echo "  ────────────────────────────────────────────────────────────────────"
  echo "  HTTP response time"

  for stat in avg med "p(95)" "p(99)"; do
    local fv fkv
    fv=$(extract_metric "$fpm_log" "http_req_duration\." "$stat")
    fkv=$(extract_metric "$franken_log" "http_req_duration\." "$stat")
    print_row "  $stat" "$fv" "$fkv" "$(calc_delta "$fv" "$fkv" lower)"
  done

  local metrics="create_user_duration apply_loan_duration make_payment_duration get_loan_duration list_users_duration health_check_duration"

  local has_custom=false
  for m in $metrics; do
    if grep -qE "^\s+${m}" "$fpm_log" 2>/dev/null; then
      has_custom=true
      break
    fi
  done

  if $has_custom; then
    echo "  ────────────────────────────────────────────────────────────────────"
    echo "  Custom metrics (avg / p95)"

    for m in $metrics; do
      if ! grep -qE "^\s+${m}" "$fpm_log" 2>/dev/null; then continue; fi
      local short_name
      short_name=$(echo "$m" | sed 's/_duration$//' | sed 's/_/ /g')

      local fpm_avg fpm_p95 frank_avg frank_p95
      fpm_avg=$(extract_metric "$fpm_log" "${m}\." "avg")
      frank_avg=$(extract_metric "$franken_log" "${m}\." "avg")
      fpm_p95=$(extract_metric "$fpm_log" "${m}\." "p(95)")
      frank_p95=$(extract_metric "$franken_log" "${m}\." "p(95)")

      print_row "  $short_name avg" "$fpm_avg" "$frank_avg" "$(calc_delta "$fpm_avg" "$frank_avg" lower)"
      print_row "  $short_name p95" "$fpm_p95" "$frank_p95" "$(calc_delta "$fpm_p95" "$frank_p95" lower)"
    done
  fi

  echo "  ────────────────────────────────────────────────────────────────────"

  local fpm_iter_avg franken_iter_avg fpm_iter_rps franken_iter_rps
  fpm_iter_avg=$(extract_metric "$fpm_log" "iteration_duration\." "avg")
  franken_iter_avg=$(extract_metric "$franken_log" "iteration_duration\." "avg")
  fpm_iter_rps=$(extract_rps "$fpm_log" "iterations\.")
  franken_iter_rps=$(extract_rps "$franken_log" "iterations\.")

  print_row "Iteration avg" "$fpm_iter_avg" "$franken_iter_avg" "$(calc_delta "$fpm_iter_avg" "$franken_iter_avg" lower)"
  print_row "Iterations/sec (total)" "$fpm_iter_rps" "$franken_iter_rps" "$(calc_delta "$fpm_iter_rps" "$franken_iter_rps")"

  print_separator
  echo ""
  echo "  Full logs: $RESULTS_DIR/"
  echo ""
}

case "$MODE" in
  fpm)
    franken_down 2>/dev/null || true
    fpm_up
    fpm_db_fresh
    settle
    run_k6 "fpm" "http://host.docker.internal:$FPM_PORT"
    ;;

  franken)
    fpm_down 2>/dev/null || true
    franken_up
    franken_db_fresh
    settle
    run_k6 "franken" "http://host.docker.internal:$FRANKEN_PORT"
    ;;

  compare)
    echo ""
    echo "╔══════════════════════════════════════════════════════════╗"
    echo "║          FPM vs FrankenPHP Benchmark                     ║"
    echo "║          Scenario: $SCENARIO"
    echo "║                                                          ║"
    echo "║  Each stack runs in isolation (the other is stopped).    ║"
    echo "║  DB is reset before each run for a fair comparison.      ║"
    echo "╚══════════════════════════════════════════════════════════╝"
    echo ""

    # --- Phase 1: FPM ---
    echo "═══ Phase 1/2: Nginx + PHP-FPM ═══"
    franken_down 2>/dev/null || true
    fpm_up
    fpm_db_fresh
    settle
    run_k6 "fpm" "http://host.docker.internal:$FPM_PORT"
    fpm_down

    echo ""
    echo "[wait] Cooldown ${SETTLE_SECONDS}s before next phase..."
    sleep "$SETTLE_SECONDS"

    # --- Phase 2: FrankenPHP ---
    echo "═══ Phase 2/2: FrankenPHP Worker ═══"
    franken_up
    franken_db_fresh
    settle
    run_k6 "franken" "http://host.docker.internal:$FRANKEN_PORT"
    franken_down

    # --- Results ---
    print_comparison
    ;;

  *)
    echo "Usage: $0 [fpm|franken|compare]"
    echo ""
    echo "  mode:  fpm      — benchmark FPM only (stops FrankenPHP first)"
    echo "         franken  — benchmark FrankenPHP only (stops FPM first)"
    echo "         compare  — both sequentially, then compare (default)"
    echo ""
    echo "Scenario: users (fixed). Example: $0 compare"
    exit 1
    ;;
esac
