[CmdletBinding()]
param(
    [Parameter(Position = 0)]
    [ValidateSet('status', 'ui-assets', 'ui-assets-check')]
    [string]$Action = 'status'
)

$ErrorActionPreference = 'Stop'
$repoRoot = Split-Path -Parent $PSScriptRoot
Set-Location $repoRoot

function Require-Command([string]$Name) {
    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "Required command is not available: $Name"
    }
}

switch ($Action) {
    'status' {
        Require-Command git
        git status --short
        exit $LASTEXITCODE
    }

    'ui-assets' {
        Require-Command node
        Require-Command npm
        npm run extract --prefix UI/tools
        exit $LASTEXITCODE
    }

    'ui-assets-check' {
        Require-Command node
        Require-Command npm
        npm run extract --prefix UI/tools
        if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

        $report = Get-Content -LiteralPath 'UI/assets/export-report.json' -Raw | ConvertFrom-Json
        if ($report.Count -lt 1) { throw 'UI asset export produced no files.' }
        Write-Output ("UI assets verified: {0} separate PNG files." -f $report.Count)
    }
}
