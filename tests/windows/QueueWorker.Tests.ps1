#Requires -Version 4.0
[CmdletBinding()]
param([Parameter(Mandatory)][string]$PhpPath)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$repo = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
. (Join-Path $repo 'scripts\windows\QueueWorker.Common.ps1')
$fixture = Join-Path $env:TEMP ('Laundry queue tests ' + [guid]::NewGuid().ToString('N'))
$children = New-Object 'Collections.Generic.List[Diagnostics.Process]'
$checks = 0
function Assert-Test([bool]$Condition, [string]$Message) {
    if (-not $Condition) { throw "FAIL: $Message" }
    $script:checks++
    Write-Output "PASS: $Message"
}
function Wait-Test([scriptblock]$Condition, [string]$Message) {
    $deadline = [DateTime]::UtcNow.AddSeconds(30)
    while ([DateTime]::UtcNow -lt $deadline) {
        if (& $Condition) { Assert-Test $true $Message; return }
        Start-Sleep -Milliseconds 200
    }
    throw "TIMEOUT: $Message"
}
function Write-TestFile([string]$Path, [string]$Text) {
    [IO.File]::WriteAllText($Path, $Text, (New-Object Text.UTF8Encoding($false)))
}
function Start-TestSupervisor {
    $arguments = '-NoProfile -NonInteractive -ExecutionPolicy Bypass -File "{0}" -AppPath "{1}"' -f (Join-Path $fixture 'scripts\windows\Run-QueueWorker.ps1'), $fixture
    $process = Start-Process -FilePath (Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe') -ArgumentList $arguments -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $fixture ([guid]::NewGuid().ToString('N') + '.out')) -RedirectStandardError (Join-Path $fixture ([guid]::NewGuid().ToString('N') + '.err'))
    [void]$process.Handle
    $children.Add($process)
    return $process
}
function Get-TestWorker { Get-Content -LiteralPath $paths.WorkerState -Raw | ConvertFrom-Json }

try {
    $installerAst = $null
    foreach ($file in (Get-ChildItem -LiteralPath (Join-Path $repo 'scripts\windows') -Filter '*.ps1')) {
        $tokens = $null
        $errors = $null
        $ast = [Management.Automation.Language.Parser]::ParseFile($file.FullName, [ref]$tokens, [ref]$errors)
        Assert-Test ($errors.Count -eq 0) "PowerShell parser: $($file.Name)"
        Assert-Test ($ast.ScriptRequirements.RequiredPSVersion -eq [version]'4.0') "PowerShell 4.0 minimum: $($file.Name)"
        if ($file.Name -eq 'Install-QueueWorker.ps1') { $installerAst = $ast }
    }
    # Exercise the installer's actual parameter binding without executing its body,
    # requesting elevation, registering a task, or reading a real environment.
    $databaseParameter = @($installerAst.ParamBlock.Parameters | Where-Object { $_.Name.VariablePath.UserPath -eq 'ExpectedDatabase' })[0]
    $validateDatabase = [scriptblock]::Create('param(' + $databaseParameter.Extent.Text + ') $ExpectedDatabase')
    foreach ($name in @('ecc-laundry-v1', 'ecc_laundry_private_test', 'ecc-laundry-v1_test', 'ECC123')) {
        Assert-Test ((& $validateDatabase -ExpectedDatabase $name) -eq $name) "ExpectedDatabase accepts $name"
    }
    $invalidNames = @('', 'ecc laundry', ' ecc-laundry-v1', 'ecc-laundry-v1 ',
        'ecc.laundry', '../ecc-laundry-v1', 'ecc\laundry', 'ecc/laundry',
        'ecc;drop', "ecc'laundry", 'ecc"laundry', 'ecc`laundry',
        "ecc`tlaundry", "ecc`rlaundry", "ecc`nlaundry", "ecc-laundry-v1`n", ('ecc_laundr' + [char]0x00e9))
    for ($index = 0; $index -lt $invalidNames.Count; $index++) {
        $rejected = $false
        try { [void](& $validateDatabase -ExpectedDatabase $invalidNames[$index]) }
        catch [Management.Automation.ParameterBindingException] { $rejected = $true }
        Assert-Test $rejected "ExpectedDatabase rejects invalid name case $index"
    }
    foreach ($file in (Get-ChildItem -LiteralPath (Join-Path $repo 'scripts\windows') -Filter '*.php')) {
        & $PhpPath -n -l $file.FullName
        Assert-Test ($LASTEXITCODE -eq 0) "PHP lint: $($file.Name)"
    }
    foreach ($directory in @('scripts\windows', 'storage\logs', 'storage\framework\laundry-queue', 'vendor', 'bootstrap')) {
        [void](New-Item -ItemType Directory -Path (Join-Path $fixture $directory) -Force)
    }
    Copy-Item -Path (Join-Path $repo 'scripts\windows\*') -Destination (Join-Path $fixture 'scripts\windows')
    Write-TestFile (Join-Path $fixture '.env') '# isolated fake application; no credentials'
    Write-TestFile (Join-Path $fixture 'vendor\autoload.php') @'
<?php
function config($key) { return $GLOBALS['fixtureConfig'][$key] ?? null; }
'@
    Write-TestFile (Join-Path $fixture 'bootstrap\app.php') @'
<?php
$GLOBALS['fixtureConfig'] = json_decode(file_get_contents(__DIR__.'/../fixture.json'), true);
return new class implements ArrayAccess {
    public function make($name) { return new class { public function bootstrap() {} }; }
    public function offsetExists($key): bool { return $key === 'db'; }
    public function offsetGet($key): mixed {
        return new class {
            public function connection($name) {
                return new class {
                    public function getConfig($key) { return config('database.'.$key); }
                };
            }
        };
    }
    public function offsetSet($key, $value): void {}
    public function offsetUnset($key): void {}
};
'@
    Write-TestFile (Join-Path $fixture 'artisan') @'
<?php
// Fake only: file signals replace cache/database/job work.
$root = __DIR__;
if (($argv[1] ?? '') === 'queue:restart') {
    file_put_contents($root.'/restart.signal', bin2hex(random_bytes(8)));
    exit(0);
}
$signal = @file_get_contents($root.'/restart.signal');
echo "Synthetic worker started\n";
while (true) {
    if (is_file($root.'/busy')) {
        file_put_contents($root.'/busy.observed', 'active synthetic job');
        usleep(100000);
        continue;
    }
    if (is_file($root.'/crash.signal')) {
        unlink($root.'/crash.signal');
        fwrite(STDERR, "Synthetic worker failure\n");
        exit(23);
    }
    if (@file_get_contents($root.'/restart.signal') !== $signal) { exit(0); }
    usleep(100000);
}
'@
    $paths = Get-QueuePaths $fixture
    $config = [pscustomobject]@{
        Version = 1; TaskName = 'LaundryQueueWorker'; AppPath = $paths.Root
        PhpPath = [IO.Path]::GetFullPath($PhpPath); EnvironmentKind = 'PrivateTest'
        ExpectedDatabase = 'fixture_test'; ExpectedDatabaseHost = '127.0.0.1'; TaskUser = 'fixture'
    }
    Write-TestFile $paths.Config ($config | ConvertTo-Json)
    $effective = @{
        'database.driver' = 'mysql'; 'database.database' = 'fixture_test'; 'database.host' = '127.0.0.1'
        'private_test.enabled' = $true; 'queue.default' = 'database'
        'queue.connections.database.driver' = 'database'; 'queue.connections.database.queue' = 'default'
        'queue.connections.database.retry_after' = 90; 'cache.default' = 'database'
        'cache.stores.database' = @{driver = 'database'}; 'cache.prefix' = 'fixture_test'
    }
    Write-TestFile (Join-Path $fixture 'fixture.json') ($effective | ConvertTo-Json)
    Assert-QueueEnvironment $paths $config
    Assert-Test $true 'Preflight accepts approved isolated target without SQL'
    $config.EnvironmentKind = 'Production'
    $config.ExpectedDatabase = 'ecc-laundry-v1'
    $effective['database.database'] = 'ecc-laundry-v1'
    $effective['private_test.enabled'] = $false
    Write-TestFile $paths.Config ($config | ConvertTo-Json)
    Write-TestFile (Join-Path $fixture 'fixture.json') ($effective | ConvertTo-Json)
    Assert-QueueEnvironment $paths $config
    Assert-Test $true 'Preflight accepts an approved hyphenated production database without SQL'
    $config.EnvironmentKind = 'PrivateTest'
    $config.ExpectedDatabase = 'fixture_test'
    $effective['database.database'] = 'fixture_test'
    $effective['private_test.enabled'] = $true
    Write-TestFile $paths.Config ($config | ConvertTo-Json)
    foreach ($change in @(
        @('database.database', 'production'), @('database.host', 'unexpected-host'),
        @('private_test.enabled', $false), @('queue.default', 'sync'),
        @('queue.connections.database.retry_after', 60), @('cache.prefix', '')
    )) {
        $previous = $effective[$change[0]]
        $effective[$change[0]] = $change[1]
        Write-TestFile (Join-Path $fixture 'fixture.json') ($effective | ConvertTo-Json)
        $rejected = $false
        try { Assert-QueueEnvironment $paths $config } catch { $rejected = $true }
        Assert-Test $rejected "Preflight rejects mismatch: $($change[0])"
        $effective[$change[0]] = $previous
    }
    Write-TestFile (Join-Path $fixture 'fixture.json') ($effective | ConvertTo-Json)
    $first = Start-TestSupervisor
    Wait-Test { (Test-QueueLockHeld $paths.WorkerLock) -and (Test-Path -LiteralPath $paths.WorkerState) } 'Supervisor launches PHP from a path containing spaces'
    $initial = Get-TestWorker
    $duplicate = Start-TestSupervisor
    Wait-Test { $duplicate.HasExited } 'Duplicate supervisor exits instead of launching another worker'
    Assert-Test ((Get-TestWorker).generation -eq $initial.generation) 'Duplicate launch preserves PHP generation'

    # A direct second PHP launch is also rejected, independently of the supervisor lock.
    $rejected = $false
    try {
        [void](Invoke-QueuePhp $paths $config @('-d', ('auto_prepend_file=' + (Join-Path $fixture 'scripts\windows\queue-worker-lock.php')), (Join-Path $fixture 'artisan'), 'queue:work'))
    } catch { $rejected = $_.Exception.Message.Contains('exit 75') }
    Assert-Test $rejected 'PHP lifetime lock rejects a duplicate worker'

    [void](Invoke-QueuePhp $paths $config @((Join-Path $fixture 'artisan'), 'queue:restart'))
    Wait-Test { (Test-QueueLockHeld $paths.WorkerLock) -and (Get-TestWorker).generation -ne $initial.generation } 'Clean queue:restart exit is replaced automatically'
    $beforeCrash = Get-TestWorker
    Write-TestFile (Join-Path $fixture 'crash.signal') 'fail'
    Wait-Test { (Test-QueueLockHeld $paths.WorkerLock) -and (Get-TestWorker).generation -ne $beforeCrash.generation } 'Unexpected PHP exit is replaced automatically'

    $orphan = Get-TestWorker
    $first.Kill()
    $first.WaitForExit()
    Assert-Test (Test-QueueLockHeld $paths.WorkerLock) 'PHP remains protected when its supervisor crashes'
    $replacement = Start-TestSupervisor
    Wait-Test { Test-QueueLockHeld $paths.SupervisorLock } 'Replacement supervisor obtains its lock'
    Start-Sleep -Seconds 2
    Assert-Test ((Get-TestWorker).generation -eq $orphan.generation) 'Replacement supervisor does not duplicate surviving PHP'

    Write-TestFile (Join-Path $fixture 'busy') 'active job'
    Wait-Test { Test-Path -LiteralPath (Join-Path $fixture 'busy.observed') } 'Synthetic active job started'
    Write-TestFile $paths.Drain 'drain'
    [void](Invoke-QueuePhp $paths $config @((Join-Path $fixture 'artisan'), 'queue:restart'))
    Start-Sleep -Seconds 2
    Assert-Test (Test-QueueLockHeld $paths.WorkerLock) 'Drain signal does not interrupt an active job'
    Remove-Item -LiteralPath (Join-Path $fixture 'busy')
    Wait-Test { $replacement.HasExited -and -not (Test-QueueLockHeld $paths.WorkerLock) } 'Drain completes after active job and exits supervisor'
    $stopped = Start-TestSupervisor
    Wait-Test { $stopped.HasExited } 'Persistent drain prevents relaunch after another supervisor start'
    Assert-Test (-not (Test-QueueLockHeld $paths.WorkerLock)) 'No worker remains after drain'
    Write-Output "Completed $checks isolated checks. No real task/database/SMTP was used."
} finally {
    # Cleanup only the verified temporary fixture. Never touch a real checkout or task.
    $resolvedFixture = [IO.Path]::GetFullPath($fixture)
    $tempRoot = [IO.Path]::GetFullPath($env:TEMP).TrimEnd('\') + '\'
    if (-not $resolvedFixture.StartsWith($tempRoot, [StringComparison]::OrdinalIgnoreCase) -or
        -not ([IO.Path]::GetFileName($resolvedFixture)).StartsWith('Laundry queue tests ')) { throw 'Unsafe fixture cleanup target.' }
    if (Test-Path -LiteralPath (Join-Path $fixture 'storage\framework\laundry-queue\worker.json')) {
        $identity = Get-Content -LiteralPath (Join-Path $fixture 'storage\framework\laundry-queue\worker.json') -Raw | ConvertFrom-Json
        $candidate = Get-Process -Id $identity.pid -ErrorAction SilentlyContinue
        if ($null -ne $candidate -and $candidate.Path -eq [IO.Path]::GetFullPath($PhpPath) -and
            [Math]::Abs(($candidate.StartTime.ToUniversalTime() - [DateTime]::Parse($identity.startedUtc).ToUniversalTime()).TotalSeconds) -lt 2) {
            Stop-Process -Id $identity.pid -Force -ErrorAction SilentlyContinue
        }
    }
    foreach ($process in $children) {
        if (-not $process.HasExited) { $process.Kill(); $process.WaitForExit() }
        $process.Dispose()
    }
    if (Test-Path -LiteralPath $fixture) { Remove-Item -LiteralPath $resolvedFixture -Recurse -Force }
}
