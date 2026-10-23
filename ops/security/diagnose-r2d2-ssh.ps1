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
            if ($null -ne $taskChild.ExitCode -and $taskChild.ExitCode -ne 0) {
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
    $taskClock = [System.Diagnostics.Stopwatch]::StartNew()
    $taskReport['seccion_en_curso'] = $Name
    $taskReport['estado'] = 'recogiendo'
    Save-DiagnosticReport
    try { $taskReport[$Name] = @(& $Read) }
    catch { $taskReport[$Name] = @{ error = $_.Exception.Message } }
    $taskReport[$Name + '_segundos'] = [Math]::Round($taskClock.Elapsed.TotalSeconds, 3)
    $taskReport['seccion_en_curso'] = $null
    Save-DiagnosticReport
}
Read-DiagnosticSection 'configuracion' {
    [System.IO.File]::ReadAllText((Join-Path $env:ProgramData 'ssh\sshd_config'))
}
Read-DiagnosticSection 'arranque_directo' {
    if ((Get-Service sshd).Status -ne 'Stopped') { throw 'Servicio activo: no se ejecuta otra instancia.' }
    $taskDebugOut = Join-Path $taskRoot 'maintenance-ssh-native.out.txt'
    $taskDebugErr = Join-Path $taskRoot 'maintenance-ssh-native.err.txt'
    $taskBinary = Join-Path $env:WINDIR 'System32\OpenSSH\sshd.exe'
    # Instancia temporal: DenyUsers=* impide cualquier inicio de sesion.
    # No arranca el servicio, no modifica su configuracion ni el cortafuegos.
    $taskArgs = '-ddd -e -f "' + (Join-Path $env:ProgramData 'ssh\sshd_config') + '" -o "DenyUsers=*"'
    $taskNative = Start-Process -FilePath $taskBinary -ArgumentList $taskArgs -WindowStyle Hidden -PassThru `
        -RedirectStandardOutput $taskDebugOut -RedirectStandardError $taskDebugErr
    try {
        $taskEnded = $taskNative.WaitForExit(4000)
        if (-not $taskEnded) {
            & (Join-Path $env:WINDIR 'System32\taskkill.exe') /PID $taskNative.Id /T /F | Out-Null
        }
        [pscustomobject]@{
            termino_solo = $taskEnded
            salida = [System.IO.File]::ReadAllText($taskDebugOut)
            detalle = [System.IO.File]::ReadAllText($taskDebugErr)
            servicio_reiniciado = $false
            sesiones_permitidas = $false
        }
    } finally {
        if (-not $taskNative.HasExited) { $taskNative.Kill() }
        $taskNative.Dispose()
    }
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
$taskReport['estado'] = 'terminado'
Save-DiagnosticReport
Write-Output 'Diagnostico guardado. Codex puede leerlo por la carpeta compartida; no hace falta copiarlo aqui.'
