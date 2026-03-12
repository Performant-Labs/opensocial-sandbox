#!/usr/bin/env bash
# kill-zombies.sh — Kill orphan processes that can interfere with build phases.
# Run before starting each phase.
#
# Usage: bash scripts/kill-zombies.sh [--dry-run]

set -euo pipefail

DRY_RUN=false
[[ "${1:-}" == "--dry-run" ]] && DRY_RUN=true

KILLED=0

kill_pattern() {
  local label="$1"
  local pattern="$2"
  local pids
  pids=$(pgrep -f "$pattern" 2>/dev/null || true)
  if [[ -n "$pids" ]]; then
    echo "  ⚠️  $label:"
    while IFS= read -r pid; do
      local cmd
      cmd=$(ps -p "$pid" -o command= 2>/dev/null | head -c 120)
      if $DRY_RUN; then
        echo "      [dry-run] would kill PID $pid — $cmd"
      else
        kill "$pid" 2>/dev/null && echo "      killed PID $pid — $cmd" || echo "      PID $pid already gone"
      fi
      ((KILLED++))
    done <<< "$pids"
  else
    echo "  ✅ $label: none found"
  fi
}

echo ""
echo "🧹 Zombie Process Cleanup"
echo "========================="
echo ""

# 1. Playwright test runners
kill_pattern "Playwright (node)" "node.*playwright"

# 2. Chromium browser instances (spawned by Playwright)
kill_pattern "Chromium browsers" "chromium --"

# 3. Orphan Drush processes (outside DDEV containers — host-side wrappers)
kill_pattern "Orphan Drush (host)" "drush.*--backend"

# 4. Orphan PHP processes (host-side, not containerised)
kill_pattern "Orphan PHP (host)" "php.*drush"

# 5. Orphan Composer processes (host-side)
kill_pattern "Orphan Composer" "composer.*require\|composer.*install\|composer.*update"

# 6. Stale node dev-servers (Vite, webpack, etc.)
kill_pattern "Node dev servers" "node.*vite\|node.*webpack"

# 7. Orphan curl/wget left from downloads
kill_pattern "Orphan curl/wget" "curl.*ddev\|wget.*ddev"

echo ""

# DDEV container health check (informational — never kills containers)
echo "🐳 DDEV Container Health"
echo "------------------------"
if command -v docker &>/dev/null; then
  docker ps --format "  {{.Names}}  {{.Status}}" --filter "name=ddev" 2>/dev/null || echo "  (docker not responding)"
else
  echo "  (docker not found)"
fi

echo ""
if [[ $KILLED -eq 0 ]]; then
  echo "✨ Clean — no zombies found."
else
  if $DRY_RUN; then
    echo "🔍 Dry run complete — $KILLED process(es) would be killed."
  else
    echo "🪦 Killed $KILLED zombie process(es)."
  fi
fi
echo ""
