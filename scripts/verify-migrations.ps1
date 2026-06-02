param(
    [string]$DatabaseName = ("amplaerp_migration_verify_" + (Get-Date -Format "yyyyMMdd_HHmmss")),
    [switch]$KeepDatabase,
    [switch]$SkipTests
)

$ErrorActionPreference = "Stop"

$repoRoot = Split-Path -Parent $PSScriptRoot
$envPath = Join-Path $repoRoot ".env"

if ($DatabaseName -notmatch '^[A-Za-z0-9_]+$') {
    throw "DatabaseName may only contain letters, numbers, and underscores."
}

if (-not (Test-Path -LiteralPath $envPath)) {
    throw ".env was not found at $envPath"
}

function Get-EnvValue {
    param(
        [string]$Content,
        [string]$Key,
        [string]$Default = ""
    )

    $pattern = '(?m)^[ \t]*' + [regex]::Escape($Key) + '[ \t]*=[ \t]*(.*)$'
    $match = [regex]::Match($Content, $pattern)

    if (-not $match.Success) {
        return $Default
    }

    return $match.Groups[1].Value.Trim().Trim("'").Trim('"')
}

function Set-EnvValue {
    param(
        [string]$Content,
        [string]$Key,
        [string]$Value
    )

    $line = "$Key = $Value"
    $pattern = '(?m)^[ \t]*' + [regex]::Escape($Key) + '[ \t]*=.*$'

    if ([regex]::IsMatch($Content, $pattern)) {
        return [regex]::Replace($Content, $pattern, $line)
    }

    return $Content.TrimEnd() + [Environment]::NewLine + $line + [Environment]::NewLine
}

function Invoke-Checked {
    param(
        [string]$Label,
        [scriptblock]$Command
    )

    Write-Host ""
    Write-Host "==> $Label"
    $output = & $Command 2>&1
    $output | ForEach-Object { Write-Host $_ }
    $outputText = ($output | Out-String)

    if ($LASTEXITCODE -ne 0 -or $outputText -match '\[[^\]]*Exception\]') {
        throw "$Label failed with exit code $LASTEXITCODE."
    }
}

$mysql = Get-Command mysql -ErrorAction Stop
$originalEnv = Get-Content -LiteralPath $envPath -Raw

$hostName = Get-EnvValue $originalEnv "database.default.hostname" "localhost"
$port = Get-EnvValue $originalEnv "database.default.port" "3306"
$username = Get-EnvValue $originalEnv "database.default.username" "root"
$password = Get-EnvValue $originalEnv "database.default.password" ""

$mysqlArgs = @("--host=$hostName", "--port=$port", "--user=$username")
if ($password -ne "") {
    $mysqlArgs += "--password=$password"
}

$updatedEnv = Set-EnvValue $originalEnv "database.default.database" $DatabaseName
$updatedEnv = Set-EnvValue $updatedEnv "database.default.hostname" $hostName
$updatedEnv = Set-EnvValue $updatedEnv "database.default.username" $username
$updatedEnv = Set-EnvValue $updatedEnv "database.default.password" $password
$updatedEnv = Set-EnvValue $updatedEnv "database.default.port" $port

$databaseCreated = $false

try {
    Write-Host "Using temporary migration database: $DatabaseName"
    & $mysql.Source @mysqlArgs -e "DROP DATABASE IF EXISTS ``$DatabaseName``; CREATE DATABASE ``$DatabaseName`` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
    if ($LASTEXITCODE -ne 0) {
        throw "Could not create temporary database $DatabaseName."
    }
    $databaseCreated = $true

    Set-Content -LiteralPath $envPath -Value $updatedEnv -NoNewline

    Invoke-Checked "Run all migrations on a clean database" {
        php spark migrate --all --no-header
    }

    Invoke-Checked "Run migrations again to verify no-op behavior" {
        php spark migrate --all --no-header
    }

    Invoke-Checked "Check migration status" {
        php spark migrate:status --no-header
    }

    if (-not $SkipTests) {
        Invoke-Checked "Run PHPUnit after migration verification" {
            vendor\bin\phpunit --colors=never
        }
    }

    Write-Host ""
    Write-Host "Migration verification passed for $DatabaseName."
}
finally {
    Set-Content -LiteralPath $envPath -Value $originalEnv -NoNewline

    if ($databaseCreated -and -not $KeepDatabase) {
        & $mysql.Source @mysqlArgs -e "DROP DATABASE IF EXISTS ``$DatabaseName``;"
    }

    if ($KeepDatabase) {
        Write-Host "Temporary database kept: $DatabaseName"
    } else {
        Write-Host "Temporary database removed and original .env restored."
    }
}
