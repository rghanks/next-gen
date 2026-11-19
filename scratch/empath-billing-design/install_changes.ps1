$ErrorActionPreference = 'Stop'
$stageRoot = [System.IO.Path]::GetFullPath($PSScriptRoot)
$projectRoot = [System.IO.Path]::GetFullPath('C:\xampp\htdocs\empath')
$entries = Get-Content -LiteralPath (Join-Path $stageRoot 'install-manifest.json') -Raw | ConvertFrom-Json
foreach ($entry in $entries) {
    $targetPath = [System.IO.Path]::GetFullPath((Join-Path $projectRoot $entry.path))
    $stagedPath = [System.IO.Path]::GetFullPath((Join-Path $stageRoot $entry.path))
    if (-not $targetPath.StartsWith($projectRoot + [System.IO.Path]::DirectorySeparatorChar) -or -not $stagedPath.StartsWith($stageRoot + [System.IO.Path]::DirectorySeparatorChar)) { throw 'Invalid manifest path' }
    if ((Get-FileHash -LiteralPath $stagedPath -Algorithm SHA256).Hash -ne $entry.stagedHash) { throw "Staged file changed: $($entry.path)" }
    if ($entry.originalHash) {
        if (-not (Test-Path -LiteralPath $targetPath) -or (Get-FileHash -LiteralPath $targetPath -Algorithm SHA256).Hash -ne $entry.originalHash) { throw "Source changed since review: $($entry.path)" }
    } elseif (Test-Path -LiteralPath $targetPath) { throw "New file already exists: $($entry.path)" }
}
foreach ($entry in $entries) {
    $targetPath = [System.IO.Path]::GetFullPath((Join-Path $projectRoot $entry.path))
    if ($entry.originalHash) {
        $backupPath = Join-Path (Join-Path $stageRoot 'backups') $entry.path
        New-Item -ItemType Directory -Path (Split-Path -Parent $backupPath) -Force | Out-Null
        if (-not (Test-Path -LiteralPath $backupPath)) { Copy-Item -LiteralPath $targetPath -Destination $backupPath }
    }
    New-Item -ItemType Directory -Path (Split-Path -Parent $targetPath) -Force | Out-Null
    Copy-Item -LiteralPath (Join-Path $stageRoot $entry.path) -Destination $targetPath
    Write-Output "Applied $($entry.path)"
}
Push-Location -LiteralPath $projectRoot
try {
    foreach ($entry in $entries) {
        if ($entry.path.EndsWith('.php')) {
            & 'C:\xampp\php\php.exe' -l (Join-Path $projectRoot $entry.path)
            if ($LASTEXITCODE -ne 0) { throw "PHP syntax check failed: $($entry.path)" }
        }
    }
    if (@($entries.path) -contains 'routes/web.php') {
        & 'C:\xampp\php\php.exe' artisan route:clear
        if ($LASTEXITCODE -ne 0) { throw 'Route cache clear failed' }
        & 'C:\xampp\php\php.exe' artisan route:list --path=v2/billing --except-vendor
        if ($LASTEXITCODE -ne 0) { throw 'Route validation failed' }
    }
    if (@($entries.path | Where-Object { $_.EndsWith('.php') }).Count -gt 0) {
        & 'C:\xampp\php\php.exe' -d extension=pdo_sqlite -d extension=sqlite3 vendor\phpunit\phpunit\phpunit --no-configuration --bootstrap vendor/autoload.php --do-not-cache-result tests/Feature/BillingWorkflowTest.php
        if ($LASTEXITCODE -ne 0) { throw 'Billing workflow tests failed' }
    }
} finally { Pop-Location }
