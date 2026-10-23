param([switch]$Apply)
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

# Activacion unica local. No ejecutar mediante la interfaz grafica automatizada.
if ($env:COMPUTERNAME -ine 'R2-D2') { throw 'Ejecutar solo en R2-D2.' }
$taskRoot = 'C:\xampp\var\vigilante-replicas'
$taskRequestPath = Join-Path $taskRoot 'access\maintenance-request.json'
$taskRequest = Get-Content -LiteralPath $taskRequestPath -Raw | ConvertFrom-Json
if ($taskRequest.version -ne 1) { throw 'Solicitud no compatible.' }
$taskClient = [System.Net.IPAddress]::Parse([string]$taskRequest.client_ipv4)
$taskServer = [System.Net.IPAddress]::Parse([string]$taskRequest.server_ipv4)
foreach ($taskAddress in @($taskClient, $taskServer)) {
    if ($taskAddress.AddressFamily -ne [System.Net.Sockets.AddressFamily]::InterNetwork -or
        $taskAddress.GetAddressBytes()[0] -ne 10) { throw 'Se exige la red privada actual 10.x.x.x.' }
}
if (-not (Get-NetIPAddress -AddressFamily IPv4 | Where-Object IPAddress -eq $taskServer.ToString())) {
    throw 'La direccion prevista ya no pertenece a R2-D2; revisar la red antes de instalar.'
}
$taskPublicKey = [string]$taskRequest.public_key
if ($taskPublicKey -notmatch '^ssh-ed25519 [A-Za-z0-9+/]+={0,2} codex-c3po-r2d2-20261023$') {
    throw 'Clave publica no valida.'
}
$taskIdentity = [System.Security.Principal.WindowsIdentity]::GetCurrent()
$taskParts = $taskIdentity.Name.Split('\')
if ($taskParts.Count -ne 2 -or $taskParts[0] -ine $env:COMPUTERNAME) {
    throw 'Se necesita una cuenta local de R2-D2; no se crean cuentas ni contrasenas.'
}
$taskUser = $taskParts[1].ToLowerInvariant()
if ($taskUser -match '["\r\n@*?!]') { throw 'Nombre de cuenta no compatible con la restriccion SSH.' }
$taskConfigRoot = Join-Path $env:ProgramData 'ssh'
$taskConfig = Join-Path $taskConfigRoot 'sshd_config'
$taskKeyFile = Join-Path $taskConfigRoot 'codex_c3po_authorized_keys'
$taskRule = 'Codex-R2D2-SSH-C3PO'
if ((Get-Service sshd -ErrorAction SilentlyContinue) -or
    (Test-Path -LiteralPath $taskConfig) -or
    (Test-Path -LiteralPath $taskKeyFile) -or
    (Get-NetFirewallRule -Name $taskRule -ErrorAction SilentlyContinue)) {
    throw 'Ya hay configuracion SSH o una regla previa: no se sobrescribe. Revisar el estado existente.'
}
if (-not $Apply) {
    [pscustomobject]@{
        equipo = $env:COMPUTERNAME; usuario = $taskIdentity.Name
        escuchar = $taskServer.ToString(); cliente_unico = $taskClient.ToString()
        puerto = 22; autenticacion = 'solo clave publica'; aplicado = $false
    } | ConvertTo-Json
    return
}
$taskPrincipal = New-Object System.Security.Principal.WindowsPrincipal($taskIdentity)
if (-not $taskPrincipal.IsInRole([System.Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw 'La activacion unica necesita PowerShell como administrador en R2-D2.'
}
$taskCapability = Get-WindowsCapability -Online -Name 'OpenSSH.Server~~~~0.0.1.0'
$taskCreatedRule = $false
$taskInstalled = $false
try {
    if ($taskCapability.State -ne 'Installed') {
        $taskResult = Add-WindowsCapability -Online -Name $taskCapability.Name
        $taskInstalled = $true
        if ($taskResult.RestartNeeded) { throw 'Windows pide reinicio; no se reinicia automaticamente.' }
    }
    Stop-Service sshd -ErrorAction SilentlyContinue
    Set-Service sshd -StartupType Disabled
    # La regla amplia solo puede proceder de esta instalacion nueva.
    if ($taskInstalled) {
        Get-NetFirewallRule -Name 'OpenSSH-Server-In-TCP' -ErrorAction SilentlyContinue |
            Disable-NetFirewallRule | Out-Null
    }
    New-Item -ItemType Directory -Path $taskConfigRoot -Force | Out-Null
    $taskAcl = New-Object System.Security.AccessControl.FileSecurity
    $taskAcl.SetAccessRuleProtection($true, $false)
    foreach ($taskSidText in @('S-1-5-18','S-1-5-32-544')) {
        $taskSid = New-Object System.Security.Principal.SecurityIdentifier($taskSidText)
        $taskAcl.AddAccessRule((New-Object System.Security.AccessControl.FileSystemAccessRule(
            $taskSid, 'FullControl', 'Allow')))
    }
    $taskAdminSid = New-Object System.Security.Principal.SecurityIdentifier('S-1-5-32-544')
    $taskAcl.SetOwner($taskAdminSid)
    $taskUtf8 = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($taskKeyFile, $taskPublicKey + "`n", $taskUtf8)
    Set-Acl -LiteralPath $taskKeyFile -AclObject $taskAcl
    $taskConfigText = @"
# Codex: administracion autorizada desde C-3PO. Sin acceso por contrasena.
Port 22
AddressFamily inet
ListenAddress $($taskServer.ToString())
HostKey __PROGRAMDATA__/ssh/ssh_host_ed25519_key
PubkeyAuthentication yes
PasswordAuthentication no
AuthenticationMethods publickey
AuthorizedKeysFile __PROGRAMDATA__/ssh/codex_c3po_authorized_keys
AllowUsers "$taskUser@$($taskClient.ToString())"
DisableForwarding yes
PermitTTY no
MaxSessions 2
MaxAuthTries 3
LoginGraceTime 20
LogLevel VERBOSE
Subsystem sftp sftp-server.exe
"@
    [System.IO.File]::WriteAllText($taskConfig, $taskConfigText + "`n", $taskUtf8)
    Set-Acl -LiteralPath $taskConfig -AclObject $taskAcl
    $taskSshd = Join-Path $env:WINDIR 'System32\OpenSSH\sshd.exe'
    $taskKeygen = Join-Path $env:WINDIR 'System32\OpenSSH\ssh-keygen.exe'
    & $taskKeygen -A
    if ($LASTEXITCODE -ne 0) { throw 'No se pudieron generar las claves de servidor.' }
    & $taskSshd -t -f $taskConfig
    if ($LASTEXITCODE -ne 0) { throw 'La configuracion SSH no pasa su validador nativo.' }
    New-NetFirewallRule -Name $taskRule -DisplayName 'Codex: R2-D2 solo desde C-3PO' `
        -Direction Inbound -Action Allow -Protocol TCP -LocalPort 22 `
        -LocalAddress $taskServer.ToString() -RemoteAddress $taskClient.ToString() `
        -Program $taskSshd -Profile Any | Out-Null
    $taskCreatedRule = $true
    Set-Service sshd -StartupType Automatic
    Start-Service sshd
    if ((Get-Service sshd).Status -ne 'Running') { throw 'SSH no esta en marcha.' }
    $taskStatus = [ordered]@{
        version = 1; installed_at = [DateTimeOffset]::Now.ToString('o')
        computer = $env:COMPUTERNAME; user = $taskUser
        server_ipv4 = $taskServer.ToString(); client_ipv4 = $taskClient.ToString(); port = 22
        host_public_key = (Get-Content -LiteralPath (Join-Path $taskConfigRoot 'ssh_host_ed25519_key.pub') -Raw).Trim()
        connection_verified = $false; replica_task_activated = $false
    }
    [System.IO.File]::WriteAllText((Join-Path $taskRoot 'maintenance-ssh-status.json'),
        ($taskStatus | ConvertTo-Json), $taskUtf8)
    Write-Output 'SSH preparado. Falta comprobar la conexion real desde C-3PO y activar la tarea de copias.'
} catch {
    if ($taskCreatedRule) { Disable-NetFirewallRule -Name $taskRule | Out-Null }
    if (Get-Service sshd -ErrorAction SilentlyContinue) {
        Stop-Service sshd -ErrorAction SilentlyContinue
        Set-Service sshd -StartupType Disabled
    }
    throw
}
