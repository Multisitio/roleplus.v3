# Acceso de mantenimiento de R2-D2

Estado: preparado, NO instalado ni conectado por SSH. La sesion existente
de Chrome remoto en el perfil ROLEplus ya esta conectada; una segunda
sesion abierta desde otro perfil pedia PIN. No era necesario repetir el
inicio de sesion del usuario.

Se prepara `install-r2d2-maintenance-ssh.ps1` para una activacion humana
unica en PowerShell como administrador de R2-D2. La herramienta de control
grafico prohibe ejecutar ordenes de terminal directa o indirectamente.
El script no cambia la politica de ejecucion de PowerShell ni se ejecuta
automaticamente desde la carpeta compartida o desde una web.

Usa el componente OpenSSH.Server de Windows, sin nuevos servicios de pago,
sin crear cuentas ni contrasenas. La clave privada nueva permanece solo
en el directorio privado de C-3PO, con acceso a su usuario, SYSTEM y
Administradores. Es distinta de la identidad restringida de exportacion
del VPS. En R2-D2 se deposita solo una solicitud con la clave publica.

Restricciones previstas: escuchar en 10.40.175.30:22; permitir solo la
cuenta local que ejecute la instalacion y el origen 10.40.175.5; solo
autenticacion publica; sin redirecciones ni terminal interactivo. Si
cambian las direcciones de la red movil hay que revisar la restriccion,
sin abrirla automaticamente a toda Internet. La cuenta administradora
permite mantenimiento elevado: no es un ejecutor aislado para una IA
autonoma. Este acceso lo usa el agente bajo las instrucciones del operador.

Se detiene ante servicio sshd, configuracion o regla propia preexistentes.
No sobrescribe accesos anteriores. Genera claves del servidor, valida la
configuracion con sshd -t y arranca solo tras limitar el cortafuegos. Si
falla, deja el servicio deshabilitado y desactiva su regla nueva. No
reinicia Windows, XAMPP, Apache, MariaDB ni el VPS. Si Windows exige
reinicio al instalar el componente, se detiene y lo comunica.

Comprobado en C-3PO: analisis sintactico sin errores; rechazo al intentar
aplicar en equipo distinto antes de cualquier mutacion; ACL de la clave
privada limitada a tres identidades. No equivalen a validar sshd en R2-D2.

Pendiente tras la activacion: leer el resultado privado
`C:/xampp/var/vigilante-replicas/maintenance-ssh-status.json`, fijar su
clave de servidor obtenida por el recurso compartido autenticado y
comprobar identidad y orden estructurada por SSH. No aceptar una huella
obtenida solo por la conexion que se quiere verificar. Despues activar
y comprobar la tarea de replicas ya preparada. No se marca ninguna de
estas operaciones como realizada.

Referencia de configuracion y permisos de claves de Microsoft:
https://learn.microsoft.com/en-us/windows-server/administration/openssh/openssh-server-configuration
