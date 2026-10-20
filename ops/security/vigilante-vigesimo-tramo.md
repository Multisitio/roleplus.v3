# Tramo 20: correspondencias y retencion de copias en ISPConfig

Fecha: 20 de octubre de 2026.

Se restablece la asociacion de las bases de Controlate y Delixio con sus webs
actuales. Se contrastaron los nombres utilizados en la configuracion de cada
aplicacion, ServerName y DocumentRoot de Apache y el registro actual del panel.
No se dedujo el sitio a partir de un antiguo numero webN.

El proceso instalado de ISPConfig une web_database con web_domain mediante
parent_domain_id: un valor cero excluye la base de las copias programadas.
Esto explica que las dos copias localizadas fueran del 12 de abril. Las
asociaciones reparadas vuelven a incluir ambas bases en la seleccion de copias.

Las siete bases con web identificada conservan su frecuencia semanal y pasan
de una a siete copias retenidas. Las otras dos bases sin correspondencia
acreditada quedan intactas y pendientes de investigar. No se borraron ni
movieron volcados anteriores ni se cambio informacion de las aplicaciones.

## Aplicacion cautelosa

`vigilante-backup-policy.php` es una herramienta manual de administrador,
guardada en el VPS dentro de las evidencias privadas, sin acceso de la IA.
Solo permite cambiar asociacion y ampliar retencion hasta siete, conservando
la frecuencia semanal. El plan revisado se prepara localmente a partir de
metadatos remotos, sin claves ni contenidos de usuarios.

La herramienta compara todos los valores anteriores dentro de una transaccion
InnoDB, bloquea solo las filas de configuracion afectadas y limita la espera
a tres segundos. Comprueba dominio, raiz y huellas de las evidencias antes de
escribir. Guarda un resultado privado reservado de forma exclusiva; no repite
una aplicacion con resultado previo. Una caida entre COMMIT y confirmacion
exige contrastar el estado real con el plan, sin aplicar otra vez a ciegas.

Se actualizan unicamente esos campos del panel. El proceso de copias instalado
los consulta directamente, por lo que no se necesitan eventos de regeneracion
de Apache ni cambios de usuarios o permisos SQL. Se conservan las demas
columnas, configuraciones, servidores virtuales y automatismos.

## Limite detectado antes de aumentar la frecuencia

Las siete bases incluyen tablas MyISAM; ROLEplus y MTGsearch mezclan motores.
El ejecutor instalado usa mysqldump con sus bloqueos predeterminados. No se
activa copia diaria ni se ejecuta una copia manual en horario de uso sin
resolver la ventana y los limites de bloqueo. `--single-transaction` por si
solo no proporciona una copia consistente de tablas MyISAM.

No se han generado nuevas copias en este tramo. La seleccion del proceso
programado incluye ahora las siete bases, pero su siguiente generacion sigue
pendiente de comprobar. La ampliacion de retencion consume espacio al acumular
copias: los ocho volcados actuales suman aproximadamente 126 MiB comprimidos;
no se presupone que su tamano permanezca constante.

## Comprobaciones y cierre

Siete comprobaciones de validacion de planes en local y en el VPS; rechazo de
campos adicionales, cambios de frecuencia, retencion invalida, correspondencias
sin evidencia, duplicados y nombres no permitidos. Prueba real de discrepancia
en el ultimo registro del plan: transaccion abortada y configuracion completa
sin cambios. Aplicacion real verificada por columnas, seleccion del proceso
programado y huellas de servidores virtuales y antipánico, sin reinicios.

La tarea global de recuperacion sigue pendiente. El estado y los criterios de
fin de implementacion quedan en `vigilante-estado-implementacion.md`.
Evidencias privadas: `/root/codex-vigilante/tramo-20-2026-10-20` y su copia
local ignorada por Git. No se habilitan reparaciones automaticas.
