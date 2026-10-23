$ErrorActionPreference = 'Stop'
if ($env:COMPUTERNAME -ine 'R2-D2') { throw 'Ejecutar solo en R2-D2.' }
$taskRoot = 'C:\xampp\var\vigilante-replicas'
$taskConfigRoot = Join-Path $env:ProgramData 'ssh'
$taskKey = Join-Path $taskConfigRoot 'ssh_host_ed25519_key'
$taskConfigPath = Join-Path $taskConfigRoot 'sshd_config'
$taskIdentity = [System.Security.Principal.WindowsIdentity]::GetCurrent()
$taskPrincipal = New-Object System.Security.Principal.WindowsPrincipal($taskIdentity)
if (-not $taskPrincipal.IsInRole([System.Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw 'Ejecutar en PowerShell como administrador.'
}
Add-Type -AssemblyName System.ServiceProcess
$taskService = New-Object System.ServiceProcess.ServiceController('sshd')
if ($taskService.Status -ne [System.ServiceProcess.ServiceControllerStatus]::Stopped) {
    throw 'SSH no esta detenido; no se interrumpe una sesion activa.'
}
$taskOriginalService = Get-ItemProperty -LiteralPath 'HKLM:\SYSTEM\CurrentControlSet\Services\sshd'
$taskBinary = Join-Path $env:WINDIR 'System32\OpenSSH\sshd.exe'
if ($taskOriginalService.ImagePath.Trim('"') -ine $taskBinary -or $taskOriginalService.Start -ne 4) {
    throw 'Estado de servicio diferente de la instalacion propia pendiente.'
}
$taskRequest = [System.IO.File]::ReadAllText((Join-Path $taskRoot 'access\maintenance-request.json')) | ConvertFrom-Json
$taskConfig = [System.IO.File]::ReadAllText($taskConfigPath)
$taskUser = $taskIdentity.Name.Split('\')[-1].ToLowerInvariant()
foreach ($taskExpected in @(
    '# Codex: administracion autorizada desde C-3PO. Sin acceso por contrasena.',
    ('ListenAddress ' + $taskRequest.server_ipv4),
    ('AllowUsers "' + $taskUser + '@' + $taskRequest.client_ipv4 + '"'),
    'PasswordAuthentication no', 'AuthenticationMethods publickey', 'DisableForwarding yes',
    'HostKey __PROGRAMDATA__/ssh/ssh_host_ed25519_key')) {
    if ($taskConfig.Split("`n").TrimEnd("`r") -cnotcontains $taskExpected) {
        throw ('Configuracion inesperada: ' + $taskExpected)
    }
}
if ((Get-Item -LiteralPath $taskKey).Attributes -band [System.IO.FileAttributes]::ReparsePoint) {
    throw 'La clave es un enlace: no se modifican sus permisos.'
}
$taskBeforeHash = (Get-FileHash -LiteralPath $taskKey -Algorithm SHA256).Hash
$taskBeforeAcl = Get-Acl -LiteralPath $taskKey
$taskUtf8 = New-Object System.Text.UTF8Encoding($false)
$taskAclRecord = Join-Path $taskRoot 'maintenance-ssh-hostkey-acl-before.json'
if (-not [System.IO.File]::Exists($taskAclRecord)) {
    $taskBefore = @{ ruta = $taskKey; sddl = $taskBeforeAcl.Sddl; fecha = [DateTimeOffset]::Now.ToString('o') }
    [System.IO.File]::WriteAllText($taskAclRecord, ($taskBefore | ConvertTo-Json), $taskUtf8)
}
# Correccion exclusiva de la clave configurada. No se copia ni regenera su contenido.
$taskAcl = New-Object System.Security.AccessControl.FileSecurity
$taskAcl.SetAccessRuleProtection($true, $false)
$taskAdmin = New-Object System.Security.Principal.SecurityIdentifier('S-1-5-32-544')
$taskAcl.SetOwner($taskAdmin)
foreach ($taskSidText in @('S-1-5-18','S-1-5-32-544')) {
    $taskSid = New-Object System.Security.Principal.SecurityIdentifier($taskSidText)
    $taskAcl.AddAccessRule((New-Object System.Security.AccessControl.FileSystemAccessRule($taskSid,'FullControl','Allow')))
}
Set-Acl -LiteralPath $taskKey -AclObject $taskAcl
$taskActualAcl = Get-Acl -LiteralPath $taskKey
if ($taskActualAcl.GetOwner([System.Security.Principal.SecurityIdentifier]).Value -ne 'S-1-5-32-544') {
    throw 'No se confirmo el propietario Administradores.'
}
if ((Get-FileHash -LiteralPath $taskKey -Algorithm SHA256).Hash -cne $taskBeforeHash) {
    throw 'El contenido de la clave cambio inesperadamente.'
}
$taskRuleName = 'Codex-R2D2-SSH-C3PO'
$taskSuccess = $false
try {
    # Revisar la regla exacta antes de permitir la conexion.
    $taskRule = Get-NetFirewallRule -Name $taskRuleName
    $taskAddress = $taskRule | Get-NetFirewallAddressFilter
    $taskPort = $taskRule | Get-NetFirewallPortFilter
    $taskProgram = $taskRule | Get-NetFirewallApplicationFilter
    if ([string]$taskAddress.RemoteAddress -ne [string]$taskRequest.client_ipv4 -or
        [string]$taskAddress.LocalAddress -ne [string]$taskRequest.server_ipv4 -or
        [string]$taskPort.LocalPort -ne '22' -or [string]$taskPort.Protocol -notin @('TCP','6') -or
        $taskProgram.Program -ine $taskBinary -or $taskRule.Action -ne 'Allow' -or $taskRule.Direction -ne 'Inbound') {
        throw 'La regla de red no coincide con C-3PO y el puerto previstos.'
    }
    Set-Service sshd -StartupType Automatic
    $taskService.Start()
    $taskService.WaitForStatus([System.ServiceProcess.ServiceControllerStatus]::Running,[TimeSpan]::FromSeconds(8))
    Enable-NetFirewallRule -Name $taskRuleName | Out-Null
    $taskStatus = [ordered]@{
        version = 1; installed_at = [DateTimeOffset]::Now.ToString('o')
        computer = $env:COMPUTERNAME; user = $taskUser
        server_ipv4 = $taskRequest.server_ipv4; client_ipv4 = $taskRequest.client_ipv4; port = 22
        host_public_key = [System.IO.File]::ReadAllText($taskKey + '.pub').Trim()
        hostkey_content_unchanged = $true; hostkey_owner = 'S-1-5-32-544'
        connection_verified = $false; replica_task_activated = $false
    }
    [System.IO.File]::WriteAllText((Join-Path $taskRoot 'maintenance-ssh-status.json'),($taskStatus | ConvertTo-Json),$taskUtf8)
    $taskSuccess = $true
    Write-Output 'SSH iniciado. Codex debe comprobar ahora la conexion real desde C-3PO.'
} finally {
    if (-not $taskSuccess) {
        Disable-NetFirewallRule -Name $taskRuleName -ErrorAction Continue | Out-Null
        $taskService.Refresh()
        if ($taskService.Status -ne [System.ServiceProcess.ServiceControllerStatus]::Stopped) {
            $taskService.Stop()
            $taskService.WaitForStatus([System.ServiceProcess.ServiceControllerStatus]::Stopped,[TimeSpan]::FromSeconds(5))
        }
        Set-Service sshd -StartupType Disabled
    }
    $taskService.Dispose()
}
