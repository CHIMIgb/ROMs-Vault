# Script para arrancar LibreTranslate con 1 worker (preservando modelos)
# Uso: powershell.exe -NoProfile -ExecutionPolicy Bypass -File "scripts\libretranslate-start.ps1"

$d = 'C:\Users\chimi\AppData\Local\Programs\DockerDesktop\resources\bin\docker.exe'
$img = 'romv-libretranslate:models'

Write-Host '=== LibreTranslate: iniciar con LT_THREADS=1 ===' -ForegroundColor Cyan

# Verificar imagen existe
$imgs = & $d images --format '{{.Repository}}:{{.Tag}}' | Where-Object { $_ -eq $img }
if (-not $imgs) {
  Write-Host "ERROR: No existe $img. Ejecuta docker commit primero o usa libretranslate/libretranslate:latest." -ForegroundColor Red
  exit 1
}

# Eliminar contenedor si existe
& $d rm -f libretranslate 2>$null | Out-Null

Write-Host 'Arrancando contenedor...' -ForegroundColor Green
& $d run -d --name libretranslate -p 5000:5000 `
  -e LT_LOAD_ONLY=es,en,fr,pt,de,ja,it,ru,tr,zh,ko,ar `
  -e LT_THREADS=1 `
  $img

if ($LASTEXITCODE -ne 0) {
  Write-Host 'Fallo al arrancar contenedor' -ForegroundColor Red
  exit 1
}

Write-Host 'Esperando /languages (hasta 120s)...' -ForegroundColor Yellow
$ok = $false
for ($i = 1; $i -le 120; $i++) {
  try {
    $r = Invoke-RestMethod -Uri 'http://localhost:5000/languages' -TimeoutSec 5
    $codes = ($r | ForEach-Object { $_.code }) -join ', '
    Write-Host ('OK [' + $i*5 + 's]: ' + $codes) -ForegroundColor Green
    $ok = $true
    break
  } catch {
    Start-Sleep -Seconds 5
  }
}

if (-not $ok) {
  Write-Host 'No responde /languages. Revisar logs:' -ForegroundColor Red
  & $d logs --tail 30 libretranslate
  exit 1
}

# Estado
Write-Host '--- Estado ---' -ForegroundColor Cyan
& $d ps --filter name=libretranslate --format '{{.Names}} {{.Status}}'
& $d stats libretranslate --no-stream --format 'MEM={{.MemUsage}} CPU={{.CPUPerc}}'
Write-Host 'Listo. API: http://localhost:5000' -ForegroundColor Green
