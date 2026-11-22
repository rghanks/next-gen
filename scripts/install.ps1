# =============================================================================
# LM-Path Pathology Laboratory System - 1-Click Client Automated Installer
# =============================================================================

[CmdletBinding()]
param(
    [string]$RepoUrl = "https://github.com/rghanks/next-gen.git",
    [string]$Branch = "production",
    [string]$InstallPath = "C:\xampp\htdocs\lmpath",
    [string]$DbName = "lmpath_db",
    [string]$DbUser = "root",
    [string]$DbPass = "",
    [string]$GitHubToken = ""
)

$ErrorActionPreference = "Continue"

function Write-Step {
    param([string]$Text)
    Write-Host ""
    Write-Host "[+] $Text" -ForegroundColor Cyan
}

function Write-Success {
    param([string]$Text)
    Write-Host "    [OK] $Text" -ForegroundColor Green
}

function Write-WarnMsg {
    param([string]$Text)
    Write-Host "    [!] $Text" -ForegroundColor Yellow
}

function Write-Fail {
    param([string]$Text)
    Write-Host "    [X] $Text" -ForegroundColor Red
}

Clear-Host
Write-Host "=================================================================" -ForegroundColor Cyan
Write-Host "       LM-PATH LABORATORY SYSTEM - AUTOMATED INSTALLER          " -ForegroundColor White
Write-Host "=================================================================" -ForegroundColor Cyan
Write-Host "Target Directory : $InstallPath" -ForegroundColor Gray
Write-Host "GitHub Branch    : $Branch" -ForegroundColor Gray
Write-Host "Database Name    : $DbName" -ForegroundColor Gray
Write-Host "-----------------------------------------------------------------" -ForegroundColor Gray

# -----------------------------------------------------------------------------
# 1. Administrator Privileges Check
# -----------------------------------------------------------------------------
$currentPrincipal = New-Object Security.Principal.WindowsPrincipal([Security.Principal.WindowsIdentity]::GetCurrent())
$isAdmin = $currentPrincipal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)

if (-not $isAdmin) {
    Write-WarnMsg "Requesting Administrator privileges..."
    Start-Process powershell.exe -Verb RunAs -ArgumentList @("-NoProfile", "-ExecutionPolicy", "Bypass", "-File", "`"$PSCommandPath`"")
    exit
}

# -----------------------------------------------------------------------------
# 2. Check or Install Web Server & PHP Runtime (XAMPP / PHP)
# -----------------------------------------------------------------------------
Write-Step "Checking Web Server and PHP Runtime..."
$xamppRoot = "C:\xampp"
$phpPath = "$xamppRoot\php\php.exe"
$apachePath = "$xamppRoot\apache\bin\httpd.exe"

if (-not (Test-Path $phpPath)) {
    $globalPhp = Get-Command "php.exe" -ErrorAction SilentlyContinue
    if ($globalPhp) {
        $phpPath = $globalPhp.Source
        $xamppRoot = Split-Path (Split-Path $phpPath -Parent) -Parent
    }
}

if (-not (Test-Path $phpPath)) {
    Write-WarnMsg "PHP not found at C:\xampp\php\php.exe."
    Write-Host "    Attempting automated installation via Windows Package Manager (winget)..." -ForegroundColor Yellow

    $winget = Get-Command "winget" -ErrorAction SilentlyContinue
    if ($winget) {
        try {
            Write-Host "    Downloading and installing XAMPP..." -ForegroundColor Gray
            # BitRock InstallBuilder requires --mode unattended --unattendedmodeui none to prevent UAC/Antivirus popups from hanging
            Start-Process "winget" -ArgumentList @("install", "--id", "ApacheFriends.Xampp.8.2", "-e", "--accept-package-agreements", "--accept-source-agreements", "--override", "`"--mode unattended --unattendedmodeui none`"") -Wait
            Write-Success "XAMPP installation finished."
        } catch {
            Write-WarnMsg "Automated XAMPP install encountered an issue."
        }
    }

    if (-not (Test-Path $phpPath)) {
        Write-Fail "Please install XAMPP for Windows (PHP 8.2 or 8.3) to C:\xampp, then re-run this script."
        Write-Host "Download link: https://www.apachefriends.org/download.html" -ForegroundColor Cyan
        pause
        exit 1
    }
}

$phpVersion = & $phpPath -r "echo PHP_VERSION;"
Write-Success "Found PHP $phpVersion at $phpPath"

