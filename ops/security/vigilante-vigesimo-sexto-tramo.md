# Tramo 26: SQL textual de Rola con valores comprobados

Fecha: 20 de octubre de 2026.

## Solucion aplicada

Los dos U+0003 del volcado de Rola se representan mediante CHAR(3 USING
utf8mb4), unido a los segmentos originales con CONCAT. El archivo SQL
conservado no contiene esos controles literales y sigue pasando el mismo
clasificador de texto. No se borran caracteres de los datos ni se relaja
el clasificador. La fuente de los datos sigue siendo la base del VPS; solo
se transforma la representacion de una copia.

vigilante-sql-text.py admite esa transformacion exclusivamente en valores
literales de INSERT generados por mariadb-dump, con el modo SQL conocido.
Conserva los escapes y las comillas; rechaza controles fuera de ese contexto,
literales sin cierre, prefijos o escapes no contemplados. Otros caracteres
de control siguen siendo rechazados. No es un normalizador general de SQL.

Referencias oficiales de MariaDB:
[CHAR](https://mariadb.com/docs/server/reference/sql-functions/string-functions/char-function)
y [CONCAT](https://mariadb.com/docs/server/reference/sql-functions/string-functions/concat).

## Verificacion de fidelidad

Cuatro pruebas del transformador pasan en Windows y Linux. Una prueba real
en la unidad aislada incluye controles repetidos y en los extremos, comillas
escapadas y duplicadas, texto ordinario y NULL. Importa la version original
y la transformada en dos bases desechables. Ambas generan la misma exportacion
completa de datos, comparada por SHA-256; no se valida solo el numero de filas.

La comprobacion real de Rola repite ese procedimiento con el SQL de la misma
captura. El original se conserva solo temporalmente para esta comparacion,
sin enviarlo al contexto del modelo ni descargarlo al repositorio. El SQL
transformado se contrasta tambien con el resultado exacto del transformador
y las huellas de entrada. Las exportaciones de comparacion desactivan
comentarios y fechas y ordenan por clave primaria, usando la misma version
de MariaDB y las mismas opciones en ambas bases.

El nuevo volcado de produccion termino en 0.91 segundos, manteniendo el plazo
independiente de bloqueo de 1.5 segundos. La copia figura en ISPConfig como
mysql/sqlgz/gzip. La programacion semanal del panel no cambia y no se crea
otro temporizador. La IA sigue sin acceso a este ejecutor manual.

Antes de exportar, un primer arranque encontro el archivo de errores del
intento anterior y se detuvo sin crear SQL. Se conservaron esos archivos y
el resultado fallido antes de repetir. Los resultados parciales del tramo
25 no se reescriben como exitosos.

## Cierre acotado

Resultado real: 58 tablas y 18 080 filas recuperadas. Las exportaciones
completas de datos del original y del SQL transformado tienen el mismo
SHA-256. Los recuentos coinciden tambien con los capturados bajo READ.
La copia pasa el clasificador textual y su registro en ISPConfig esta
verificado, con los servicios activos y el antipánico sin cambios.

Tras verificar la restauracion, se eliminan exclusivamente el SQL original
temporal, su duplicacion de lectura del ensayo y las bases desechables. Se
conservan la copia SQL textual comprimida y los informes privados de huellas,
recuentos y comparacion. Ninguna fila de las aplicaciones se modifica.

Quedan pendientes la estrategia consistente de las dos bases grandes, la
tabla independiente con error 1932 y los demas bloques de la lista de cierre.
Esta correccion resuelve el rechazo textual de Rola; no acredita que las
copias nativas semanales ya utilicen el nuevo ejecutor acotado.

Fuentes publicadas antes de su ejecucion y replicadas desde R2-D2. Evidencias
privadas: /root/codex-vigilante/tramo-26-2026-10-20 y metadatos en la carpeta
local ignorada. La implementacion global todavia no esta finalizada.
