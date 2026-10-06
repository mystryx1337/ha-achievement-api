# PowerShell Packaging-Skript für HA Achievement API
$ErrorActionPreference = 'Stop'

$PluginSlug = 'ha-achievement-api'
$PluginDir = Split-Path -Parent $PSScriptRoot
$ReleaseDir = Join-Path $PluginDir 'release'
$TempDir = Join-Path $ReleaseDir $PluginSlug

Write-Host "=== Verpacke $PluginSlug ===" -ForegroundColor Cyan

# 1. Release-Verzeichnis vorbereiten
if (Test-Path $ReleaseDir) {
    Remove-Item -Recurse -Force $ReleaseDir
}
New-Item -ItemType Directory -Force -Path $TempDir | Out-Null

# 2. Relevante Dateien kopieren
$IncludeItems = @(
    'ha-achievement-api.php',
    'uninstall.php',
    'README.md',
    'DEVELOPER_GUIDE.md',
    'examples',
    'includes',
    'templates',
    'assets',
    'languages'
)

foreach ($item in $IncludeItems) {
    $src = Join-Path $PluginDir $item
    if (Test-Path $src) {
        Copy-Item -Recurse -Force $src (Join-Path $TempDir $item)
        Write-Host "  + Kopiert: $item" -ForegroundColor Green
    }
}

# 3. Zip-Archiv erstellen
$ZipFile = Join-Path $ReleaseDir "$PluginSlug-v1.0.0.zip"
Compress-Archive -Path $TempDir -DestinationPath $ZipFile -Force
Remove-Item -Recurse -Force $TempDir

Write-Host "=== ERFOLG: $ZipFile erstellt! ===" -ForegroundColor Yellow
