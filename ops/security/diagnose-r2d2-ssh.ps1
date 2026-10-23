param([switch]$Collect)
$ErrorActionPreference = 'Stop'
if ($env:COMPUTERNAME -ine 'R2-D2') { throw 'Ejecutar solo en R2-D2.' }
$taskRoot = 'C:\xampp\var\vigilante-replicas'
$taskOutput = Join-Path $taskRoot 'maintenance-ssh-diagnostic-v2.json'
$taskUtf8 = New-Object System.Text.UTF8Encoding($false)
if (-not $Collect) {
    # Solo supervisa su proceso nuevo; no toca sshd ni procesos del usuario.
    $taskInitial = @{ equipo = $env:COMPUTERNAME; estado = 'iniciando'; limite_segundos = 25 }
    [System.IO.File]::WriteAllText($taskOutput, ($taskInitial | ConvertTo-Json), $taskUtf8)
    $taskShell = Join-Path $PSHOME 'powershell.exe'
    $taskArgs = '-NoProfile -NonInteractive -File "' + $PSCommandPath + '" -Collect'
    $taskChild = Start-Process -FilePath $taskShell -ArgumentList $taskArgs -WindowStyle Hidden -PassThru `
        -RedirectStandardOutput (Join-Path $taskRoot 'maintenance-ssh-diagnostic.out.txt') `
        -RedirectStandardError (Join-Path $taskRoot 'maintenance-ssh-diagnostic.err.txt')
    try {
        Write-Output 'Recogiendo datos; limite total de 25 segundos. Los resultados parciales ya se guardan.'
        if (-not $taskChild.WaitForExit(25000)) {
            & (Join-Path $env:WINDIR 'System32\taskkill.exe') /PID $taskChild.Id /T /F | Out-Null
            $taskPartial = Get-Content -LiteralPath $taskOutput -Raw | ConvertFrom-Json
            $taskPartial | Add-Member -NotePropertyName limite_superado -NotePropertyValue $true -Force
            [System.IO.File]::WriteAllText($taskOutput, ($taskPartial | ConvertTo-Json -Depth 8), $taskUtf8)
            Write-Output 'Limite alcanzado. Guardado el resultado parcial y la comprobacion que se atasco.'
        } else {
            if ($taskChild.ExitCode -ne 0) {
                throw 'El recolector fallo. Codex puede leer maintenance-ssh-diagnostic.err.txt; no es una comprobacion terminada.'
            }
            $taskFinished = Get-Content -LiteralPath $taskOutput -Raw | ConvertFrom-Json
            if ($taskFinished.estado -ne 'terminado') { throw 'El recolector no confirmo un informe completo.' }
            Write-Output 'Comprobacion terminada. Codex puede leer el informe y sus posibles errores.'
        }
    } finally { $taskChild.Dispose() }
    return
}
$taskReport = [ordered]@{
    equipo = $env:COMPUTERNAME
    fecha = [DateTimeOffset]::Now.ToString('o')
    solo_lectura_del_sistema = $true
}
function Save-DiagnosticReport {
    $taskTemporary = $taskOutput + '.collect.tmp'
    [System.IO.File]::WriteAllText($taskTemporary, ($taskReport | ConvertTo-Json -Depth 8), $taskUtf8)
    if ([System.IO.File]::Exists($taskOutput)) {
        [System.IO.File]::Replace($taskTemporary, $taskOutput, [NullString]::Value)
    } else { [System.IO.File]::Move($taskTemporary, $taskOutput) }
}
function Read-DiagnosticSection([string]$Name, [scriptblock]$Read) {
    $taskReport['seccion_en_curso'] = $Name
    $taskReport['estado'] = 'recogiendo'
    Save-DiagnosticReport
    try { $taskReport[$Name] = @(& $Read) }
    catch { $taskReport[$Name] = @{ error = $_.Exception.Message } }
    $taskReport['seccion_en_curso'] = $null
    Save-DiagnosticReport
}
foreach ($taskLog in @('OpenSSH/Admin','OpenSSH/Operational')) {
    $taskLogName = $taskLog
    Read-DiagnosticSection $taskLogName {
        Get-WinEvent -LogName $taskLogName -MaxEvents 20 |
            Select-Object TimeCreated, Id, LevelDisplayName, Message
    }
}
Read-DiagnosticSection 'eventos_servicio' {
    Get-WinEvent -FilterHashtable @{ LogName = 'System'; ProviderName = 'Service Control Manager' } -MaxEvents 150 |
        Where-Object { $_.Message -match 'sshd|OpenSSH' } |
        Select-Object -First 15 TimeCreated, Id, Message
}
Read-DiagnosticSection 'servicio' {
    Get-CimInstance Win32_Service -Filter "Name='sshd'" -OperationTimeoutSec 5 |
        Select-Object Name, State, StartMode, StartName, PathName, ExitCode, ServiceSpecificExitCode
}
Read-DiagnosticSection 'configuracion' {
    Get-Content -LiteralPath (Join-Path $env:ProgramData 'ssh\sshd_config')
}
Read-DiagnosticSection 'permisos' {
    foreach ($taskRelative in @('ssh','ssh\sshd_config','ssh\ssh_host_ed25519_key','ssh\codex_c3po_authorized_keys')) {
        $taskPath = Join-Path $env:ProgramData $taskRelative
        if (Test-Path -LiteralPath $taskPath) {
            $taskAcl = Get-Acl -LiteralPath $taskPath
            [pscustomobject]@{ ruta = $taskPath; propietario = $taskAcl.Owner; sddl = $taskAcl.Sddl }
        }
    }
}
Read-DiagnosticSection 'version_binario' {
    (Get-Item -LiteralPath (Join-Path $env:WINDIR 'System32\OpenSSH\sshd.exe')).VersionInfo |
        Select-Object FileVersion, ProductVersion, FileName
}
Read-DiagnosticSection 'validacion_configuracion' {
    # -t solo valida configuracion y claves: no escucha, arranca ni reinicia servicios.
    $taskPreviousPreference = $ErrorActionPreference
    try {
        $ErrorActionPreference = 'Continue'
        $taskMessages = & (Join-Path $env:WINDIR 'System32\OpenSSH\sshd.exe') -t -f (Join-Path $env:ProgramData 'ssh\sshd_config') 2>&1
        $taskNativeCode = $LASTEXITCODE
        [pscustomobject]@{ salida = @($taskMessages | ForEach-Object { [string]$_ }); codigo = $taskNativeCode }
    } finally { $ErrorActionPreference = $taskPreviousPreference }
}
Read-DiagnosticSection 'direcciones_ipv4' {
    Get-NetIPAddress -AddressFamily IPv4 | Select-Object InterfaceAlias, IPAddress, AddressState
}
Read-DiagnosticSection 'escucha_22' {
    Get-NetTCPConnection -LocalPort 22 -State Listen -ErrorAction SilentlyContinue |
        Select-Object LocalAddress, LocalPort, OwningProcess
}
Read-DiagnosticSection 'reglas_ssh' {
    Get-NetFirewallRule -Name 'Codex-R2D2-SSH-C3PO','OpenSSH-Server-In-TCP' -ErrorAction SilentlyContinue |
        Select-Object Name, Enabled, Direction, Action
}
$taskReport['estado'] = 'terminado'
Save-DiagnosticReport
Write-Output 'Diagnostico guardado. Codex puede leerlo por la carpeta compartida; no hace falta copiarlo aqui.'
