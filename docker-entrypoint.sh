#!/bin/bash
# Deshabilitar MPMs conflictivos
a2dismod mpm_event mpm_worker 2>/dev/null || true
a2enmod mpm_prefork 2>/dev/null || true
a2enmod rewrite 2>/dev/null || true

# Iniciar Apache
apache2-foreground