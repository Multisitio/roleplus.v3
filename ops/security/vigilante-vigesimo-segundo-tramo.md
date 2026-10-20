# Tramo 22: copias de texto y barrera de revision comun en Git

Fecha: 20 de octubre de 2026.

## Correccion de alcance de las copias

El usuario limita las copias a archivos de texto. La copia completa de CSS del
tramo anterior contenia 35 archivos binarios; se ha sustituido el mecanismo,
no una lista incompleta de extensiones de imagen. Las copias completas web
automaticas quedan desactivadas en ISPConfig para las once webs del catalogo.
Los dos programas anteriores que activaban o ejecutaban copias completas
rechazan ahora su ejecucion. Sus fuentes historicos privados no son ejecutores
automaticos ni herramientas concedidas a la IA.

`vigilante-text-backups.py` incluye codigo y configuracion de esas once raices,
mas core y vendor compartidos. Selecciona extensiones de texto conocidas y
archivos como .htaccess y .env; comprueba el contenido entero como UTF-8 o
texto heredado Windows-1252, rechazando controles y firmas binarias. Un PNG
renombrado como PHP no entra. Conserva los bytes originales, sin convertir la
codificacion. Es una seleccion conservadora, no un clasificador universal de
cualquier formato textual. La lista exacta vive en SUFFIXES y candidate().

Se excluyen directorios de imagenes, fuentes, audio, cargas, estadisticas,
registros, temporales, caches y repositorios. Los volcados SQL bajo config
tambien se excluyen de esta copia de codigo: las bases conservan su mecanismo
de copia SQL independiente y sus limites de consistencia pendientes.
No se siguen enlaces ni componentes de directorio sustituidos por enlaces.
Archivos de mas de 16 MiB, enlaces duros o modificaciones durante una lectura
detienen la copia para revision; no se oculta una copia incompleta como valida.

Cada tar.gz contiene solo los archivos de texto seleccionados. La compresion
es el contenedor, no una inclusion de recursos binarios. Se conservan huellas,
propietario, grupo y permisos; cada archivo se relee desde la copia, y se
comprueba tambien el final de gzip. Los manifiestos y las copias son privados,
con modo 0600: pueden contener keys.php y configuraciones reales. Ninguno de
esos contenidos se incorpora a Git, a registros publicos o al contexto de IA.

## Un unico responsable de las copias de archivos

`vigilante-text-backups.timer` programa domingos a las 04:15 de Madrid y
recupera una ejecucion perdida. El ejecutor usa un bloqueo propio y un registro
de vencimientos por raiz. Las bases siguen a cargo de ISPConfig; sus campos no
se modifican. ISPConfig muestra las copias de texto en su listado de cada web
con tar_gzip/rootgz, pero la frecuencia web nativa figura desactivada: su
responsable real es esta unidad, registrada en la politica privada.

La unidad tiene las webs en solo lectura, acceso al panel por socket local,
sin red ni perfiles autenticados, CPU del 25%, memoria de 256 MiB y plazo de
quince minutos. Exige mas de 20 GiB libres y limita cada copia a 512 MiB de
texto. Retiene dos copias verificadas por raiz; solo elimina archivos de su
propio registro cuyo nombre y SHA-256 coincidan. No barre originales ni otros
volcados. Una copia interrumpida o una discrepancia obliga a conciliarla antes
de crear otra, evitando acumulaciones de reintentos.

La copia completa de CSS creada por el agente se retira solo despues de
verificar su sustituta de texto y contrastar nombre, tamano, huella y registro
del panel. Se conservan sus metadatos de ensayo; no se borra ningun binario
original de una web ni copias personales o de bases de datos.

Estas copias permiten recuperar los textos seleccionados. **Los binarios de
usuarios quedan expresamente fuera de cobertura**, y almacenar todo en el
mismo VPS tampoco protege de su perdida total. No se mantiene como requisito
de cierre una copia de imagenes que el usuario acaba de descartar.

## GitHub y operaciones concurrentes

Se registra la correccion en AGENTS.md y se incorpora la barrera de Git al
preparador de paquetes. Rechaza cambios locales pendientes, repositorios
heredados, revisiones no publicadas o divergentes y consultas remotas fallidas.
Compara antes y despues de preparar; no mezcla ni descarta trabajo. Las
comprobaciones reales de ROLEplus acreditan la revision publicada comun y
el bloqueo por trabajo local pendiente. No se incluyen cambios ajenos en el
commit de este tramo.

La barrera no sustituye la coordinacion antes de escribir: las reparaciones
reales siguen deshabilitadas hasta poder contrastar R2-D2, GitHub y el destino
bajo el bloqueo de la operacion. Un clon limpio del VPS no acredita que R2-D2
este limpio. El procedimiento y sus limites estan en vigilante-git-workflow.md.

## Validacion

Siete pruebas de seleccion, binarios renombrados, caracteres de control,
enlaces, archivo alterado y recuperacion en Linux. Siete pruebas de Git y
preparador, con repositorios temporales propios y remotos locales: revision
comun, cambios pendientes conservados, falta de publicacion, divergencia,
repositorio padre y consola del preparador. Las siete pruebas previas del
preparador siguen pasando. Las comprobaciones de Git tambien se ejecutan
localmente en Windows; el bloqueo fcntl del ejecutor de copias se valida en
Linux, su entorno real.

La primera ejecucion real se revisa antes de habilitar el temporizador. Se
conservan informe agregado, pruebas de recuperacion y verificaciones bajo
`/root/codex-vigilante/tramo-22-2026-10-20`, con metadatos replicados en la
carpeta local ignorada por Git. Las claves y los textos recuperados permanecen
en el VPS. La implementacion global sigue pendiente segun su lista de cierre.

Resultado real: 36 483 archivos de texto en trece raices, 28.7 MiB comprimidos.
Se recuperaron codigo y configuracion de ROLEplus en nombres privados
controlados y se probo una segunda ejecucion sin duplicar copias. Las once
copias web figuran en ISPConfig; la politica SQL permanece identica. Proxima
ejecucion comprobada: 25 de octubre de 2026 a las 04:15 CET, hora de Madrid.
