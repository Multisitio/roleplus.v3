param([Parameter(Mandatory=$true)][string]$Root)
$ErrorActionPreference = 'Stop'
if ($env:COMPUTERNAME -ine 'R2-D2') { throw 'La tarea debe instalarse en R2-D2, nunca en C-3PO.' }
$Root = (Resolve-Path -LiteralPath $Root).ProviderPath
if ($Root.StartsWith('\\') -or $Root.Contains('"')) { throw 'Usa la ruta local privada de R2-D2.' }
foreach ($taskFile in @('access\config.json','access\known_hosts','access\ssh_config','access\id_ed25519','tools\vigilante-replica-pull.py')) {
    if (-not (Test-Path -LiteralPath (Join-Path $Root $taskFile) -PathType Leaf)) { throw "Falta $taskFile" }
}
$taskPython = (Get-Command python.exe -ErrorAction Stop).Source
& $taskPython -c 'import sys; assert sys.version_info >= (3,9)'
if ($LASTEXITCODE -ne 0) { throw 'Se necesita Python 3.9 o posterior.' }
$null = Get-Command ssh.exe -ErrorAction Stop
$taskName = 'Vigilante-Replica-VPS'
$taskDescription = 'Vigilante: replica de copias existentes del VPS, sin restaurar ni volcar SQL.'
$taskPrevious = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
if ($taskPrevious -and $taskPrevious.Description -ne $taskDescription) { throw 'Existe una tarea distinta con el mismo nombre.' }
$taskUser = [System.Security.Principal.WindowsIdentity]::GetCurrent().Name
$taskAction = New-ScheduledTaskAction -Execute $taskPython -Argument ('-I -B "' + (Join-Path $Root 'tools\vigilante-replica-pull.py') + '" --root "' + $Root + '"') -WorkingDirectory $Root
$taskTriggers = @((New-ScheduledTaskTrigger -AtStartup), (New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 15)))
$taskSettings = New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 20) -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
$taskPrincipal = New-ScheduledTaskPrincipal -UserId $taskUser -LogonType S4U -RunLevel Limited
$taskDefinition = New-ScheduledTask -Action $taskAction -Trigger $taskTriggers -Settings $taskSettings -Principal $taskPrincipal -Description $taskDescription
Register-ScheduledTask -TaskName $taskName -InputObject $taskDefinition -Force | Out-Null
Start-ScheduledTask -TaskName $taskName
Write-Output 'Tarea registrada. Comprobar status.json y LastTaskResult antes de darla por operativa.'
