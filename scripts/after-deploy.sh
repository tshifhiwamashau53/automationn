#!/bin/bash
set -e
echo "Running after-deploy hook"
# Add commands to restart services, warm caches, etc.
# Example:
# supervisorctl restart myworkers || true
