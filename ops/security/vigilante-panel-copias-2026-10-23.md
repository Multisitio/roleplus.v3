# Copias textuales del panel ISPConfig

Fuente de codigo: R2-D2; datos del panel y configuracion efectiva: VPS.

## Hallazgo y cambio

El trabajo nativo 500-sysbackup.inc.php guardaba /etc y /usr/local/ispconfig
completos, con imagenes binarias. La captura de las 03:00 del 23 de octubre
ocupa 8.1 MiB aproximadamente, incluido SQL. Sus archivos se conservan;
no se borran originales ni versiones heredadas para implantar el cambio.

El mismo trabajo conserva su horario diario de las 03:00 y delega en
vigilante-panel-backups.service. No hay un segundo temporizador. Mantiene
tres generaciones propias en /var/backup/vigilante-panel-text. La politica
sysbackup_copies=3 y el monitor sysbackup del panel siguen vigentes.
Un cambio del responsable, retencion o uso de montaje requiere revision.

## Consistencia y limites

Las 79 tablas de dbispconfig son InnoDB. El volcado usa una instantanea
transaccional, sin LOCK TABLES ni pausa de escrituras. Inventario y esquema
se contrastan antes y despues; un motor diferente o vista aborta la captura.
El cliente tiene un plazo independiente de doce segundos y se exige cierre
SQL. El servicio entero tiene un limite de 180 segundos. No reinicia servicios.

Reutiliza el comprobador textual existente. En /etc y /usr/local/ispconfig
incluye tambien configuraciones sin extension; excluye binarios por su
contenido y directorios auxiliares. No sigue enlaces: almacena sus destinos
y los permisos/propietarios de directorios en un JSON dentro del archivo.
Cada archivo regular conserva metadatos y huella. No se promete una imagen
completa del sistema ni la recuperacion de ejecutables o imagenes excluidos.

Copias privadas 0600, directorios 0700, reserva minima de 20 GiB mas presupuesto
de la captura, sin acceso de red del ejecutor. Ningun secreto va a GitHub en
texto claro. Una captura incompleta queda pendiente de revision, sin borrar
copias buenas ni reintentar ciegamente. La retencion solo elimina versiones
propias contrastadas, nunca los archivos nativos antiguos.

## Verificacion y replicas

Las pruebas aisladas cubren seleccion de texto, enlaces, publicacion,
duplicados, fallos, retencion, cambios de motor/propietario y reversion de
la migracion. La validacion real de la primera captura y su restauracion
se documentaran tras ejecutarse. El exportador y receptor admiten exactamente
sql:dbispconfig, text:system-etc y text:system-ispconfig; no rutas arbitrarias.
El detector incorpora ausencia, fallo, antiguedad superior a 27 horas y
cambio del propietario mediante el circuito de avisos ya existente.

La copia cifrada en GitHub sigue pendiente junto con la cobertura triple y
las primeras capturas grandes del domingo. Este paso no cierra el vigilante.
Al actualizar ISPConfig hay que revalidar tanto 500-backup.inc.php como
500-sysbackup.inc.php antes de liberar cron.
