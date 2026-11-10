# Windows PowerShell 5.1. Dot-sourced by the task and operator commands.
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Get-QueuePaths([string]$AppPath) {
    $root = [IO.Path]::GetFullPath($AppPath).TrimEnd('\')
    $state = Join-Path $root 'storage\framework\laundry-queue'
    [pscustomobject]@{
        Root = $root
        State = $state
        Config = Join-Path $state 'task.json'
        SupervisorLock = Join-Path $state 'supervisor.lock'
        WorkerLock = Join-Path $state 'worker.lock'
        OperatorLock = Join-Path $state 'operator.lock'
        Drain = Join-Path $state 'drain.request'
        WorkerState = Join-Path $state 'worker.json'
        Log = Join-Path $root 'storage\logs\queue-supervisor.log'
    }
}

function Open-QueueLock([string]$Path) {
    try {
        return [IO.File]::Open($Path, [IO.FileMode]::OpenOrCreate, [IO.FileAccess]::ReadWrite, [IO.FileShare]::None)
    } catch [IO.IOException] {
        if (($_.Exception.HResult -band 0xffff) -in 32, 33) { return $null }
        throw
    }
}

function Test-QueueLockHeld([string]$Path) {
    $handle = Open-QueueLock $Path
    if ($null -eq $handle) { return $true }
    $handle.Dispose()
    return $false
}

function Write-QueueLog([string]$Path, [string]$Message) {
    # Rotate only our supervisor log; Laravel's own logging is unchanged.
    if ((Test-Path -LiteralPath $Path) -and (Get-Item -LiteralPath $Path).Length -gt 5MB) {
        Move-Item -LiteralPath $Path -Destination ($Path + '.1') -Force
    }
    Add-Content -LiteralPath $Path -Value ('{0:o} {1}' -f [DateTime]::UtcNow, $Message) -Encoding UTF8
}

function Read-QueueConfig($Paths) {
    $config = Get-Content -LiteralPath $Paths.Config -Raw | ConvertFrom-Json
    if ($config.Version -ne 1 -or $config.TaskName -ne 'LaundryQueueWorker' -or $config.AppPath -ne $Paths.Root) {
        throw 'Task configuration does not match this installation. Reinstall after a graceful stop.'
    }
    foreach ($file in @($config.PhpPath, (Join-Path $Paths.Root 'artisan'),
            (Join-Path $Paths.Root 'vendor\autoload.php'), (Join-Path $Paths.Root '.env'))) {
        if (-not (Test-Path -LiteralPath $file -PathType Leaf)) { throw "Required file missing: $file" }
    }
    return $config
}

function Assert-QueueTask($Paths) {
    $task = Get-ScheduledTask -TaskPath '\' -TaskName 'LaundryQueueWorker' -ErrorAction Stop
    $expected = '-NoProfile -NonInteractive -WindowStyle Hidden -ExecutionPolicy Bypass -File "{0}" -AppPath "{1}"' -f (Join-Path $Paths.Root 'scripts\windows\Run-QueueWorker.ps1'), $Paths.Root
    $shell = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
    $actions = @($task.Actions)
    if ($actions.Count -ne 1 -or $actions[0].Execute -ne $shell -or $actions[0].Arguments -ne $expected -or $actions[0].WorkingDirectory -ne $Paths.Root) {
        throw 'LaundryQueueWorker belongs to a different checkout/action. Refusing to change it.'
    }
    return $task
}

function Invoke-QueuePhp($Paths, $Config, [string[]]$Arguments, [int]$TimeoutSeconds = 30) {
    # No shell interpolation. UseShellExecute=false and ArgumentList is unavailable in .NET Framework.
    foreach ($arg in $Arguments) {
        if ($arg.Contains('"') -or $arg.Contains("`r") -or $arg.Contains("`n") -or $arg.EndsWith('\')) {
            throw 'Unsupported PHP argument.'
        }
    }
    $info = New-Object Diagnostics.ProcessStartInfo
    $info.FileName = $Config.PhpPath
    $info.WorkingDirectory = $Paths.Root
    $info.Arguments = ($Arguments | ForEach-Object { '"' + $_ + '"' }) -join ' '
    $info.UseShellExecute = $false
    $info.CreateNoWindow = $true
    $info.RedirectStandardOutput = $true
    $info.RedirectStandardError = $true
    $process = New-Object Diagnostics.Process
    $process.StartInfo = $info
    try {
        [void]$process.Start()
        $stdout = $process.StandardOutput.ReadToEndAsync()
        $stderr = $process.StandardError.ReadToEndAsync()
        if (-not $process.WaitForExit($TimeoutSeconds * 1000)) {
            $process.Kill() # This is the short-lived diagnostic/restart command, never the worker.
            $process.WaitForExit()
            throw "PHP control command timed out after $TimeoutSeconds seconds. Worker was not killed."
        }
        $output = $stdout.GetAwaiter().GetResult()
        $errors = $stderr.GetAwaiter().GetResult()
        if ($process.ExitCode -ne 0) {
            # Laravel exception text can include connection strings: do not copy it into task logs.
            $hint = ''
            if ($errors -match 'Queue environment check failed \[([a-z-]+)\]') { $hint = " Preflight stage: $($Matches[1])." }
            throw "PHP control command failed (exit $($process.ExitCode)).$hint Inspect PHP/Laravel logs privately."
        }
        return $output
    } finally { $process.Dispose() }
}

function Assert-QueueEnvironment($Paths, $Config) {
    [void](Invoke-QueuePhp $Paths $Config @((Join-Path $Paths.Root 'scripts\windows\check-queue-environment.php'), $Paths.Config))
}

function Get-QueueStatus($Paths) {
    $task = Assert-QueueTask $Paths
    $info = Get-ScheduledTaskInfo -TaskPath '\' -TaskName 'LaundryQueueWorker'
    $worker = $null
    if (Test-Path -LiteralPath $Paths.WorkerState) {
        try { $worker = Get-Content -LiteralPath $Paths.WorkerState -Raw | ConvertFrom-Json } catch { }
    }
    $held = Test-QueueLockHeld $Paths.WorkerLock
    $process = $null
    if ($null -ne $worker) { $process = Get-Process -Id $worker.pid -ErrorAction SilentlyContinue }
    [pscustomobject]@{
        Task = $task.TaskName
        TaskState = $task.State
        TaskEnabled = $task.Settings.Enabled
        LastTaskResult = $info.LastTaskResult
        SupervisorRunning = Test-QueueLockHeld $Paths.SupervisorLock
        WorkerLockHeld = $held
        WorkerPid = $(if ($null -ne $worker) { $worker.pid } else { $null })
        WorkerProcessExists = ($null -ne $process)
        Generation = $(if ($null -ne $worker) { $worker.generation } else { $null })
        Draining = Test-Path -LiteralPath $Paths.Drain
        Log = $Paths.Log
    }
}
