# Laravel queue worker on Windows / Laragon

Target branch: `codex/private-school-test-2026-10-11`. These scripts do not deploy
code, migrate/seed data, edit `.env`, change credentials or generate `APP_KEY`.
`queue:restart` writes Laravel's operational restart timestamp to its cache.
Nothing is added to Laravel providers, HTTP requests, or application startup.

## Server prerequisites

- Windows Server 2012 or newer, Windows PowerShell **4.0 or newer** (with its
  .NET Framework 4.5 prerequisite), the built-in `ScheduledTasks` module and
  Task Scheduler. PowerShell 5.1 is not required. Confirm the shell version with
  `$PSVersionTable.PSVersion` and module availability with
  `Get-Module -ListAvailable ScheduledTasks`.
- Default checkout: `C:\laragon\private\laundry-release`.
- Default PHP: `C:\laragon\bin\php\php-8.3.36\php.exe`. Confirm the file exists
  and its CLI `php.ini` loads PDO MySQL, OpenSSL and the application's extensions.
- Installed Composer dependencies and an existing `.env` / `APP_KEY`.
- MySQL must itself start at boot (for example, as a Windows service). A task
  cannot make Laragon's interactive GUI start MySQL while nobody is logged in.
  The supervisor retries failures until the dependency is available.
- A dedicated Windows account with a password and **Log on as a batch job**,
  not denied that right by local/domain policy. Use password logon, not S4U or
  “Run only when user is logged on”. The worker runs with limited privileges.
- That account needs read/execute on PHP and the checkout (including `.env`,
  `vendor` and cached configuration), modify permission on `storage` and
  `bootstrap\cache`, network access to the approved database and SMTP endpoint,
  and access to any configured TLS certificates/CA files. Use local absolute
  paths, not mapped drives. Grant access only to the needed account/directories;
  do not grant Everyone full control. Administrator access is required for the
  installation/management commands, not for the PHP worker.

The read-only PHP preflight resolves the effective Laravel configuration,
including `config:cache` and database URLs. It accepts MySQL, the `database`
queue connection with queue `default`, and `retry_after > 60`. The application,
queue, and database cache must use the explicitly approved database/host.
File cache is accepted only inside this checkout's storage directory. Array,
null and external/shared Redis caches are refused. Database cache requires a
nonempty prefix; keep that prefix distinct between deployments. Ensure the
existing queue/cache/failed-job tables exist through your separately reviewed
deployment procedure. Installation does not create them or query their contents.

## One-time installation

Use **PowerShell as Administrator on the server**. Example for the isolated
private test (replace the Windows account name):

```powershell
cd C:\laragon\private\laundry-release
.\scripts\windows\Install-QueueWorker.ps1 -EnvironmentKind PrivateTest -ExpectedDatabase ecc_laundry_private_test -Credential (Get-Credential 'SERVER\LaundryWorker')
.\scripts\windows\Manage-QueueWorker.ps1 -Action Status
```

Enter the Windows account's password in the credential prompt; it is passed to
Windows Task Scheduler, never written into repository files, command arguments
or the task JSON. Restrict access to task registration and this checkout.
Update the task's stored credentials when the account's password changes.

`PrivateTest` also requires effective `PRIVATE_TEST_ENABLED=true` and a database
name ending in `_test`. Never copy the production `.env` or point the private
worker at production. The older [private-test guide](../../PRIVATE-TEST.md)
uses `C:\laragon\private\laundry-test`; if that is your actual checkout, supply
`-AppPath C:\laragon\private\laundry-test` to **every** command instead.

The release directory name alone does not establish its environment. If it is
an intentionally approved production installation, use `-EnvironmentKind
Production -ExpectedDatabase YOUR_APPROVED_DATABASE` instead. This requires
private-test mode to be disabled. Supply `-ExpectedDatabaseHost` if the host is
not `127.0.0.1`. No configuration is automatically corrected to pass these
checks. Confirm the database name/host privately before installing.
`ExpectedDatabase` accepts only ASCII letters, digits, underscores and hyphens
(for example, `ecc-laundry-v1`); empty names, whitespace and other punctuation
are rejected. Private-test names must still end in `_test`.

