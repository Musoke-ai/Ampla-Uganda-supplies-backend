param(
    [string]$DatabaseName = ("amplaerp_migration_test_" + (Get-Date -Format "yyyyMMdd_HHmmss")),
    [switch]$CreateOnly,
    [switch]$KeepEnv
)

$ErrorActionPreference = "Stop"

$repoRoot = Split-Path -Parent $PSScriptRoot
$envPath = Join-Path $repoRoot ".env"

if (-not (Test-Path $envPath)) {
    throw ".env was not found at $envPath"
}

$originalEnv = Get-Content -LiteralPath $envPath -Raw
$updatedEnv = [regex]::Replace(
    $originalEnv,
    '(?m)^\s*database\.default\.database\s*=.*$',
    "database.default.database = $DatabaseName"
)

if ($updatedEnv -eq $originalEnv) {
    throw "Could not find database.default.database in .env"
}

try {
    Write-Host "Using temporary database: $DatabaseName"
    & php spark db:create $DatabaseName
    if ($LASTEXITCODE -ne 0) {
        throw "php spark db:create failed."
    }

    Set-Content -LiteralPath $envPath -Value $updatedEnv -NoNewline

    if (-not $CreateOnly) {
        & php spark migrate --all
        if ($LASTEXITCODE -ne 0) {
            throw "php spark migrate --all failed."
        }

        & php spark migrate:status
        if ($LASTEXITCODE -ne 0) {
            throw "php spark migrate:status failed."
        }
    }

    Write-Host ""
    Write-Host "Clean test database is ready: $DatabaseName"
    if (-not $KeepEnv) {
        Write-Host "Your original .env database setting will now be restored."
    }
}
finally {
    if (-not $KeepEnv) {
        Set-Content -LiteralPath $envPath -Value $originalEnv -NoNewline
    }
}
