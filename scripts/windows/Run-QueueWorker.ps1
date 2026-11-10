#Requires -Version 4.0
[CmdletBinding()]
param([string]$AppPath = 'C:\laragon\private\laundry-release')

. (Join-Path $PSScriptRoot 'QueueWorker.Common.ps1')
$paths = Get-QueuePaths $AppPath
$supervisor = $null
$workerProcess = $null
try {
    $config = Read-QueueConfig $paths
    $supervisor = Open-QueueLock $paths.SupervisorLock
    if ($null -eq $supervisor) { exit 0 }
    Write-QueueLog $paths.Log "Supervisor started pid=$PID; target=$($paths.Root)."
    while ($true) {
        if (Test-QueueLockHeld $paths.WorkerLock) {
            # Also covers a surviving child of a previous/crashed supervisor.
            Start-Sleep -Seconds 1
            continue
        }
        if ($null -ne $workerProcess) {
            $workerProcess.Refresh()
            if (-not $workerProcess.HasExited) {
                # The child may still be loading PHP before it acquires its lock.
                Start-Sleep -Seconds 1
                continue
            }
            Write-QueueLog $paths.Log "Worker exited code=$($workerProcess.ExitCode); normal exits are also supervised."
            $workerProcess.Dispose()
            $workerProcess = $null
            Start-Sleep -Seconds 3
        }
        if (Test-Path -LiteralPath $paths.Drain) {
            Write-QueueLog $paths.Log 'Drain complete; supervisor exiting without relaunch.'
            break
        }
        try {
            Assert-QueueEnvironment $paths $config
            if (Test-Path -LiteralPath $paths.Drain) { continue }
            $stamp = [DateTime]::UtcNow.ToString('yyyyMMdd-HHmmss-fffffff')
            $logRoot = Join-Path $paths.Root 'storage\logs'
            # Retain the 30 most recent worker stdout/stderr files, never Laravel logs.
            foreach ($pattern in @('queue-worker-*.out.log', 'queue-worker-*.err.log')) {
                Get-ChildItem -LiteralPath $logRoot -Filter $pattern | Sort-Object LastWriteTimeUtc -Descending |
                    Select-Object -Skip 30 | Remove-Item -Force
            }
            $arguments = '-d auto_prepend_file="{0}" "{1}" queue:work --sleep=3 --tries=3 --timeout=60 --no-interaction --no-ansi' -f (Join-Path $paths.Root 'scripts\windows\queue-worker-lock.php'), (Join-Path $paths.Root 'artisan')
            $workerProcess = Start-Process -FilePath $config.PhpPath -ArgumentList $arguments -WorkingDirectory $paths.Root -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $logRoot "queue-worker-$stamp.out.log") -RedirectStandardError (Join-Path $logRoot "queue-worker-$stamp.err.log")
            [void]$workerProcess.Handle # Retain a process handle so ExitCode remains available after exit.
            Write-QueueLog $paths.Log "PHP launched pid=$($workerProcess.Id); see queue-worker-$stamp.*.log."
        } catch {
            Write-QueueLog $paths.Log "Launch/preflight failed: $($_.Exception.Message) Retrying in 15 seconds."
            Start-Sleep -Seconds 15
        }
    }
} catch {
    if (Test-Path -LiteralPath (Split-Path $paths.Log)) {
        Write-QueueLog $paths.Log "Supervisor failure: $($_.Exception.Message)"
    }
    Write-Error $_ -ErrorAction Continue
    exit 1
} finally {
    # Never kill PHP here: it owns a separate lock and can finish its active job.
    if ($null -ne $workerProcess) { $workerProcess.Dispose() }
    if ($null -ne $supervisor) { $supervisor.Dispose() }
}
