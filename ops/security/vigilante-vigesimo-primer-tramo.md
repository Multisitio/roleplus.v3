# Tramo 21: copias web y desbloqueo del cron de ISPConfig

Fecha: 20 de octubre de 2026.

## Copias activadas y limites de espacio

Las diez webs del catalogo distintas de ROLEplus tienen ahora copia semanal
de archivos, formato tar_gzip y dos copias retenidas en ISPConfig. Se conservan
las exclusiones existentes y las exclusiones predeterminadas del ejecutor,
sin retirar imagenes de usuarios ni elegir archivos por patrones de nombre.
El proceso programado sigue siendo el de ISPConfig; no hay otro temporizador.

La herramienta manual `vigilante-web-backups.php` sigue el procedimiento del
tramo anterior: plan privado revisado, comparacion de configuracion y huellas
de Apache, transaccion de configuracion y registro privado. Solo modifica
frecuencia, retencion y formato de las webs seleccionadas. No solicita eventos
de regeneracion de servidores virtuales. Una discrepancia en el ultimo sitio
del plan se probo contra el panel real y aborto sin cambiar ninguna fila.

Las once raices suman aproximadamente 52 GiB. ROLEplus ocupa unos 47 GiB,
de los que unos 43 GiB corresponden a imagenes. Con unos 94 GiB disponibles,
no es seguro activar dos copias completas de todas las webs. Las diez
seleccionadas suman aproximadamente 4.8 GiB; el plan exige espacio para tres
veces ese tamano y otros 20 GiB libres, sin asumir ahorro de compresion.
Ese control es previo a la activacion, no una cuota ni una proteccion continua
del disco: falta incorporar el crecimiento y la ejecucion acotada a la politica
de operacion. Las copias nativas eliminan antiguas despues de generar la nueva.

ROLEplus conserva desactivada su copia web mientras se decide una estrategia
que conserve originales y evite duplicarlos de forma insostenible. No se han
excluido directorios de vistas previas o RSS: sus nombres no acreditan que su
contenido pueda reconstruirse. Tampoco se han borrado temporales o miniaturas.
Las dependencias compartidas core y vendor, fuera de las raices, requieren
su propia cobertura y siguen pendientes de recuperacion integral.

## Cron bloqueado

La primera prueba nativa rechazo correctamente entrar porque el cron estaba
ocupado. Se encontro el cron de ISPConfig esperando desde hacia mas de diez
horas a AWStats, que procesaba ROLEplus. El proceso estaba en espera de sondeo,
con consultas DNS activadas. Eso no prueba que DNS fuera su unica causa,
pero si acredita el bloqueo y que retrasaba las siguientes tareas del panel.

`vigilante-awstats-recovery.py` conserva la configuracion base y el archivo
mensual publicado en una carpeta privada. Cambia exclusivamente DNSLookup
de 1 a 0 y envia SIGTERM al proceso concreto de AWStats, validando argumentos,
identidad de inicio y descriptor de PID para evitar senalar un PID reutilizado.
No termina el cron ni reinicia servicios web. El proceso original y su cadena
terminaron; una nueva ejecucion del cron completo las tareas atrasadas, incluida
la rutina de copias. Se retiro solo la marca obsoleta running del trabajo
AWStats, contrastando el PID terminado y su fecha exacta; se conservo su proxima
fecha de ejecucion. No se borraron estadisticas ni registros de acceso.

Las estadisticas mantienen IP en vez de resolver nombres DNS. Su regeneracion
completa tras la interrupcion queda pendiente de comprobar. La correccion de
DNS tampoco impone un plazo maximo a todos los trabajos de red del panel:
esa carencia queda registrada para la coordinacion de automatismos heredados.

## Prueba real y recuperacion de archivos

`vigilante-native-web-backup-cron.php` llama al ejecutor instalado de ISPConfig
para una sola web seleccionada. Conserva cron.php en su nombre porque el panel
comprueba tambien la linea del proceso al reconocer su bloqueo. Espera hasta
90 segundos sin retirar bloqueos ajenos ni procesos del panel. En caso de
terminacion abrupta hay que contrastar el PID antes de retirar el bloqueo.

La prueba de CSS se ejecuto bajo una unidad temporal con CPU del 25%, memoria
de 256 MiB, prioridad baja y plazo de 120 segundos. Estos limites corresponden
a la prueba manual; no se atribuyen al cron nativo. No se ejecuto PHP de las
aplicaciones como administrador: solo el ejecutor de administracion del panel.

ISPConfig genero y registro una copia de aproximadamente 1.6 MiB. Se verifico
la lectura completa de gzip y se recuperaron tres archivos regulares, mediante
lectura explicita de sus entradas, en una carpeta privada. Coinciden contenido
SHA-256 y metadatos guardados de propietario, grupo y permisos con la fuente,
que no cambio durante la prueba. No se extrajo un archivo tar de usuarios sobre
una web ni se siguieron enlaces de sus entradas. Las muestras recuperadas se
mantienen con permisos privados; los metadatos originales se contrastan en el
archivo tar. Esto valida esa muestra, no el funcionamiento de una web completa.

Seis comprobaciones del plan en local y Linux, prueba real de discrepancia y
tres comprobaciones del cambio limitado de DNS. Configuracion de Apache y
antipánico conservados; servicios activos y acciones de IA deshabilitadas.
Evidencias privadas bajo `/root/codex-vigilante/tramo-21-2026-10-20` y su copia
local ignorada por Git. No se descargan los secretos ni los datos recuperados
a documentos publicos o al contexto de la IA.

Siguen pendientes ROLEplus, dependencias compartidas, copias consistentes de
MyISAM con limites compatibles con el servicio y restauraciones completas.
