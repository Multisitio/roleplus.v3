# ISPConfig y PHP 8.4: cierre del 23 de octubre de 2026

## Resultado en produccion

ISPConfig actualizado de 3.2.12p1 a 3.3.2p1; esquema SQL de 100 a 107.
El panel servido por HTTP, el manejador de aplicaciones y el manejador
FPM general ejecutan PHP 8.4.27 con sus usuarios respectivos. phpMyAdmin
responde correctamente. Las once webs del inventario responden HTTP 200;
ROLEplus y MTGsearch tambien se comprobaron desde el PC del operador.

Retirados los 20 paquetes de PHP 8.1, sin autoremove ni retirada adicional
de dependencias. Solo queda la rama PHP 8.4 instalada. ISPConfig refleja
el manejador general nuevo; la opcion antigua queda inactiva y se conserva
su ficha. Se mantienen las rutas historicas de sockets web con nombre 8.1:
son rutas utilizadas por procesos 8.4, no consumidores de PHP 8.1.

## Conservacion y comprobaciones

Las configuraciones de los quince archivos de sitios y los dieciseis pools
web personalizados conservan sus huellas anteriores. Se contrastaron los
valores de configuracion del panel con la copia SQL original: solo cambian
los siete campos previstos para PHP 8.4. Las claves no se registran ni se
versionan. Las plantillas propias del iniciador CGI conservan PHP 8.4 en
futuras regeneraciones de ISPConfig.

No se renovaron certificados ni se regeneraron sitios como efecto lateral.
La recarga del certificado de kumbia.app conserva el controlador central.
El programador nativo de ISPConfig esta restaurado. El terminador SQL
retirado y la coordinacion del antipanico mantienen su politica.

Se aplico de forma acotada la correccion de permisos de registros prevista
por el actualizador: 240 cambios de metadatos en rutas contrastadas con
DocumentRoot y ErrorLog, sin seguir enlaces ni cambiar contenidos. Los
propietarios se conservaron y los grupos se resolvieron por sitio.

## Incidencia durante la actualizacion

Una llamada interna del primer intento reinicio PHP-FPM durante
aproximadamente un segundo, pese a desactivar la reconfiguracion de
servicios. Se comunico al operador, se localizo la llamada y se interceptaron
las mutaciones del instalador. La rama se probo con servicios activos
simulados antes del intento definitivo. No se reiniciaron el VPS ni MariaDB.
El intento definitivo conserva los procesos principales; las activaciones
posteriores se realizan mediante el controlador central.

Se comprobaron las migraciones SQL y el instalador completo en aislamiento.
Los intentos fallidos recuperaron el manejador anterior antes de continuar.
La prueba HTTP final valida el interprete real y el usuario ejecutor; no se
considera suficiente la version mostrada por la consola.

## Evidencias y copias

Evidencias privadas en /root/codex-vigilante/ispconfig-php84-2026-10-22:
upgrade-verification.json, php84-verification.json, final-verification.json,
configuration-preservation-verification.json y closure-verification.json.
Se conservan las copias de texto y SQL y los metadatos necesarios para
recuperacion. Se retiran las distribuciones binarias temporales y el
laboratorio de trabajo; no se incluyen en las copias permanentes.

Los scripts y las instrucciones operativas se mantienen en el repositorio
local y en GitHub. Esta actualizacion cierra el mantenimiento previo de PHP;
no completa los tres bloques pendientes del vigilante: SQL automatico seguro,
reparaciones acotadas y aceptacion conjunta.
