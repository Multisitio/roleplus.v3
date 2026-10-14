# Credenciales y alerta del 14 de octubre de 2026

La alerta de GitGuardian referencia 9decbbc y Generic Password; la captura
no permite identificar la linea exacta. Ese commit anade valores sinteticos
en test-lazy-user-dependencies.php, sin cuentas reales ni conexion a Google.
Los literales del doble se sustituyeron por valores generados en memoria.
La alerta externa no se ha cerrado ni clasificado mediante GitGuardian.

La revision adicional de fuentes versionados encontro una contraseña SSH
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

La contraseña SSH real sigue en el historial Git. La retirada del literal
no la revoca. Queda pendiente autorizar y coordinar su rotacion: mantener
abierta la conexion actual, comprobar los accesos afectados, actualizar
keys.ssh.password local y verificar una segunda conexion independiente
antes de cerrar la primera. No se reescribe el historial por iniciativa
propia ni se afirma que la credencial expuesta ha quedado revocada.
