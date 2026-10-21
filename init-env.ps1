$ErrorActionPreference = 'Stop'
$envPath = Join-Path $PSScriptRoot '.env'
if (Test-Path -LiteralPath $envPath) {
    Write-Output '.env already exists; keeping the current configuration.'
    return
}
$keyBytes = New-Object byte[] 32
$rng = [Security.Cryptography.RandomNumberGenerator]::Create()
try {
    $rng.GetBytes($keyBytes)
} finally {
    $rng.Dispose()
}
$appKey = [Convert]::ToBase64String($keyBytes)
$dbPassword = [Guid]::NewGuid().ToString('N')
$rootPassword = [Guid]::NewGuid().ToString('N')
@(
    'APP_PORT=8080'
    "APP_KEY=base64:$appKey"
    "DB_PASSWORD=$dbPassword"
    "DB_ROOT_PASSWORD=$rootPassword"
) | Set-Content -LiteralPath $envPath -Encoding ascii
Write-Output 'Created .env with a new application key and database passwords.'