There is exactly one root-level task, **LaundryQueueWorker**. It will not be
replaced if its action belongs to another checkout. To run production and
private-test workers simultaneously requires separately designed task names
and isolation; do not reuse this single task for both.

Reinstalling uses `Register-ScheduledTask -Force`, not another task name. With
an active worker and identical settings, installation is a no-op. Changed
settings require a graceful stop first. A previous stop/drain marker is
preserved; installation never silently resumes a stopped deployment.

## Update the website and relaunch the worker

Fresh clone only (destination must not already exist), in Laragon CMD:

```bat
git clone --branch codex/private-school-test-2026-10-11 --single-branch https://github.com/yahya-dev-e/ecc_laundry2.0.git C:\laragon\private\laundry-release
```

For an existing installation, use an **elevated Laragon CMD**. Drain **before**
overwriting PHP files, Composer dependencies, or cached configuration. Keep
web traffic and the separately managed scheduler out of an incompatible
upgrade using the site's existing maintenance procedure. Back up and review
any database migration separately; this queue procedure runs none.

Run each command only if the preceding command succeeds:

```bat
cd /d C:\laragon\private\laundry-release
git branch --show-current
git status --short
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts\windows\Manage-QueueWorker.ps1 -Action Stop
git pull --ff-only origin codex/private-school-test-2026-10-11
```

Before pulling, the branch must be `codex/private-school-test-2026-10-11` and
the checkout must be clean. If dependencies/assets changed, complete the
repository's Composer install and frontend build instructions and check their
exit codes. Complete any separately approved migrations before starting the
worker. Do not overwrite `.env` or regenerate `APP_KEY`. Then refresh only the
application's compiled files using the explicit PHP binary:

```bat
"C:\laragon\bin\php\php-8.3.36\php.exe" artisan config:cache
"C:\laragon\bin\php\php-8.3.36\php.exe" artisan route:cache
"C:\laragon\bin\php\php-8.3.36\php.exe" artisan view:cache
```

After the website update succeeds, this is the **relaunch command**:

```bat
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts\windows\Manage-QueueWorker.ps1 -Action Restart
```

It returns success only after observing a managed PHP generation continuously
running for five seconds. A standalone `Restart` also drains any existing
managed worker, so it works for a simple restart without a code update.
`-Action Start` follows the same guarded restart procedure, including enabling
a previously disabled task. On a failed update, leave the task stopped; fix or
roll back the files before explicitly relaunching it. An interrupted restart
before startup preserves the drain marker and disabled task. If startup
verification times out, the task stays enabled to retry, and the command fails
with instructions to inspect logs.

Do not use `optimize:clear` or `cache:clear` as part of this worker restart:
flushing a shared cache can remove the restart signal. The normal deployment
path stops workers before rebuilding config, so changes in cache settings
cannot strand an old worker listening to its previous cache. If you already
changed those settings while an old worker was running, restore its old cache
configuration to drain it; the scripts intentionally will not force-kill it.

**Laragon Restart All** restarts Laragon-managed web/database services. It has
no repository-defined hook to execute this PowerShell deployment command and
does not reload a detached PHP queue worker. There is no hook configured here.
Use the explicit restart command after every successful update or intentional
website relaunch. An Apache-only restart without code/config changes does not
require restarting the worker. Restarting MySQL may interrupt active database
work; avoid Restart All during a job when an Apache graceful reload suffices.
Check Laragon's active PHP/version and Apache configuration separately; these
scripts manage only the queue worker, not Apache, MySQL, or the scheduler.

## Verify, stop and diagnose

In elevated Laragon CMD:

```bat
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts\windows\Manage-QueueWorker.ps1 -Action Status
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts\windows\Manage-QueueWorker.ps1 -Action Stop
```