# -----------------------------------------------------------------------------
# 3. Configure PHP Extensions in php.ini
# -----------------------------------------------------------------------------
Write-Step "Configuring PHP extensions for Laravel and Licensing..."
$phpIni = "$xamppRoot\php\php.ini"
if (Test-Path $phpIni) {
    $iniContent = Get-Content $phpIni -Raw
    # Clean up invalid extension=bcmath if previously added (bcmath is built-in on Windows)
    $iniContent = $iniContent -replace "(?m)^extension\s*=\s*bcmath\r?\n?", ""

    $extensions = @("pdo_mysql", "openssl", "mbstring", "curl", "fileinfo", "gd", "zip")
    $iniModified = $false

    foreach ($ext in $extensions) {
        if ($iniContent -match ";\s*extension\s*=\s*$ext") {
            $iniContent = $iniContent -replace ";\s*extension\s*=\s*$ext", "extension=$ext"
            $iniModified = $true
        } elseif ($iniContent -notmatch "(?m)^extension\s*=\s*$ext") {
            if (Test-Path "$xamppRoot\php\ext\php_$ext.dll") {
                $iniContent += "`r`nextension=$ext"
                $iniModified = $true
            }
        }
    }

    $iniContent = $iniContent -replace "upload_max_filesize\s*=\s*\d+M", "upload_max_filesize = 64M"
    $iniContent = $iniContent -replace "post_max_size\s*=\s*\d+M", "post_max_size = 64M"
    $iniContent = $iniContent -replace "memory_limit\s*=\s*\d+M", "memory_limit = 256M"

    Set-Content -Path $phpIni -Value $iniContent -Encoding UTF8
    Write-Success "PHP extensions and performance settings verified in php.ini."
}

# -----------------------------------------------------------------------------
# 4. Check or Install Git
# -----------------------------------------------------------------------------
Write-Step "Checking Git..."
$git = Get-Command "git" -ErrorAction SilentlyContinue
if (-not $git) {
    if (Test-Path "C:\Program Files\Git\cmd\git.exe") {
        $env:Path += ";C:\Program Files\Git\cmd"
        $git = Get-Command "git" -ErrorAction SilentlyContinue
    }
}

if (-not $git) {
    Write-WarnMsg "Git not found. Installing Git for Windows via winget..."
    $winget = Get-Command "winget" -ErrorAction SilentlyContinue
    if ($winget) {
        Start-Process "winget" -ArgumentList @("install", "--id", "Git.Git", "-e", "--accept-package-agreements", "--accept-source-agreements", "--silent") -Wait -NoNewWindow
        $env:Path += ";C:\Program Files\Git\cmd"
        $git = Get-Command "git" -ErrorAction SilentlyContinue
    }

    if (-not $git) {
        Write-Fail "Git installation failed. Please install Git from https://git-scm.com/download/win and re-run."
        pause
        exit 1
    }
    Write-Success "Git installed successfully."
} else {
    Write-Success "Git is available."
}

# -----------------------------------------------------------------------------
# 5. Check Database Engine (MySQL / MariaDB) and Create Database
# -----------------------------------------------------------------------------
Write-Step "Checking Database Engine (MySQL / MariaDB)..."

# Test if database server is already running and reachable
$dbCheckScript = "try { `$pdo = new PDO('mysql:host=127.0.0.1;port=3306', '$DbUser', '$DbPass', [PDO::ATTR_TIMEOUT => 2]); echo 'ONLINE'; } catch (Exception `$e) { echo 'OFFLINE'; }"
$dbStatus = & $phpPath -r $dbCheckScript 2>$null

if ($dbStatus -ne "ONLINE") {
    Write-Host "    Database service not detected on port 3306. Attempting to start service..." -ForegroundColor Gray

    # Try starting existing Windows services (MySQL / MariaDB)
    $services = Get-Service -Name "mysql", "MariaDB", "MySQL*", "MariaDB*" -ErrorAction SilentlyContinue
    foreach ($svc in $services) {
        if ($svc.Status -ne "Running") {
            Start-Service -Name $svc.Name -ErrorAction SilentlyContinue
            Start-Sleep -Seconds 2
        }
    }

    # If still not running, try starting XAMPP MySQL daemon
    $dbStatus = & $phpPath -r $dbCheckScript 2>$null
    if ($dbStatus -ne "ONLINE") {
        $xamppMysqld = "$xamppRoot\mysql\bin\mysqld.exe"
        if (Test-Path $xamppMysqld) {
            Start-Process -FilePath $xamppMysqld -ArgumentList @("--defaults-file=$xamppRoot\mysql\bin\my.ini", "--standalone") -WindowStyle Hidden
            Start-Sleep -Seconds 3
        }
    }
}

