[CmdletBinding()]
param(
    [string] $N8nComposePath = (Join-Path $env:USERPROFILE 'Desktop\N8n\self-hosted-ai-starter-kit\docker-compose.yml'),
    [switch] $DryRun
)

$ErrorActionPreference = 'Stop'
$projectDirectory = $PSScriptRoot
$scraperComposePath = Join-Path $projectDirectory 'docker-compose.yaml'
$scraperEnvPath = Join-Path $projectDirectory '.env'
$networkName = 'n8n_automation'
$n8nService = 'n8n'
$scraperService = 'playwright'

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw 'Docker CLI est introuvable. Installe ou active Docker Desktop, puis relance ce script.'
}
if (-not (Test-Path -LiteralPath $N8nComposePath -PathType Leaf)) {
    throw "Compose n8n introuvable: $N8nComposePath. Passe son chemin avec -N8nComposePath."
}
if (-not (Test-Path -LiteralPath $scraperComposePath -PathType Leaf)) {
    throw "Compose du scraper introuvable: $scraperComposePath"
}
if ( -not $DryRun -and [string]::IsNullOrWhiteSpace($env:PLAYWRIGHT_API_KEY) ) {
    $envFileHasKey = ( Test-Path -LiteralPath $scraperEnvPath -PathType Leaf ) -and ( Select-String -LiteralPath $scraperEnvPath -Pattern '^\s*PLAYWRIGHT_API_KEY\s*=\s*\S+' -Quiet )
    if ( -not $envFileHasKey ) {
    throw 'Scraper API key missing. Create local .env from .env.example and set PLAYWRIGHT_API_KEY before startup. The .env file is ignored by Git.'
    }
}

function Invoke-DockerCommand {
    param(
        [Parameter(Mandatory = $true)]
        [string[]] $Arguments
    )

    if ($DryRun) {
        Write-Host ('docker ' + ($Arguments -join ' '))
        return
    }

    $previousErrorActionPreference = $ErrorActionPreference
    try {
        $ErrorActionPreference = 'Continue'
        $output = & docker @Arguments 2>&1
        $exitCode = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $previousErrorActionPreference
    }

    foreach ($line in $output) {
        Write-Host ([string] $line)
    }
    if ($exitCode -ne 0) {
        throw "Echec de la commande Docker: docker $($Arguments -join ' ')"
    }
}

function Test-DockerCommand {
    param(
        [Parameter(Mandatory = $true)]
        [string[]] $Arguments
    )

    $previousErrorActionPreference = $ErrorActionPreference
    try {
        $ErrorActionPreference = 'Continue'
        $null = & docker @Arguments 2>&1
        return $LASTEXITCODE -eq 0
    } finally {
        $ErrorActionPreference = $previousErrorActionPreference
    }
}

if ($DryRun) {
    Write-Host "Compose n8n: $N8nComposePath"
    Write-Host "Compose scraper: $scraperComposePath"
    Write-Host 'Le mode DryRun est sans effet sur Docker.'
    Invoke-DockerCommand -Arguments @('desktop', 'start')
    Invoke-DockerCommand -Arguments @('network', 'create', $networkName)
    Invoke-DockerCommand -Arguments @('compose', '-f', $N8nComposePath, 'up', '-d', $n8nService)
    Invoke-DockerCommand -Arguments @('compose', '-f', $scraperComposePath, 'up', '-d', $scraperService)
    Invoke-DockerCommand -Arguments @('network', 'connect', $networkName, 'n8n')
    Invoke-DockerCommand -Arguments @('exec', 'n8n', 'node', '-e', "fetch('http://playwright:3000/health').then(r => r.text()).then(console.log)")
    return
}

if (-not (Test-DockerCommand -Arguments @('info'))) {
    Write-Host 'Demarrage de Docker Desktop...'
    Invoke-DockerCommand -Arguments @('desktop', 'start')
    if (-not (Test-DockerCommand -Arguments @('info'))) {
        throw 'Docker Desktop ne demarre pas. Ouvre Docker Desktop et verifie que le moteur Linux est pret.'
    }
}

if (-not (Test-DockerCommand -Arguments @('network', 'inspect', $networkName))) {
    Invoke-DockerCommand -Arguments @('network', 'create', $networkName)
}

Write-Host 'Demarrage de n8n et de ses dependances Compose...'
Invoke-DockerCommand -Arguments @('compose', '-f', $N8nComposePath, 'up', '-d', $n8nService)

Write-Host 'Demarrage du service Playwright...'
Invoke-DockerCommand -Arguments @('compose', '-f', $scraperComposePath, 'up', '-d', $scraperService)

$networkJson = & docker inspect --format '{{json .NetworkSettings.Networks}}' $n8nService
if ($LASTEXITCODE -ne 0) {
    throw "Le conteneur '$n8nService' est introuvable apres le demarrage de la stack n8n."
}
$attachedNetworks = $networkJson | ConvertFrom-Json
if ($attachedNetworks.PSObject.Properties.Name -notcontains $networkName) {
    Invoke-DockerCommand -Arguments @('network', 'connect', $networkName, $n8nService)
}

$healthCheck = "fetch('http://playwright:3000/health').then(r => r.json()).then(data => { console.log(JSON.stringify(data)); if (!data.success) process.exit(1); }).catch(error => { console.error(error.message); process.exit(1); })"
Write-Host 'Verification reseau n8n -> scraper (health seulement, aucune collecte)...'
$healthReady = $false
for ($attempt = 1; $attempt -le 15; $attempt++) {
    $previousErrorActionPreference = $ErrorActionPreference
    try {
        $ErrorActionPreference = 'Continue'
        $healthOutput = & docker exec $n8nService node -e $healthCheck 2>&1
        $healthExitCode = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $previousErrorActionPreference
    }

    if ($healthExitCode -eq 0) {
        foreach ($line in $healthOutput) {
            Write-Host ([string] $line)
        }
        $healthReady = $true
        break
    }

    if ($attempt -lt 15) {
        Write-Host "Le scraper n'est pas encore pret (tentative $attempt/15); nouvel essai dans 2 secondes."
        Start-Sleep -Seconds 2
    }
}
if (-not $healthReady) {
    throw 'Le scraper ne repond pas au health-check depuis n8n apres 30 secondes.'
}

Write-Host ''
Write-Host 'Stack locale prete. Interface n8n: http://localhost:5678'
Write-Host 'Aucun workflow de scraping ne sera lance.'
Write-Warning 'Le port 5678 est publie sur toutes les interfaces. Limite-le a 127.0.0.1 si un acces externe nest pas souhaite.'
