# Tramo 24: restauracion del catalogo SQL y retirada de temporales

Fecha: 20 de octubre de 2026.

## Alcance y fuentes

Se seleccionan los siete volcados existentes de las bases asociadas a webs.
Los archivos originales del VPS son la fuente de estos ensayos; no se mezclan
con bases locales ni se escriben resultados en las bases de las aplicaciones.
Los nombres historicos webN de los directorios no se usan para identificar
una web ni para reasignar bases en ISPConfig.

El plan privado fija ruta, nombre de base, SHA-256 comprimido, SHA-256 del SQL
y longitud descomprimida previamente comprobados. El coordinador rechaza
enlaces, rutas no permitidas, origen cambiado, seleccion duplicada o resultados
anteriores sin conservar. Las fuentes de mantenimiento se publican en GitHub
antes de instalarlas; los cambios ajenos de R2-D2 no se despliegan.

## Aislamiento y limites

Se reutiliza vigilante-restore-drill.service: usuario dinamico, capacidades
vacias, sin red, sin archivos ni socket de MariaDB en uso, sin las webs y sin
perfiles autenticados. Cada base se importa en una instancia desechable
independiente, con su nombre real para resolver correctamente las vistas.
Los contenidos SQL y mensajes de error permanecen privados en el VPS.

Solo hay una restauracion cada vez. Limites: CPU del 25%, memoria de 768 MiB,
prioridad baja, doce minutos por unidad y ocho minutos para una importacion.
Se mantienen los limites de 1 GiB de SQL descomprimido y 2 GiB por archivo.
Antes de cada ensayo se exige reserva de disco de 20 GiB y memoria disponible
de 1.5 GiB. No se toman bloqueos ni se lanzan consultas sobre bases en uso.
El coordinador manual no es una herramienta concedida a la IA.
La unidad queda inactiva y sin una entrada permanente seleccionada; el
coordinador monta solo el archivo revisado durante cada ensayo manual.

Cada ensayo contrasta el SQL con la huella y longitud del plan, exige que la
importacion y mariadb-check terminen correctamente, cuenta las filas de cada
tabla y comprueba las vistas con SELECT LIMIT 0. Los recuentos pertenecen a
ese volcado: no acreditan igualdad con el contenido actual de produccion.

La comprobacion de vistas detecto el error 1449 en el primer ensayo ampliado
de ROLEplus: faltaba su cuenta definidora en el servidor desechable. Se
conserva el resultado fallido. El ejecutor reproduce esas identidades solo
en la instancia aislada, con ACCOUNT LOCK y SELECT limitado a la base
restaurada, sin copiar claves ni abrir conexiones. No se modifica el SQL de
origen ni se atribuye ese fallo del ensayo a la base de produccion.

## Temporales y espacio

El ensayo inicial de ROLEplus habia conservado 488 MiB de SQL y archivos
temporales de MariaDB. Se contrastaron su informe, huella del SQL, rutas y
estado detenido antes de retirar exclusivamente ese espacio generado.
Sus informes de texto se conservan; los originales de las webs y sus
volcados SQL no se eliminan.

El ejecutor crea ahora work con un marcador propio y propietario comprobado.
ExecStopPost lo retira como usuario sin privilegios, tanto al terminar como
al fallar. Rechaza directorios ajenos o sin marcador; no sigue enlaces al
borrar. Una prueba real de rechazo por espacio previo verifico la limpieza
posterior sin arrancar MariaDB. Se conservan informes privados y se elimina
solo la duplicacion de entrada propia, tras comprobar nuevamente su huella.
Una perdida brusca del VPS puede interrumpir esa limpieza: un espacio previo
bloquea el siguiente ensayo y exige revision, sin acumular otra restauracion.

Los archivos binarios de MariaDB solo existen durante la importacion aislada;
no son copias conservadas. Las copias de origen siguen siendo SQL textual
comprimido. No se cambian frecuencias, retenciones ni configuraciones del panel.

## Limites de lo demostrado

Las siete restauraciones seleccionadas terminaron correctamente: 197 tablas,
dos vistas y 992 600 filas. ROLEplus recupero 80 tablas, dos vistas y 684 109
filas en 130 segundos; MTGsearch, 32 tablas y 163 624 filas en 238 segundos.
Son tiempos completos de cada ensayo aislado, no latencias de las webs.

Las cinco pruebas del ejecutor pasan en Windows y Linux; la limpieza efectiva
se comprueba tambien mediante un fallo real de la unidad. Se verifican las
huellas de los siete archivos originales y de los originales de la tabla
ilegible, la ausencia de espacios temporales y los servicios activos.
La cola y el diario registran el avance sin cerrar la recuperacion pendiente.

Restaurar un archivo antiguo acredita que ese archivo puede recuperarse.
No lo convierte en una copia reciente ni demuestra la consistencia temporal
entre tablas al generarlo. Controlate y Delixio conservan archivos de abril;
la ejecucion acotada de copias nuevas de las siete bases sigue pendiente.
La tabla independiente con error 1932 sigue registrada y no se toca.

La implementacion permanece abierta segun vigilante-estado-implementacion.md.
Evidencias privadas: /root/codex-vigilante/tramo-24-2026-10-20 y metadatos
replicados en la carpeta local ignorada por Git.