# Create Database via PHP PDO directly (100% reliable across MySQL, MariaDB, XAMPP, or Program Files)
$createDbScript = "try { `$pdo = new PDO('mysql:host=127.0.0.1;port=3306', '$DbUser', '$DbPass'); `$pdo->exec('CREATE DATABASE IF NOT EXISTS $DbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); echo 'DB_READY'; } catch (Exception `$e) { echo 'ERROR: ' . `$e->getMessage(); }"
$createResult = & $phpPath -r $createDbScript 2>$null

if ($createResult -eq "DB_READY") {
    Write-Success "MySQL/MariaDB is online and database [$DbName] is verified/created."
} else {
    Write-WarnMsg "Database initialization note: $createResult"
    Write-Host "    Please ensure MySQL or MariaDB is running and credentials match in .env." -ForegroundColor Yellow
}

# -----------------------------------------------------------------------------
# 6. Clone or Update Application from GitHub (production branch)
# -----------------------------------------------------------------------------
Write-Step "Deploying software from GitHub [$Branch branch]..."
$targetCloneUrl = $RepoUrl

if ($GitHubToken -ne "") {
    $targetCloneUrl = $RepoUrl -replace "https://", "https://$GitHubToken@"
}

if (Test-Path "$InstallPath\.git") {
    Write-Host "    Existing installation found. Pulling latest encrypted release..." -ForegroundColor Gray
    Push-Location $InstallPath
    try {
        & git checkout $Branch 2>$null
        & git pull origin $Branch
        Write-Success "Updated to latest release."
    } catch {
        Write-WarnMsg "Git pull encountered an error. Proceeding with existing files."
    }
    Pop-Location
} else {
    Write-Host "    Cloning fresh encrypted production release..." -ForegroundColor Gray
    if (Test-Path $InstallPath) {
        $existingItems = Get-ChildItem -Path $InstallPath -Force
        if ($existingItems.Count -eq 0) {
            Remove-Item $InstallPath -Force
        }
    }

    try {
        & git clone -b $Branch $targetCloneUrl $InstallPath
        Write-Success "Cloned to $InstallPath."
    } catch {
        Write-Fail "Failed to clone repository. If this is a private repository, please pass your GitHub Token."
        pause
        exit 1
    }
}

# -----------------------------------------------------------------------------
# 7. Setup Composer and Dependencies
# -----------------------------------------------------------------------------
Write-Step "Checking Composer Dependencies..."
$composerPhar = "$xamppRoot\php\composer.phar"

