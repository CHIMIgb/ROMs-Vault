#!/usr/bin/env pwsh
<#
.SYNOPSIS
    Purga imagenes antiguas del Vercel Container Registry (VCR).

.DESCRIPTION
    Conserva las N imagenes mas recientes del repositorio VCR del proyecto y
    borra el resto.

    Vercel NO tiene retencion automatica: cada deploy (Preview y produccion)
    empuja una imagen nueva al registry y el plan Hobby limita a 50 imagenes por
    repositorio. Al alcanzar el tope, el push del build se rechaza con el error
    "denied: repository has reached the maximum allowed number of images" y el
    deploy falla. Este script libera espacio de forma segura e idempotente.

    Ver docs/2026-10-11-retencion-imagenes-vercel-vcr.md.

.PARAMETER Repo
    Nombre del repositorio VCR. Defecto: "dockerfile" (o $env:VCR_REPO).

.PARAMETER Keep
    Numero de imagenes mas recientes a conservar. Defecto: 5 (o $env:VCR_KEEP).

.PARAMETER Project
    Proyecto Vercel. Opcional en local (se usa el .vercel linkeado);
    necesario en CI. Defecto: $env:VERCEL_PROJECT.

.PARAMETER Scope
    Team/slug de Vercel. Opcional en local; necesario en CI. Defecto: $env:VERCEL_SCOPE.

.PARAMETER Token
    Token de Vercel. Opcional en local (usa la sesion); en CI: $env:VERCEL_TOKEN.

.PARAMETER DryRun
    Muestra que imagenes se borrarian sin ejecutar ningun borrado.

.EXAMPLE
    ./scripts/prune-vcr-images.ps1 -DryRun
    Previsualiza el borrado conservando las 5 mas recientes.

.EXAMPLE
    ./scripts/prune-vcr-images.ps1 -Keep 3
    Conserva solo las 3 mas recientes y borra el resto.
#>
[CmdletBinding()]
param(
    [string]$Repo,
    [int]$Keep = 0,
    [string]$Project,
    [string]$Scope,
    [string]$Token,
    [switch]$DryRun
)

# No usar 'Stop' global: en Windows PowerShell 5.1 el stderr de un comando nativo
# (el wrapper de `vercel` escribe "Retrieving project..." en stderr) se envuelve
# como ErrorRecord y abortaria el script. Los fallos se controlan con $LASTEXITCODE.
$ErrorActionPreference = 'Continue'

# Evita el prompt interactivo de actualizacion del Vercel CLI en ejecucion no interactiva.
$env:CI = '1'

# Resolucion de valores: parametro > variable de entorno > constante.
if (-not $Repo) { $Repo = if ($env:VCR_REPO) { $env:VCR_REPO } else { 'dockerfile' } }
if ($Keep -le 0) { $Keep = if ($env:VCR_KEEP) { [int]$env:VCR_KEEP } else { 5 } }
if (-not $Project) { $Project = $env:VERCEL_PROJECT }
if (-not $Scope) { $Scope = $env:VERCEL_SCOPE }
if (-not $Token) { $Token = $env:VERCEL_TOKEN }

if (-not (Get-Command vercel -ErrorAction SilentlyContinue)) {
    Write-Error "No se encontro el CLI 'vercel' en el PATH. Instalalo con: npm i -g vercel"
    exit 1
}

# Ejecutar desde la raiz del repo para que .vercel/ (proyecto linkeado) se resuelva
# aunque el script se invoque desde otro directorio.
$RepoRoot = Split-Path -Parent $PSScriptRoot
Push-Location $RepoRoot
try {
    # Argumentos de scoping comunes a los comandos `vercel vcr`.
    $scopeArgs = @()
    if ($Project) { $scopeArgs += @('--project', $Project) }
    if ($Scope) { $scopeArgs += @('--scope', $Scope) }
    if ($Token) { $scopeArgs += @('--token', $Token) }

    Write-Host "Repositorio VCR: $Repo | conservar: $Keep | dry-run: $([bool]$DryRun)"

    # 1) Listar imagenes (max. 100 por pagina; sobra para el tope de 50 de Hobby).
    $lsArgs = @('vcr', 'image', 'ls', $Repo, '--limit', '100', '--format', 'json') + $scopeArgs
    $raw = (& vercel @lsArgs 2>&1 | Out-String)

    if ($LASTEXITCODE -ne 0) {
        Write-Error "El comando 'vercel vcr image ls' fallo (exit $LASTEXITCODE). Sesion iniciada? Prueba 'vercel login'."
        Write-Host $raw
        exit 1
    }

    $match = [regex]::Match($raw, '(?s)\{.*\}')
    if (-not $match.Success) {
        Write-Error "No se pudo leer el JSON de imagenes. Salida del CLI:"
        Write-Host $raw
        exit 1
    }
    $images = @(($match.Value | ConvertFrom-Json).images)

    $total = $images.Count
    Write-Host "Imagenes actuales: $total"
    if ($total -le $Keep) {
        Write-Host "Nada que hacer: hay $total imagen(es) y se conservan $Keep."
        exit 0
    }

    # 2) Ordenar por fecha desc; las que sobran (mas antiguas) son las que se borran.
    $ordered = $images | Sort-Object { [datetime]$_.createdAt } -Descending
    $toDelete = @($ordered | Select-Object -Skip $Keep)

    Write-Host "Se conservan las $Keep mas recientes. A borrar: $($toDelete.Count)"
    foreach ($img in $toDelete) {
        $tag = if ($img.tags) { @($img.tags) -join ',' } else { '<none>' }
        Write-Host ("  - {0}  [{1}]  {2}" -f $img.id, $tag, $img.createdAt)
    }

    if ($DryRun) {
        Write-Host "Dry-run: no se borro nada."
        exit 0
    }

    # 3) Borrar (irreversible, pero Vercel reconstruye cualquier imagen desde git).
    $ok = 0
    $fail = 0
    foreach ($img in $toDelete) {
        $rmArgs = @('vcr', 'image', 'rm', $Repo, $img.id, '--yes') + $scopeArgs
        $out = (& vercel @rmArgs 2>&1 | Out-String)
        if ($LASTEXITCODE -eq 0) {
            $ok++
        }
        else {
            $fail++
            Write-Warning "Fallo el borrado de $($img.id): $($out.Trim())"
        }
    }

    Write-Host "Borradas: $ok | Fallidas: $fail"
    if ($fail -gt 0) { exit 1 }
    exit 0
}
finally {
    Pop-Location
}
