#Requires -Version 5.1
#Requires -RunAsAdministrator
[CmdletBinding()]
param(
    [ValidateSet('Restart', 'Start', 'Stop', 'Status')][string]$Action = 'Restart',
    [string]$AppPath = 'C:\laragon\private\laundry-release',
    [ValidateRange(15, 86400)][int]$WaitSeconds = 180
)

. (Join-Path $PSScriptRoot 'QueueWorker.Common.ps1')
Import-Module ScheduledTasks
$paths = Get-QueuePaths $AppPath
$config = Read-QueueConfig $paths
[void](Assert-QueueTask $paths)
if ($Action -eq 'Status') {
    $status = Get-QueueStatus $paths
    $status
    if (-not $status.WorkerLockHeld -or -not $status.WorkerProcessExists -or
        -not $status.SupervisorRunning -or $status.Draining -or -not $status.TaskEnabled) { exit 1 }
    exit 0
}
$operation = Open-QueueLock $paths.OperatorLock
if ($null -eq $operation) { throw 'Another worker management command is running.' }
try {
    # Disabling a task prevents future triggers; it does not terminate a running instance.
    Disable-ScheduledTask -TaskPath '\' -TaskName 'LaundryQueueWorker' | Out-Null
    [IO.File]::WriteAllText($paths.Drain, [DateTime]::UtcNow.ToString('o'))
    Write-QueueLog $paths.Log "Operator requested $Action; blocking replacement workers while draining."
    $deadline = [DateTime]::UtcNow.AddSeconds($WaitSeconds)
    $nextSignal = [DateTime]::MinValue
    while ((Test-QueueLockHeld $paths.SupervisorLock) -or (Test-QueueLockHeld $paths.WorkerLock)) {
        if ([DateTime]::UtcNow -ge $deadline) {
            throw 'Graceful drain timed out. Worker was NOT killed. Task remains disabled and drain marker preserved. Inspect jobs/logs and rerun this command with a longer WaitSeconds.'
        }
        if ((Test-QueueLockHeld $paths.WorkerLock) -and [DateTime]::UtcNow -ge $nextSignal) {
            Assert-QueueEnvironment $paths $config
            [void](Invoke-QueuePhp $paths $config @((Join-Path $paths.Root 'artisan'), 'queue:restart', '--no-interaction', '--no-ansi'))
            # Repeat across timestamp boundaries for a worker bootstrapping during the first signal.
            $nextSignal = [DateTime]::UtcNow.AddSeconds(3)
        }
        Start-Sleep -Milliseconds 500
    }
    if ($Action -eq 'Stop') {
        Write-QueueLog $paths.Log 'Stopped gracefully; task disabled and drain marker retained.'
        Write-Output 'Worker stopped gracefully. LaundryQueueWorker is disabled, including across reboot.'
        exit 0
    }
    Assert-QueueEnvironment $paths $config
    Remove-Item -LiteralPath $paths.Drain -Force
    Enable-ScheduledTask -TaskPath '\' -TaskName 'LaundryQueueWorker' | Out-Null
    Start-ScheduledTask -TaskPath '\' -TaskName 'LaundryQueueWorker'
    $deadline = [DateTime]::UtcNow.AddSeconds($WaitSeconds)
    $generation = $null
    $stableSince = [DateTime]::UtcNow
    while ([DateTime]::UtcNow -lt $deadline) {
        $status = Get-QueueStatus $paths
        if ($status.SupervisorRunning -and $status.WorkerLockHeld -and $status.WorkerProcessExists -and -not $status.Draining) {
            if ($status.Generation -ne $generation) {
                $generation = $status.Generation
                $stableSince = [DateTime]::UtcNow
            } elseif (([DateTime]::UtcNow - $stableSince).TotalSeconds -ge 5) {
                Write-QueueLog $paths.Log "Restart verified pid=$($status.WorkerPid) generation=$generation."
                $status
                exit 0
            }
        } else {
            $generation = $null
            $stableSince = [DateTime]::UtcNow
        }
        Start-Sleep -Seconds 1
    }
    throw 'Startup not verified. Task remains enabled for recovery; inspect supervisor, worker and Laravel logs. A running process alone does not prove database/SMTP health.'
} finally { $operation.Dispose() }
