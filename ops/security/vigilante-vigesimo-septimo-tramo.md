# Tramo 27: recuperacion completa de textos y limites reales del SQL grande

Fecha: 20 de octubre de 2026.

## Ensayo de las bases grandes

Se restauran los volcados historicos ya contrastados de ROLEplus y MTGsearch,
uno cada vez, en MariaDB desechable. La unidad tiene CPU del 75% de un nucleo,
memoria de 768 MiB, sin red, sin las webs y sin datos ni socket SQL de
produccion. El cambio temporal de CPU se retira al acabar. No se cambia
ningun limite de la herramienta ni de las copias de produccion.

Ambas copias se rechazan con la proteccion actual de 1.5 segundos. La
exportacion completa de laboratorio, sin escritores concurrentes y sin ese
limite de sentencia, tarda 8.86 segundos para ROLEplus y 29.69 para MTGsearch.
Genera respectivamente 190 013 376 y 659 942 508 bytes de SQL, comprobando
el cierre del volcado. Ese SQL solo existe temporalmente en el laboratorio.

Esas cifras no son una medicion de pausa real en produccion ni un compromiso
de tiempo bajo otra carga. Acreditan que no basta aplicar sin cambios el
procedimiento pequeno: ademas del plazo, sus limites de 16 MiB de SQL y
32 MiB por archivo excluyen estos volumenes. No se suben esos limites ni
se automatizan esperas largas por deduccion de este ensayo.

La estrategia consistente y recurrente de las bases grandes sigue pendiente:
MyISAM requiere proteger las escrituras durante la captura. single-transaction
no lo soluciona por si solo; el volumen LVM no tiene espacio sin asignar para
instantaneas convencionales. Una ventana de escritura restringida o un cambio
estructural de almacenamiento/motores deben prepararse y evaluarse antes de
aplicarlos. Se mantiene el servicio y su programacion de copias existente.

## Recuperacion de todos los textos seleccionados

Se reutiliza verify de vigilante-text-backups.py y el mismo catalogo privado.
Se preparan entradas de solo lectura con huellas de archivo y manifiesto;
vigilante-text-restore.service las restaura como usuario dinamico sin
privilegios. No tiene red ni acceso a las webs, datos SQL o perfiles
autenticados. No ejecuta fuentes recuperados; el espacio temporal tiene
ejecucion prohibida.

El ensayo incluye once webs y core/vendor: trece raices y 36 483 archivos,
con 165 340 088 bytes de texto seleccionados. Cada archivo se materializa
fuera de produccion, se relee y compara por SHA-256 y se comprueba su modo.
El propietario y grupo esperados se contrastan entre archivo y manifiesto;
no se aplica chown como usuario sin privilegios ni se atribuye a este ensayo
la recuperacion de directorios o archivos binarios que no incluye la copia.

Solo se crean archivos regulares de rutas admitidas; no se usa extractall,
ni se siguen enlaces o rutas con .. para restaurar. Las comprobaciones de
contenido textual, gzip, inventario, propietario, grupo y modo reutilizan
las del generador de copias. La unidad tiene CPU del 25%, memoria de 512 MiB
y plazo de diez minutos. RuntimeDirectory retira los archivos recuperados
al terminar; se retiran tambien las duplicaciones privadas de entrada tras
verificar el informe. Permanecen solo los originales de copia y metadatos.

La comprobacion cubre los textos seleccionados en esas copias, no una imagen
completa de cada web ni la ejecucion de sus configuraciones recuperadas. Las
claves no se imprimen, descargan al repositorio ni incorporan al modelo.

## Criterios de cierre

El ensayo de textos termino correctamente en 121 segundos como UID 65118:
trece raices y todos los 36 483 archivos restaurados. Se comprobaron la
retirada de temporales y entradas duplicadas, la politica SQL del panel sin
cambios y los mismos procesos activos de Apache, MariaDB y PHP-FPM antes y
despues, sin reinicios. Solo quedan informes privados y las copias originales.

La lista se concreta en cinco pasos pendientes en
vigilante-estado-implementacion.md, manteniendo el alcance acordado: copias
SQL seguras y recurrentes, tabla independiente 1932, automatismos heredados,
ejecutor de reparaciones coordinado y aceptacion conjunta. La recuperacion
de todos los textos seleccionados deja de figurar como tarea pendiente
cuando se verifica el resultado de este ensayo. Los errores operativos
posteriores no agregan pasos indefinidos de puesta en marcha.

Evidencias privadas: /root/codex-vigilante/tramo-27-2026-10-20 y metadatos
replicados en la carpeta local ignorada. La implementacion no esta finalizada.