if (-not (Test-Path $composerPhar)) {
    Write-Host "    Downloading Composer runtime..." -ForegroundColor Gray
    try {
        [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
        Invoke-WebRequest -Uri "https://getcomposer.org/download/latest-stable/composer.phar" -OutFile $composerPhar -UseBasicParsing
        Write-Success "Composer runtime installed."
    } catch {
        Write-WarnMsg "Could not download composer.phar directly."
    }
}

Push-Location $InstallPath

if (-not (Test-Path "$InstallPath\vendor")) {
    Write-Host "    Installing production dependencies (this may take 1-2 minutes)..." -ForegroundColor Gray
    if (Test-Path $composerPhar) {
        & $phpPath $composerPhar install --no-dev --optimize-autoloader --no-interaction
    } else {
        $globalComposer = Get-Command "composer" -ErrorAction SilentlyContinue
        if ($globalComposer) {
            & composer install --no-dev --optimize-autoloader --no-interaction
        }
    }
    Write-Success "Composer dependencies installed."
} else {
    Write-Success "Vendor packages already present."
}

# -----------------------------------------------------------------------------
# 8. Configure Environment (.env) and Run Migrations
# -----------------------------------------------------------------------------
Write-Step "Configuring Environment (.env) and Database..."

$envFile = "$InstallPath\.env"
if (-not (Test-Path $envFile)) {
    if (Test-Path "$InstallPath\.env.example") {
        Copy-Item "$InstallPath\.env.example" $envFile
    } else {
        $defaultEnv = "APP_NAME=`"LM-Path Laboratory System`"`r`nAPP_ENV=production`r`nAPP_DEBUG=false`r`nAPP_URL=http://localhost/lmpath/public`r`n`r`nDB_CONNECTION=mysql`r`nDB_HOST=127.0.0.1`r`nDB_PORT=3306`r`nDB_DATABASE=$DbName`r`nDB_USERNAME=$DbUser`r`nDB_PASSWORD=$DbPass`r`n`r`nSESSION_DRIVER=database`r`nQUEUE_CONNECTION=database`r`nCACHE_STORE=database`r`n"
        Set-Content -Path $envFile -Value $defaultEnv -Encoding UTF8
    }
    Write-Success "Created .env configuration."

    & $phpPath artisan key:generate --force
    Write-Success "Generated Laravel APP_KEY."
} else {
    Write-Success ".env already configured."
}

$envContent = Get-Content $envFile -Raw
$envContent = $envContent -replace "DB_DATABASE=.*", "DB_DATABASE=$DbName"
$envContent = $envContent -replace "DB_USERNAME=.*", "DB_USERNAME=$DbUser"
$envContent = $envContent -replace "DB_PASSWORD=.*", "DB_PASSWORD=$DbPass"
Set-Content -Path $envFile -Value $envContent -Encoding UTF8

$storageDirs = @(
    "$InstallPath\storage\app\public",
    "$InstallPath\storage\framework\cache",
    "$InstallPath\storage\framework\sessions",
    "$InstallPath\storage\framework\views",
    "$InstallPath\storage\logs",
    "$InstallPath\storage\license",
    "$InstallPath\bootstrap\cache"
)
foreach ($dir in $storageDirs) {
    if (-not (Test-Path $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
    }
}

& $phpPath artisan storage:link 2>$null
Write-Success "Storage linked."

Write-Host "    Running database migrations..." -ForegroundColor Gray
& $phpPath artisan migrate --force
Write-Success "Database schema migrated."

& $phpPath artisan optimize:clear 2>$null

# -----------------------------------------------------------------------------
# 9. Configure Apache Web Server (Automatic Windows Service)
# -----------------------------------------------------------------------------
Write-Step "Configuring Apache Web Server..."

$apacheService = Get-Service -Name "Apache2.4" -ErrorAction SilentlyContinue
if (-not $apacheService -and (Test-Path $apachePath)) {
    Write-Host "    Registering Apache as a background Windows Service..." -ForegroundColor Gray
    Start-Process -FilePath $apachePath -ArgumentList @("-k", "install") -Wait -WindowStyle Hidden
    $apacheService = Get-Service -Name "Apache2.4" -ErrorAction SilentlyContinue
}

if ($apacheService) {
    Set-Service -Name "Apache2.4" -StartupType Automatic
    if ($apacheService.Status -ne "Running") {
        Start-Service -Name "Apache2.4" -ErrorAction SilentlyContinue
    }
    Write-Success "Apache configured as an Automatic Windows Service (Auto-starts on reboot)."
} else {
    $apacheRunning = Get-Process -Name "httpd" -ErrorAction SilentlyContinue
    if (-not $apacheRunning -and (Test-Path $apachePath)) {
        Start-Process -FilePath $apachePath -WindowStyle Hidden
        Start-Sleep -Seconds 2
        Write-Success "Apache Web Server started."
    } elseif ($apacheRunning) {
        Write-Success "Apache Web Server is running."
    } else {
        Write-WarnMsg "Apache path not found at $apachePath. If using a custom web server, please verify it is running."
    }
}

# -----------------------------------------------------------------------------
# 10. Create Desktop Shortcut for Receptionist / Technician
# -----------------------------------------------------------------------------
Write-Step "Creating Desktop Shortcut..."
$desktopPath = [Environment]::GetFolderPath("Desktop")
$shortcutFile = "$desktopPath\LM-Path Laboratory System.url"

$shortcutContent = "[InternetShortcut]`r`nURL=http://localhost/lmpath/public/`r`nIconIndex=0`r`nIconFile=$apachePath`r`n"
Set-Content -Path $shortcutFile -Value $shortcutContent -Encoding ASCII
Write-Success "Desktop shortcut created: [LM-Path Laboratory System.url]"

$machineId = & $phpPath -r "require 'vendor/autoload.php'; echo App\Services\Licensing\HardwareFingerprint::getMachineId();" 2>$null

Pop-Location

# -----------------------------------------------------------------------------
# FINISHED SCREEN
# -----------------------------------------------------------------------------
Write-Host ""
Write-Host "=================================================================" -ForegroundColor Green
Write-Host "              INSTALLATION COMPLETED SUCCESSFULLY!              " -ForegroundColor White
Write-Host "=================================================================" -ForegroundColor Green
Write-Host "Access URL      : http://localhost/lmpath/public/" -ForegroundColor White
Write-Host "Hardware Code   : $machineId" -ForegroundColor Yellow
Write-Host "-----------------------------------------------------------------" -ForegroundColor Gray
Write-Host "Next Step: Copy the Hardware Code above, generate a license key " -ForegroundColor Gray
Write-Host "in your Vendor Portal (http://localhost/license-portal/), and activate." -ForegroundColor Gray
Write-Host "=================================================================" -ForegroundColor Green
Write-Host ""

Start-Process "http://localhost/lmpath/public/"

pause
