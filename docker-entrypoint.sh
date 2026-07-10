#!/bin/bash
# Deshabilitar MPMs conflictivos
a2dismod mpm_event mpm_worker 2>/dev/null || true
a2enmod mpm_prefork 2>/dev/null || true
a2enmod rewrite 2>/dev/null || true

# Configurar Apache para usar el puerto asignado por Vercel
if [ -n "$PORT" ]; then
    sed -i "s/80/$PORT/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf
fi

# Generar un archivo .env en tiempo de ejecución con las variables frescas de Vercel
# Esto evita que Apache oculte las variables de entorno a PHP y sobreescribe cualquier .env viejo.
cat <<EOF > /var/www/html/.env
DB_HOST="${DB_HOST}"
DB_PORT="${DB_PORT:-5432}"
DB_NAME="${DB_NAME}"
DB_USER="${DB_USER}"
DB_PASSWORD="${DB_PASSWORD}"
SESSION_SECRET="${SESSION_SECRET}"
JWT_SECRET="${JWT_SECRET}"
JWT_EXPIRATION="${JWT_EXPIRATION:-3600}"
JWT_REFRESH_THRESHOLD="${JWT_REFRESH_THRESHOLD:-600}"
GOOGLE_CLIENT_ID="${GOOGLE_CLIENT_ID}"
GOOGLE_CLIENT_SECRET="${GOOGLE_CLIENT_SECRET}"
RATE_LIMIT_MAX="${RATE_LIMIT_MAX:-30}"
RATE_LIMIT_WINDOW="${RATE_LIMIT_WINDOW:-60}"
ALLOWED_ORIGINS="${ALLOWED_ORIGINS}"
EOF

# Iniciar Apache
apache2-foreground