#!/bin/bash
set -e

# Create uploads directory (Railway persistent volume mounted here)
mkdir -p /app/uploads

# Decode Google credentials JSON from env var (base64 encoded)
if [ -n "$GOOGLE_CREDENTIALS_JSON" ]; then
    mkdir -p /app/Portal
    echo "$GOOGLE_CREDENTIALS_JSON" | base64 -d > /app/Portal/google-credentials.json
fi

# Start PHP-FPM in background
php-fpm -D

# Start Nginx in foreground (keeps container alive)
exec nginx -g 'daemon off;'
