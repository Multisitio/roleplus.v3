$ErrorActionPreference = 'Stop'
if ($env:COMPUTERNAME -ine 'R2-D2') { throw 'Ejecutar solo en R2-D2.' }
$taskRoot = 'C:\xampp\var\vigilante-replicas'
$taskIdentity = [System.Security.Principal.WindowsIdentity]::GetCurrent()
$taskPrincipal = New-Object System.Security.Principal.WindowsPrincipal($taskIdentity)
if (-not $taskPrincipal.IsInRole([System.Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw 'Ejecutar en PowerShell como administrador.'
}
Add-Type -AssemblyName System.ServiceProcess
$taskService = New-Object System.ServiceProcess.ServiceController('sshd')
if ($taskService.Status -ne [System.ServiceProcess.ServiceControllerStatus]::Stopped) {
    throw 'SSH no esta detenido: no se interrumpe una sesion activa.'
}
$taskRegistry = 'HKLM:\SYSTEM\CurrentControlSet\Services\sshd'
$taskOriginal = Get-ItemProperty -LiteralPath $taskRegistry
$taskBinary = Join-Path $env:WINDIR 'System32\OpenSSH\sshd.exe'
if ($taskOriginal.ImagePath.Trim('"') -ine $taskBinary -or $taskOriginal.Start -ne 4) {
    throw 'El servicio no coincide con la instalacion pendiente y deshabilitada; no se modifica.'
}
$taskConfig = [System.IO.File]::ReadAllText((Join-Path $env:ProgramData 'ssh\sshd_config'))
if ($taskConfig -notmatch '(?m)^# Codex: administracion autorizada desde C-3PO\.' -or
    $taskConfig -notmatch '(?m)^PasswordAuthentication no\r?$') { throw 'Configuracion inesperada.' }
$taskTrace = Join-Path $taskRoot 'maintenance-ssh-service-native.log'
$taskResultPath = Join-Path $taskRoot 'maintenance-ssh-service-trace.json'
$taskUtf8 = New-Object System.Text.UTF8Encoding($false)
$taskResult = [ordered]@{
    fecha = [DateTimeOffset]::Now.ToString('o'); estado = 'iniciando'
    image_path_original = $taskOriginal.ImagePath; inicio_original = $taskOriginal.Start
    restaurado = $false; servicio_final = 'desconocido'
}
function Save-ServiceTraceResult {
    [System.IO.File]::WriteAllText($taskResultPath, ($taskResult | ConvertTo-Json -Depth 6), $taskUtf8)
}
function Set-ServiceTraceConfig([string]$Image, [string]$Mode) {
    # Cambiar mediante SCM, no solo el Registro: SCM conserva su propia configuracion.
    $taskInfo = New-Object System.Diagnostics.ProcessStartInfo
    $taskInfo.FileName = Join-Path $env:WINDIR 'System32\sc.exe'
    $taskEscaped = $Image.Replace('"', '\"')
    $taskInfo.Arguments = 'config sshd binPath= "' + $taskEscaped + '" start= ' + $Mode
    $taskInfo.UseShellExecute = $false
    $taskInfo.CreateNoWindow = $true
    $taskInfo.RedirectStandardOutput = $true
    $taskInfo.RedirectStandardError = $true
    $taskProcess = New-Object System.Diagnostics.Process
    $taskProcess.StartInfo = $taskInfo
    try {
        $null = $taskProcess.Start()
        if (-not $taskProcess.WaitForExit(5000)) {
            $taskProcess.Kill()
            throw 'SCM no respondio en cinco segundos.'
        }
        if ($taskProcess.ExitCode -ne 0) {
            throw ($taskProcess.StandardOutput.ReadToEnd() + $taskProcess.StandardError.ReadToEnd())
        }
    } finally { $taskProcess.Dispose() }
    $taskActual = Get-ItemProperty -LiteralPath $taskRegistry
    if ($taskActual.ImagePath -cne $Image) { throw 'SCM no guardo la ruta prevista.' }
}
Save-ServiceTraceResult
$taskStopped = $false
try {
    # -E redirige la traza a un archivo privado. DenyUsers=* rechaza toda sesion.
    # No se habilita el cortafuegos ni se debilita la autenticacion.
    $taskImage = '"' + $taskBinary + '" -E "' + $taskTrace + '" -o "LogLevel=DEBUG3" -o "DenyUsers=*"'
    Set-ServiceTraceConfig $taskImage 'demand'
    try {
        $taskService.Start()
        $taskService.WaitForStatus([System.ServiceProcess.ServiceControllerStatus]::Running,
            [TimeSpan]::FromSeconds(8))
        $taskResult['arranque'] = 'en_marcha'
    } catch { $taskResult['error_arranque'] = $_.Exception.ToString() }
} finally {
    # Siempre recupera la ruta original y el inicio deshabilitado.
    try { Set-ServiceTraceConfig $taskOriginal.ImagePath 'disabled' }
    catch { $taskResult['error_restauracion'] = $_.Exception.ToString() }
    try {
        $taskService.Refresh()
        if ($taskService.Status -ne [System.ServiceProcess.ServiceControllerStatus]::Stopped) {
            $taskService.Stop()
            $taskService.WaitForStatus([System.ServiceProcess.ServiceControllerStatus]::Stopped,
                [TimeSpan]::FromSeconds(5))
        }
        $taskService.Refresh()
        $taskResult['servicio_final'] = [string]$taskService.Status
        $taskStopped = $taskService.Status -eq [System.ServiceProcess.ServiceControllerStatus]::Stopped
    } catch { $taskResult['error_parada'] = $_.Exception.ToString() }
    $taskRestored = Get-ItemProperty -LiteralPath $taskRegistry
    $taskResult['restaurado'] = $taskRestored.ImagePath -ceq $taskOriginal.ImagePath -and
        $taskRestored.Start -eq $taskOriginal.Start
    if ([System.IO.File]::Exists($taskTrace)) {
        $taskResult['traza'] = [System.IO.File]::ReadAllText($taskTrace)
    }
    $taskResult['estado'] = 'terminado'
    Save-ServiceTraceResult
    $taskService.Dispose()
}
if (-not $taskStopped -or -not $taskResult.restaurado) {
    throw 'Revisar el informe: no se acredita parada y restauracion completas.'
}
Write-Output 'Traza del servicio guardada. SSH vuelve a estar detenido y deshabilitado; Codex puede leer el resultado.'
