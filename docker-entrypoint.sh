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
printenv > /var/www/html/.env

# Iniciar Apache
apache2-foreground