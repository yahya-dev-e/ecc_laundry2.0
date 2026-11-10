#Requires -Version 4.0
#Requires -RunAsAdministrator
[CmdletBinding()]
param(
    [string]$AppPath = 'C:\laragon\private\laundry-release',
    [string]$PhpPath = 'C:\laragon\bin\php\php-8.3.36-Win32-vs16-x64\php.exe',
    [Parameter(Mandatory)][ValidateSet('PrivateTest', 'Production')][string]$EnvironmentKind,
    [Parameter(Mandatory)][ValidatePattern('\A[A-Za-z0-9_-]+\z')][string]$ExpectedDatabase,
    [string]$ExpectedDatabaseHost = '127.0.0.1',
    [Parameter(Mandatory)][PSCredential]$Credential
)

. (Join-Path $PSScriptRoot 'QueueWorker.Common.ps1')
Import-Module ScheduledTasks
$paths = Get-QueuePaths $AppPath
$php = [IO.Path]::GetFullPath($PhpPath)
if ($paths.Root -ne [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..')).TrimEnd('\')) {
    throw 'Run the installer from the checkout specified by AppPath.'
}
foreach ($path in @($paths.Root, $php)) {
    if ($path.Contains('"') -or $path.Contains("`r") -or $path.Contains("`n")) { throw 'Unsupported path.' }
}
if (-not (Test-Path -LiteralPath $php -PathType Leaf)) { throw "PHP not found: $php" }
if ($EnvironmentKind -eq 'PrivateTest' -and -not $ExpectedDatabase.EndsWith('_test')) {
    throw 'A private-test database name must end in _test.'
}
[void](New-Item -ItemType Directory -Path $paths.State -Force)
[void](New-Item -ItemType Directory -Path (Split-Path $paths.Log) -Force)
$operation = Open-QueueLock $paths.OperatorLock
if ($null -eq $operation) { throw 'Another worker management command is running.' }
$temporary = Join-Path $paths.State ('install-' + [guid]::NewGuid().ToString('N') + '.json')
$installationLock = $null
try {
    $existing = Get-ScheduledTask -TaskPath '\' -TaskName 'LaundryQueueWorker' -ErrorAction SilentlyContinue
    if ($null -ne $existing) { [void](Assert-QueueTask $paths) }
    $config = [ordered]@{
        Version = 1
        TaskName = 'LaundryQueueWorker'
        AppPath = $paths.Root
        PhpPath = $php
        EnvironmentKind = $EnvironmentKind
        ExpectedDatabase = $ExpectedDatabase
        ExpectedDatabaseHost = $ExpectedDatabaseHost
        TaskUser = $Credential.UserName
    }
    # PHP JSON does not accept Windows PowerShell's UTF-8 BOM (including 4.0).
    [IO.File]::WriteAllText($temporary, ($config | ConvertTo-Json), (New-Object Text.UTF8Encoding($false)))
    $probeConfig = [pscustomobject]$config
    [void](Invoke-QueuePhp $paths $probeConfig @((Join-Path $PSScriptRoot 'check-queue-environment.php'), $temporary))
    if ((Test-QueueLockHeld $paths.SupervisorLock) -or (Test-QueueLockHeld $paths.WorkerLock)) {
        $old = Read-QueueConfig $paths
        foreach ($key in $config.Keys) {
            if ($old.$key -ne $config[$key]) { throw 'Configuration changed while a worker is running. Stop it gracefully before reinstalling.' }
        }
        Write-Output 'Configuration already installed; active worker left running. Use Manage-QueueWorker.ps1 to restart.'
        exit 0
    }
    # Exclude a watchdog startup while replacing the config/task definition.
    $installationLock = Open-QueueLock $paths.SupervisorLock
    if ($null -eq $installationLock -or (Test-QueueLockHeld $paths.WorkerLock)) {
        throw 'A worker/supervisor started during installation. Stop it gracefully and rerun.'
    }
    Move-Item -LiteralPath $temporary -Destination $paths.Config -Force
    [void](Read-QueueConfig $paths)
    $shell = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
    $arguments = '-NoProfile -NonInteractive -WindowStyle Hidden -ExecutionPolicy Bypass -File "{0}" -AppPath "{1}"' -f (Join-Path $PSScriptRoot 'Run-QueueWorker.ps1'), $paths.Root
    $action = New-ScheduledTaskAction -Execute $shell -Argument $arguments -WorkingDirectory $paths.Root
    $startup = New-ScheduledTaskTrigger -AtStartup
    # A repeating watchdog recovers the supervisor even after a normal exit or exhausted retries.
    # Omit RepetitionDuration so it repeats indefinitely. IgnoreNew prevents overlap.
    $watchdog = New-ScheduledTaskTrigger -Once -At ((Get-Date).AddMinutes(1)) -RepetitionInterval (New-TimeSpan -Minutes 1)
    $settings = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -ExecutionTimeLimit ([TimeSpan]::Zero) -RestartCount 999 -RestartInterval (New-TimeSpan -Minutes 1) -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
    $principal = New-ScheduledTaskPrincipal -UserId $Credential.UserName -LogonType Password -RunLevel Limited
    $task = New-ScheduledTask -Action $action -Trigger @($startup, $watchdog) -Settings $settings -Principal $principal -Description 'Laundry database queue supervisor. Drain/restart with scripts/windows/Manage-QueueWorker.ps1.'
    # Password logon supports network/SMTP access while logged out. No S4U restriction.
    $password = $Credential.GetNetworkCredential().Password
    try {
        [void](Register-ScheduledTask -TaskPath '\' -TaskName 'LaundryQueueWorker' -InputObject $task -User $Credential.UserName -Password $password -Force)
    } finally { $password = $null }
    $installationLock.Dispose()
    $installationLock = $null
    if (Test-Path -LiteralPath $paths.Drain) {
        Write-Output 'Task installed. Existing drain marker preserved; use Manage-QueueWorker.ps1 -Action Restart when ready.'
    } else {
        Start-ScheduledTask -TaskPath '\' -TaskName 'LaundryQueueWorker'
        Write-Output 'Task installed and startup requested. Verify with Manage-QueueWorker.ps1 -Action Status.'
    }
} finally {
    if ($null -ne $installationLock) { $installationLock.Dispose() }
    if (Test-Path -LiteralPath $temporary) { Remove-Item -LiteralPath $temporary -Force }
    $operation.Dispose()
}
