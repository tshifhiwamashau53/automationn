#!/bin/bash
set -e

echo "Running after-deploy tasks..."

# Warm caches or restart services
# if command -v supervisorctl >/dev/null 2>&1; then
#   supervisorctl restart all || true
# fi

# Notify external service (example)
# curl -X POST https://your-monitoring.example.com/deploy-done || true

echo "After-deploy tasks complete."
