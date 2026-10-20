# Tramo 23: SQL independiente y limites de bloqueo comprobados

Fecha: 20 de octubre de 2026.

## Alcance

Se catalogan las dos bases activas sin dominio como bases independientes.
No se inventa una correspondencia en ISPConfig ni se declara que esten en
desuso. La comprobacion explicita de TABLE_TYPE y SHOW FULL TABLES confirma
una tabla MyISAM y otra InnoDB. El motor sin informar de la primera lectura
no acreditaba una vista; se registra la regla para evitar esa deduccion.

Una admite copia SQL. La otra devuelve el error 1932: sus archivos existen,
pero no tiene entrada en el diccionario interno de InnoDB. SHOW CREATE TABLE
tambien falla y la consulta al diccionario no devuelve esa tabla. Se conservan
los archivos originales intactos y sus huellas en el informe privado. Los
archivos candidatos de R2-D2 no coinciden con los del VPS y no se toman como
fuente de verdad ni se sustituyen. No se recrea ni se vacia la tabla.

La base ilegible queda como incidencia de recuperacion de prioridad alta.
El catalogo la mantiene expresamente bloqueada: no se repite semanalmente
una operacion conocida imposible ni se declara completa la cobertura SQL.
El informe separa el exito de la base legible y coverage_complete=false.

Las siete bases asociadas a webs conservan su politica nativa de ISPConfig.
No se cambia el motor de ninguna tabla, permisos de usuarios ni datos de las
aplicaciones. Las copias grandes y sus restauraciones siguen pendientes.

## Consistencia y tiempo de bloqueo

El parametro --lock-tables de mariadb-dump utiliza READ LOCAL en MyISAM y
permite inserciones concurrentes, segun el
[manual oficial de MariaDB](https://mariadb.com/docs/server/clients-and-utilities/backup-restore-and-import-clients/mariadb-dump).
No se usa ese parametro como prueba de una inmovilizacion estricta de todas
las tablas de una base. Tampoco se aplica un bloqueo global del servidor.

`vigilante-sql-backup.py` obtiene READ de las tablas de la base seleccionada
en una conexion propia y de solo lectura, con autocommit desactivado. Exporta
desde otra conexion con single-transaction y skip-lock-tables. Los recuentos
de filas se capturan antes de liberar READ y sirven para contrastar la
restauracion. No se promete consistencia entre bases distintas ni frente a
DDL concurrente no coordinado. Las bases de este tramo tienen una tabla cada
una; las reparaciones y migraciones de esquema siguen deshabilitadas.

El lector tiene un plazo nominal independiente de 1.5 segundos, mas el margen
de terminacion del proceso, y limite de 0.5 segundos por sentencia. El volcado
usa el plazo restante y falla si el lector caduca. La compresion empieza solo
despues de cerrar esas conexiones y sus bloqueos. El plazo lo aplica timeout,
no un hilo del controlador: permanece activo si el controlador se suspende.

`vigilante-sql-child.py` enlaza la vida de cada cliente con su supervisor mediante
PR_SET_PDEATHSIG. Si muere el controlador o se cancela timeout, sus clientes
tambien terminan. Solo se senalan procesos propios; no hay KILL SQL ni acceso
para la IA al ejecutor que termina consultas de otras sesiones.

## Ensayo real fuera de produccion

`vigilante-sql-check.service` levanta MariaDB como usuario dinamico, sin red,
sin socket ni archivos de produccion y sin perfiles autenticados. Sus tablas
de ensayo, MyISAM e InnoDB, viven en /run y desaparecen al terminar la unidad.
Los informes persistentes contienen metadatos y no datos personales.

Se comprobo una copia que espera un bloqueo, una salida deliberadamente
atascada, escritura posterior a la cancelacion, muerte del controlador,
suspension del controlador y restauracion de la base de motores mixtos.
En el ensayo ampliado, la espera fallida termino en 0.48 segundos, la escritura
se reanudo en 0.005 segundos al cancelar, y siguio recuperandose al matar o
suspender el controlador. Son resultados de ese ensayo, no una garantia de
latencia maxima del sistema operativo bajo cualquier carga.

## Ejecucion independiente

`vigilante-independent-sql.service` consume un catalogo privado contrastado
con los identificadores y parent_domain_id=0 del panel. Cambiar esa asociacion
hace que rechace el trabajo antes de copiar. No se crean registros web_backup
con un dominio ficticio o cero; los resultados quedan en el catalogo privado
del vigilante, vinculados a los identificadores reales de las bases.

La politica activada es semanal, domingos a las 04:30 de Madrid, con siete
versiones propias por base accesible. El temporizador se activo despues de generar
y restaurar la primera copia real; proxima ejecucion el 25 de octubre de 2026.
La restauracion aislada recupero una tabla y sus 67 filas, coincidentes con
el recuento bajo bloqueo. El volcado tardo 0.36 segundos. Repetir el servicio
antes del vencimiento no genero otra copia. Se verificaron los servicios
activos, las politicas SQL de ISPConfig sin cambios y las huellas originales
de la tabla ilegible intactas. La cobertura global sigue siendo incompleta.
El servicio exige mas de 20 GiB libres,
limita los archivos a 32 MiB y acepta hasta 16 MiB de SQL por base. Los limites
corresponden a estas bases pequenas, no autorizan exportar grandes bases
con el mismo procedimiento sin ensayos previos.

Se conservan solo SQL de texto UTF-8, comprimido y verificado, con modo 0600
en un directorio privado. Los temporales propios se retiran tras verificar
su sustituto. La retencion comprueba nombre y SHA-256 antes de retirar una
copia propia. Una discrepancia requiere conciliacion y no genera otra copia
a ciegas. No se eliminan volcados antiguos del panel ni archivos de usuarios.

Las fuentes de mantenimiento se publican en GitHub antes de ejecutar la copia
de produccion. El preparador de despliegues de aplicacion sigue bloqueado por
los cambios pendientes de R2-D2; estos no forman parte de este tramo.

Evidencias privadas: /root/codex-vigilante/tramo-23-2026-10-20 y metadatos
replicados en la carpeta local ignorada por Git. La implementacion global
conserva los bloques pendientes de su lista de cierre; las alarmas de estas
copias deben comprobarse en la aceptacion conjunta.