`Stop` disables all future triggers, leaves a persistent drain marker and
waits for the worker to finish its job. The default wait is 180 seconds; add
`-WaitSeconds 600` for a longer job. On timeout it reports failure and preserves
the drain state, **without killing PHP**. Do not use Task Scheduler's End,
`Stop-ScheduledTask`, `taskkill`, or reboot as a normal deployment drain.
Disabling the task alone does not stop the already running worker.

In elevated PowerShell, inspect registration, execution and logs:

```powershell
Get-ScheduledTask -TaskPath '\' -TaskName LaundryQueueWorker | Format-List State,Actions,Principal,Settings,Triggers
Get-ScheduledTaskInfo -TaskPath '\' -TaskName LaundryQueueWorker
Export-ScheduledTask -TaskPath '\' -TaskName LaundryQueueWorker
Get-Content C:\laragon\private\laundry-release\storage\logs\queue-supervisor.log -Tail 50
Get-ChildItem C:\laragon\private\laundry-release\storage\logs\queue-worker-*.log | Sort-Object LastWriteTime -Descending | Select-Object -First 6
Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" | Select-Object ProcessId,ParentProcessId,ExecutablePath,CommandLine
Get-Acl C:\laragon\private\laundry-release\storage | Format-List
Get-WinEvent -LogName Microsoft-Windows-TaskScheduler/Operational -MaxEvents 20
& 'C:\laragon\bin\php\php-8.3.36\php.exe' artisan queue:failed
```

Enable Task Scheduler history if its Operational log is disabled. The running
task result `267009` (`0x41301`) is normal, not a failure. Worker stdout/stderr
are separate, timestamped files; the supervisor keeps about 30 generations
and rotates its own log at 5 MB with one backup. Laravel logs/failed jobs are
not deleted. Logs can contain job details; restrict access and inspect them
privately. A failed preflight intentionally suppresses connection strings and
credentials. Check effective config locally, PHP extensions/`php.ini`, account
ACLs, Task Scheduler logon errors, existing queue/cache tables, database
connectivity and SMTP/TLS/firewall permissions. Do not share `.env` or cached
config/logs containing secrets.

Status reports the task, supervisor lock, PHP lock, PID and random generation.
Old `worker.json` alone is not evidence of a running worker. A held worker lock
and process existing establish process presence, not successful SQL or email
delivery. Verify a controlled, approved test job and its outcome separately.
Review failed jobs before any explicit retry; never automatically retry all
failed jobs merely because the worker restarted.

## Lifecycle and limits

| Event | Behavior |
| --- | --- |
| Windows boot, no logged-in user | Startup trigger launches hidden PowerShell with password logon; PHP runs from the configured checkout. |
| Task already running | `IgnoreNew` plus a checkout-level supervisor file lock prevents another supervisor. |
| PHP startup | PHP `auto_prepend_file` takes an exclusive lock for its entire lifetime, then runs `artisan queue:work --sleep=3 --tries=3 --timeout=60 --no-interaction --no-ansi`. No persistent PHP/INI change is made. |
| `queue:restart` / clean worker exit | Supervisor launches a replacement after a short delay; it does not depend on Task Scheduler treating exit 0 as failure. |
| PHP crash / startup or DB failure | Supervisor retries (3 seconds after child exit, 15 after launch/preflight failure); worker logs expose repeated startup failures. |
| PowerShell supervisor crash | Task failure retry and an indefinite one-minute trigger recover it. A surviving PHP worker retains its own lock; the replacement supervisor waits for it instead of duplicating it. |
| Deployment restart | Disable triggers, create drain marker, validate target, repeatedly signal `queue:restart` across timestamp boundaries, wait for PHP and supervisor locks to release, then enable/start a fresh supervisor. |
| Stop / failed drain | Keep task disabled and drain marker present; no forced job interruption or automatic restart across reboot. |
| HTTP request / ordinary Laravel bootstrap | Does not start, stop or restart workers. |

