# Credenciales y alerta del 14 de octubre de 2026

La alerta de GitGuardian referencia 9decbbc y Generic Password; la captura
no permite identificar la linea exacta. Ese commit anade valores sinteticos
en test-lazy-user-dependencies.php, sin cuentas reales ni conexion a Google.
Los literales del doble se sustituyeron por valores generados en memoria.
La alerta externa no se ha cerrado ni clasificado mediante GitGuardian.

La revision adicional de fuentes versionados encontro una contraseÃ±a SSH
en ssh_cmd.py y una clave con formato Google en un ejemplo comentado de
suscripciones copy.php. Se retiro el ejemplo, incluido su token de destino.
La clave del comentario no coincide con ninguna clave de los config/keys.php
local y de produccion. Eso no demuestra que este revocada o que no se use
en otro sistema. No se realizaron peticiones al proveedor con esa clave.

SSH es administracion local: keys.ssh se guarda solo en el keys.php local,
que no esta versionado. ssh_cmd.py usa el Config del framework mediante PHP,
captura los datos en memoria y no los imprime. upload.py comparte ese lector;
se elimina su copia literal y se versiona el helper saneado. Los dos helpers
son clientes locales, no scripts de la aplicacion que deban desplegarse en
el servidor. No se replica keys.php ni la seccion SSH a produccion.

El script de migracion multimedia obtiene datos de conexion mediante
Config::read('databases') en vez de incluir directamente databases.php.
No se cambia esa configuracion propia del entorno ni se ejecuta la migracion.
El script no estaba desplegado: su validacion de sintaxis se hace en Linux
como fixture propia, sin instalar una nueva tarea de migracion.

Validacion: claves SSH antes/despues iguales en memoria; Config local
funciona; SSH real con comando inocuo true; sintaxis Python y PHP; test
local y de produccion del login Google simulado aprobado. Replica del
modelo antiguo saneado y de las instrucciones privada verificada. No se
cambian limites, sesiones, peticiones de IA, claves Google activas o servicios.

La contrasena SSH antigua sigue en el historial Git, pero se roto el
14 de octubre de 2026 con autorizacion expresa del usuario. Se mantuvo una
conexion autenticada con la contrasena anterior mientras se actualizaba
el password del usuario de administracion. La nueva contrasena aleatoria
se comprobo mediante una segunda conexion independiente con autenticacion
por password, sin agente ni claves privadas. Se actualizo keys.ssh.password
local y se verifico de nuevo el helper antes de cerrar los accesos abiertos.
No se imprimio la contrasena ni se copio keys.php a produccion. No se
reescribe el historial. Otros clientes externos guardados deben usar la
contrasena nueva que figura en la configuracion local. Portadas RolePlus y
MTGsearch respondieron HTTP 200 despues de la rotacion.
