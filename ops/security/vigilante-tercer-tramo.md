# Vigilante: tercer tramo

Fecha: 18 de octubre de 2026. Preparacion de Git y de los paquetes revisables.

## Fuente de verdad y alcance

La copia local de cada proyecto conserva la autoridad indicada por sus
instrucciones. Git conserva el codigo aceptado; no se convierte automaticamente
en fuente de los datos, configuraciones ni archivos generados de produccion.
No se han incorporado, descartado ni publicado modificaciones locales ajenas.

Se han localizado nueve repositorios propios entre las once webs del catalogo.
Siete contienen cambios pendientes. Las otras dos webs requieren resolver su
copia local o su repositorio propio: un repositorio heredado del directorio
padre no acredita que la web este versionada. Los detalles quedan privados.

La comparacion de produccion es una muestra de tres archivos por repositorio,
no una certificacion completa. El directorio publico y el privado del VPS
son hermanos; el catalogo de despliegue debe expresar esas correspondencias,
sin copiar literalmente la estructura de carpetas local sobre DocumentRoot.
No se atribuye un commit global a una web por coincidir solo algunos archivos.
En los 27 archivos comparados, 26 coinciden con la copia local y 17 con el
commit registrado. La diferencia restante y las divergencias respecto a Git
quedan identificadas por archivo en el informe privado, sin sobrescribirlas.

## Preparacion implantada

`vigilante-release-plan.py` prepara un paquete nuevo desde el identificador
completo de un commit y una lista explicita de archivos. Guarda el objeto Git,
SHA-256, longitud y si el archivo local coincide con ese commit. Extrae el
contenido versionado, aunque existan cambios sin confirmar, y avisa de esa
diferencia en el manifiesto. No modifica el repositorio ni escribe en las webs.

Rechaza repositorios heredados, referencias moviles, rutas fuera del alcance,
directorios, duplicados, enlaces y submodulos. Excluye configuracion, claves,
entrada publica, reglas de Apache, volcados, archivos de usuarios, temporales
y estilos generados de plantillas. Esos archivos necesitan procedimientos
propios; no deben introducirse por el paquete generico. No se sobrescribe un
paquete existente. Los paquetes deben conservarse en almacenamiento privado.

Estas exclusiones no sustituyen revisar el contenido de cada archivo: un
secreto mal ubicado en codigo tambien debe detener su publicacion. El
manifiesto declara obligatoria la revision y mantiene deshabilitado el
despliegue. Este programa no es un ejecutor de instrucciones del modelo.

## Condiciones para el ejecutor del siguiente tramo

Antes de habilitar escrituras, implementar y comprobar estas condiciones:

1. Catalogo privado y revisado de correspondencias por sitio, extensible a
   las webs pendientes, sin aceptar destinos elegidos por el modelo.
2. Revision del contenido y del cambio concreto, huellas esperadas de todos
   los destinos y comprobaciones de sintaxis y servicio propias del sitio.
3. Bloqueo de actuaciones coordinado con el control central y pausa acotada
   del antipánico durante la transaccion; no crear otro reiniciador.
4. Copia privada previa con contenido, propietario, grupo y permisos. Rechazar
   enlaces y cambios de huella desde la revision; preparar y reemplazar cada
   archivo conservando sus metadatos, siguiendo el instalador existente.
5. Diario de cada archivo aplicado y pruebas de salud. Ante fallo, restaurar
   solo archivos que aun coincidan con lo publicado por esa transaccion;
   una modificacion posterior exige intervencion, no sobrescritura.
6. Ensayo de fallo y recuperacion fuera de las webs, antes de cualquier
   publicacion real. Un reemplazo atomico de archivo no hace atomico un
   despliegue de varios archivos ni garantiza revertir cambios de datos.

Las copias de seguridad y su recuperacion deben verificarse antes de permitir
reparaciones autonomas. No se da por terminada esa comprobacion en este tramo.

## Verificacion y limites

Siete pruebas aisladas en Windows y en el VPS: conservacion de cambios locales,
extraccion del commit, proteccion de rutas y datos, rechazo del repositorio
padre, referencias moviles, sobrescritura, seleccion invalida y enlaces Git.
Los repositorios de prueba son temporales, sin credenciales ni servicios reales.
Las fuentes del preparador y las pruebas tienen la misma huella en ambos
entornos; su copia remota esta bajo root, fuera de las webs y sin nuevos permisos
para la IA. No se han solicitado reinicios ni publicado cambios de aplicacion.

El certificado pendiente conserva su prioridad en la cola; no se declara
corregido. Codex CLI, el ejecutor de despliegue, WhatsApp y el nuevo resumen
diario siguen pendientes. No se han habilitado acciones autonomas.

Evidencias privadas: scratch/vigilante/2026-10-18/tramo-3 y su replica bajo
/root/codex-vigilante/tramo-3.
