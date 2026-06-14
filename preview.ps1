# =====================================================================
#  preview.ps1 - One-command local preview for BHOI Arhiva
# ---------------------------------------------------------------------
#  Spins up a self-contained preview WITHOUT touching your main XAMPP
#  MySQL (which is currently corrupt). It:
#    1. Creates a clean, private MySQL data dir on first run.
#    2. Starts that MySQL on port 3307 (your XAMPP 3306 is untouched).
#    3. Loads schema.sql + seed.sql the first time.
#    4. Serves the app with PHP's built-in server on http://localhost:8000
#    5. Opens your browser.
#
#  Just double-click  preview.bat  (or run:  powershell -File preview.ps1)
#  Stop it by closing this window, then run  stop-preview.bat.
# =====================================================================

$ErrorActionPreference = 'Stop'

# --- Paths (edit XAMPP location here if yours differs) ----------------
$Xampp      = 'C:\xampp'
$Php        = Join-Path $Xampp 'php\php.exe'
$MysqlBin   = Join-Path $Xampp 'mysql\bin'
$Mysqld     = Join-Path $MysqlBin 'mysqld.exe'
$Mysql      = Join-Path $MysqlBin 'mysql.exe'
$InstallDb  = Join-Path $MysqlBin 'mysql_install_db.exe'

$ProjectDir = $PSScriptRoot
$ParentDir  = Split-Path $ProjectDir -Parent
$DataDir    = Join-Path $ParentDir 'bhoi-preview-db'        # private DB, sibling of project
$SessDir    = Join-Path $ParentDir 'bhoi-preview-sessions'  # PHP session files

$DbPort  = 3307
$WebPort = 8000
$DbName  = 'bhoi_platform'

function Test-Port($port) {
    (Test-NetConnection 127.0.0.1 -Port $port -WarningAction SilentlyContinue).TcpTestSucceeded
}

# --- Sanity checks ----------------------------------------------------
foreach ($exe in @($Php, $Mysqld, $Mysql)) {
    if (-not (Test-Path $exe)) {
        Write-Host "ERROR: not found -> $exe" -ForegroundColor Red
        Write-Host "Edit the Xampp path at the top of preview.ps1." -ForegroundColor Yellow
        exit 1
    }
}

Write-Host "=== BHOI Arhiva - local preview ===" -ForegroundColor Cyan

# --- 1. Initialise a clean data dir on first run ----------------------
if (-not (Test-Path (Join-Path $DataDir 'mysql'))) {
    Write-Host "First run: creating a private preview database..." -ForegroundColor Yellow
    if (Test-Path $DataDir) { Remove-Item $DataDir -Recurse -Force }
    New-Item -ItemType Directory -Path $DataDir | Out-Null
    & $InstallDb --datadir="$DataDir" | Out-Null
}
New-Item -ItemType Directory -Force -Path $SessDir | Out-Null

# --- 2. Start MySQL on 3307 if it isn't already up --------------------
if (-not (Test-Port $DbPort)) {
    Write-Host "Starting preview MySQL on port $DbPort ..."
    $mysqlArgs = @(
        "--no-defaults",
        "--datadir=$DataDir",
        "--port=$DbPort",
        "--socket=bhoi_preview.sock",
        "--skip-grant-tables",
        "--console"
    )
    Start-Process -FilePath $Mysqld -ArgumentList $mysqlArgs -WindowStyle Hidden
    for ($i = 0; $i -lt 30; $i++) {
        Start-Sleep -Seconds 1
        if (Test-Port $DbPort) { break }
    }
}
if (-not (Test-Port $DbPort)) {
    Write-Host "ERROR: preview MySQL did not start." -ForegroundColor Red
    exit 1
}
Write-Host "MySQL is up." -ForegroundColor Green

# --- 3. Load schema + seed the first time -----------------------------
$exists = & $Mysql --no-defaults -u root -h 127.0.0.1 -P $DbPort -N -e "SHOW DATABASES LIKE '$DbName';"
if (-not $exists) {
    Write-Host "Loading schema.sql + seed.sql ..."
    # cmd /c handles stdin redirection and passes raw UTF-8 bytes to mysql.
    cmd /c ('"{0}" --no-defaults --default-character-set=utf8mb4 -u root -h 127.0.0.1 -P {1} < "{2}\schema.sql"' -f $Mysql, $DbPort, $ProjectDir)
    cmd /c ('"{0}" --no-defaults --default-character-set=utf8mb4 -u root -h 127.0.0.1 -P {1} {2} < "{3}\seed.sql"' -f $Mysql, $DbPort, $DbName, $ProjectDir)
    Write-Host "Database ready." -ForegroundColor Green
} else {
    Write-Host "Database already present - skipping import." -ForegroundColor Green
}

# --- 4. Launch the web server + browser -------------------------------
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = "$DbPort"

if (Test-Port $WebPort) {
    Write-Host "NOTE: port $WebPort is already in use - opening it anyway." -ForegroundColor Yellow
} else {
    Start-Process "http://localhost:$WebPort/index.php"
}

Write-Host ""
Write-Host "  Preview running:  http://localhost:$WebPort" -ForegroundColor Cyan
Write-Host "  Admin panel:      http://localhost:$WebPort/admin_login.php"
Write-Host "  Admin login:      admin / admin123"
Write-Host ""
Write-Host "  Close this window (or Ctrl+C) to stop the web server." -ForegroundColor Yellow
Write-Host "  Then run stop-preview.bat to shut the preview database down." -ForegroundColor Yellow
Write-Host ""

# PHP built-in server (foreground - this window stays the running server).
& $Php -d session.save_path="$SessDir" -S "localhost:$WebPort" -t "$ProjectDir"
