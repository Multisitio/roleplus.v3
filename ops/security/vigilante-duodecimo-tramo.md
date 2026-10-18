# Duodecimo tramo: compatibilidad de las miniaturas historicas

Se toma la incidencia prioritaria de errores 500 en /img de ROLEplus. La
copia local del codigo es la fuente de verdad; los originales y sus referencias
pertenecen a produccion y no se sustituyen por copias locales.

## Diagnostico

La configuracion activa situa ROLEplus en web6 y declara su ErrorLog bajo el
dominio. Una primera lectura uso por error una carpeta web17, de otro sitio;
esos resultados se descartaron y no fundamentan esta reparacion. Queda una
regla persistente que exige resolver las rutas del servidor virtual antes de
atribuir registros a una web.

El registro correcto contiene 573 respuestas 500 de imagenes el 17 de octubre
y 400 en la muestra del 18 de octubre. Dos enlaces concentran 308 y 34 errores:
sus originales son respectivamente SVG y AVIF. No son por ello archivos
corruptos. El generador historico pasaba SVG a getimagesize/GD y no incluia AVIF
en sus operaciones de lectura y escritura. El servidor dispone de ambos
decodificador y codificador AVIF de GD.

Otros errores corresponden a imagenes aun pendientes de diagnostico o
recuperacion. Se han encontrado avisos de PNG truncado y archivos con extension
PNG cuyo contenido se identifica como texto. No se eliminan ni se sobrescriben.

## Cambio acotado

Thumbnail conserva las rutas historicas y el mecanismo de redireccion del
controlador. Si el original tiene extension SVG y MIME image/svg+xml, devuelve
su ruta original vectorial, como ya hace el sistema de medios con estos
formatos. No rasteriza ni vuelve a escribir ese contenido.

Para AVIF, el generador usa imagecreatefromavif e imageavif, manteniendo el
formato, la proporcion, el presupuesto de calidad existente y la publicacion
atomica de la miniatura. Los formatos anteriores conservan sus operaciones.
No se modifican los filtros, el nivel de registro, el controlador de imagenes
ni las configuraciones de los entornos.

## Verificacion y publicacion

La prueba aislada reproduce el fallo SVG anterior. Con el cambio supera 23
comprobaciones sobre SVG, AVIF, PNG, JPEG, GIF y WebP: dimensiones y formato,
integridad del original, reutilizacion de la miniatura y ausencia de temporales.
Se ejecuta en el PC con los fuentes locales y en el VPS como el usuario web6,
sin sesiones, claves ni bases de datos. Los archivos de prueba se eliminan al
terminar y no se crean en las carpetas de contenido de los usuarios.

La publicacion comprueba la huella anterior del archivo, guarda una copia
privada, comparte el bloqueo del antipanico y conserva propietario, grupo y
permisos. Reemplaza solamente thumbnail.php. Su recuperacion rechaza
sobrescribir un cambio posterior de otro escritor.

La primera comprobacion inmediata seguia leyendo el codigo anterior y el
procedimiento restauro el archivo. Se comprobo directamente en FPM que
OPcache valida cada dos segundos y protege archivos modificados durante dos
segundos. Tras respetar esa ventana, la publicacion supera las mismas peticiones:
500 pasa a 200 image/svg+xml y 200 image/avif. No se vacia la cache global ni
se reinicia FPM. Las huellas de ambos originales se conservan.

Las portadas de ROLEplus, MTGsearch y kumbia.app responden HTTP 200 con TLS
normal. La correccion queda en el diario y vinculada a la incidencia original.
La incidencia general permanece pendiente por los casos restantes: el
recuento historico no se presenta como una medicion posterior del trafico
ni como prueba de que todos los errores de imagen hayan desaparecido.

Las evidencias concretas quedan privadas en scratch/vigilante/2026-10-18/tramo-12
y /root/codex-vigilante/tramo-12. Esta actuacion administrativa no concede
permisos de escritura a la IA del VPS ni habilita reparaciones automaticas.

Referencias de GD: [lectura AVIF](https://www.php.net/manual/es/function.imagecreatefromavif.php)
y [escritura AVIF](https://www.php.net/manual/en/function.imageavif.php).