`ExecutionTimeLimit=PT0S` removes the default Task Scheduler runtime limit.
The one-minute trigger also covers a supervisor exiting normally or exhausting
its failure retries. The PHP lock is not inherited from PowerShell: PHP itself
holds it, so it also protects against orphan workers. Lock files remain on
disk but unlock automatically when their owning process exits. Never delete
lock/state files while a worker is running. All workers for this checkout
must use this task; an old manual `php artisan queue:work`, another scheduled
task, NSSM/service, container or another checkout is outside these locks.
Inventory and gracefully retire those before first installation. Shared
databases/queues across separate checkouts can still create competing workers;
use separate databases and credentials, as the private-test guide requires.

Native Windows PHP does not provide Laravel's PCNTL asynchronous timeout
handling. The requested `--timeout=60` is retained but **cannot guarantee a
60-second hard timeout here**. Configure finite database/network/SMTP timeouts
and ensure every job completes before the database queue's `retry_after` to
avoid overlapping redelivery. A hung job causes a graceful drain timeout and
needs diagnosis; the scripts will not silently kill it. Power loss, forced
termination and database/SMTP failures can still interrupt jobs or lead to
redelivery. These scripts do not guarantee exactly-once mail delivery.

A dedicated Windows service supervisor can offer central service management,
but adds installation and lifecycle dependencies. The built-in task plus
supervisor provides startup/recovery without NSSM/WinSW. Reassess a service
or Linux supervisor if operational requirements grow; avoid running both for
the same checkout.

References: [Laravel queue deployment/restarts and timeout requirements](https://laravel.com/docs/12.x/queues),
[Microsoft task runtime limits](https://learn.microsoft.com/en-us/windows/win32/taskschd/tasksettings-executiontimelimit),
[task settings](https://learn.microsoft.com/en-us/powershell/module/scheduledtasks/new-scheduledtasksettingsset),
[task logon/security contexts](https://learn.microsoft.com/en-us/windows/win32/taskschd/security-contexts-for-running-tasks).

## Local verification

Run the isolated tests from the repository root in Windows PowerShell 4.0 or newer:

```powershell
.\tests\windows\QueueWorker.Tests.ps1 -PhpPath 'C:\path\to\php.exe'
```

They parse all scripts, lint PHP, and exercise supervisor/worker locking,
normal/crash exit recovery and graceful drain using a temporary fake Laravel
application. No task is registered, no real `.env` is read, and no database or
SMTP connection is made. This does not certify Windows Server/Laragon startup,
actual account permissions, Task Scheduler registration, real Laravel job
execution or external delivery; those require an isolated server rehearsal.

For development, the optional PSScriptAnalyzer compatibility settings target
PowerShell 4.0 syntax and the Windows Server 2012 R2 / PowerShell 4.0 command
and .NET type profile. Run from a development machine with PSScriptAnalyzer
installed; no analyzer dependency is needed on the server:

```powershell
$issues = @(Invoke-ScriptAnalyzer -Path scripts/windows -Recurse -Settings tests/windows/PowerShell4.Settings.psd1)
$issues
if ($issues.Count -gt 0) { throw 'PowerShell 4.0 compatibility check failed.' }
```

The scripts use .NET Framework 4.5 async stream reads so stdout/stderr cannot
deadlock a PHP control command. Those APIs are available with PowerShell 4.0;
the task uses the existing `powershell.exe`, without installing another shell.
After pulling this compatibility fix, rerun the same installation command if
the previous install stopped at the version requirement. Existing task names,
configuration and management commands are unchanged.

Local result on 10 November 2026: all 53 isolated checks passed using Windows
PowerShell 5.1 and portable PHP 8.4.27, including assertions that every worker
script declares PowerShell 4.0 as its minimum. PSScriptAnalyzer 1.24.0 reported
zero findings for the PowerShell 4.0 syntax, command/parameter and .NET type
compatibility rules above. An actual PowerShell 4.0 runtime
and the target PHP 8.3.36 / Windows Server / Laragon deployment were not
runtime-tested.
