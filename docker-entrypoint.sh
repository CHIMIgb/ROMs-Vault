#!/bin/bash
# Deshabilitar MPMs conflictivos
a2dismod mpm_event mpm_worker 2>/dev/null || true
a2enmod mpm_prefork 2>/dev/null || true
a2enmod rewrite 2>/dev/null || true

# Configurar Apache para usar el puerto asignado por Vercel
if [ -n "$PORT" ]; then
    sed -i "s/80/$PORT/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf
fi

# Iniciar Apache
apache2-foreground