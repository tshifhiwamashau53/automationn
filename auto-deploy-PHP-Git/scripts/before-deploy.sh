#!/bin/bash
set -e

# Example before-deploy script (runs inside the release dir)

echo "Running before-deploy tasks..."

# Example: run migrations (uncomment if using Laravel)
# if [ -f artisan ]; then
#   php artisan migrate --force
# fi

# Example: build assets
# if [ -f package.json ]; then
#   npm install --production
#   npm run build
# fi

echo "Before-deploy tasks complete."
