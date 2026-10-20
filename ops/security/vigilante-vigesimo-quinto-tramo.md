# Tramo 25: primeras copias SQL nuevas y acotadas

Fecha: 20 de octubre de 2026.

## Resultado comprobado

Copias nuevas de Controlate, Delixio, Mansion y Multisitio: 30 tablas y
127 437 filas, restauradas en aislamiento. Los recuentos recuperados coinciden
con los capturados bajo READ en cada base. Las exportaciones completas,
incluida su preparacion, tardaron entre 0.35 y 0.73 segundos.

Controlate recupera nueve tablas y 2500 filas; Delixio, una y 84; Mansion,
16 y 122 340; Multisitio, cuatro y 2513. Los archivos anteriores de abril
de las dos primeras no se borran. No se cambia informacion de las aplicaciones
ni se reinician sus servicios. Las nuevas copias figuran en ISPConfig como
mysql/sqlgz/gzip, en el directorio del dominio actualmente acreditado.

## Procedimiento y propiedad de las operaciones

Se reutiliza vigilante-sql-backup.py: READ por base, plazo independiente de
1.5 segundos, limite por sentencia de 0.5 segundos y cierre de clientes propios.
La compresion y la comprobacion de gzip se hacen despues de liberar READ.
Los ensayos anteriores de cancelacion, muerte y suspension del controlador
siguen siendo los de ese mismo mecanismo, sin ampliar sus plazos.

vigilante-mapped-sql.py exige un plan privado, una prueba aislada satisfactoria
con huella y correspondencias activas verificadas contra el panel. Contrasta
el catalogo antes de cada copia y antes de registrarla. Solo admite las cinco
bases pequenas revisadas; una prueba real rechazo MTGsearch antes de exportar.
Reserva el resultado de forma exclusiva: una aplicacion parcial no se repite
ni sobrescribe a ciegas. Los fallos y exitos parciales quedan conservados.

La unidad manual tiene CPU del 75% de un nucleo, memoria de 192 MiB, plazo de
60 segundos, archivos limitados a 32 MiB, SQL aceptado hasta 16 MiB y reserva
de 20 GiB de disco. Sin red ni perfiles autenticados, no puede leer los
archivos de las webs ni los de MariaDB. Usa exclusivamente el socket SQL.
No es una herramienta de la IA ni tiene temporizador: ISPConfig sigue siendo
el unico programador de las siete bases asociadas, semanal y con siete copias.
No se cambia esa politica ni se habilita la reparacion autonoma.

Los ensayos previos importan las copias antiguas, exportan con el limite real
y restauran ese segundo SQL para contrastar sus filas. Rola y Mansion no
cupieron con CPU del 25%; pasaron con el 75% previsto para el copiador. Se
conservan ambos resultados y no se aumenta el plazo de bloqueo. La prueba
de produccion tiene su propio resultado: el ensayo aislado no sustituye su
verificacion. Las restauraciones finales vuelven al limite de CPU del 25%.

## Rechazo de Rola y cobertura pendiente

El volcado actual de Rola termino, pero la validacion de contenido rechazo dos
caracteres de control U+0003. Es UTF-8 valido y tiene cierre de volcado; esto
no demuestra corrupcion del servidor ni un fallo de bloqueo. No se eliminan
esos caracteres de los datos ni se normaliza SQL sin verificar equivalencia.
Se registran codigo de caracter, numero y huella, sin exponer valores de filas.
El temporal propio rechazado se retira; la copia anterior permanece intacta.
El resultado del plan de cinco bases conserva completed=false; el subconjunto
de cuatro tiene una verificacion independiente, sin falsear cobertura global.

ROLEplus y MTGsearch mezclan InnoDB y MyISAM; single-transaction no garantiza
por si solo la consistencia de MyISAM. Referencia:
[manual de MariaDB](https://mariadb.com/docs/server/clients-and-utilities/backup-restore-and-import-clients/mariadb-dump).
Su volumen es ext4 sobre LVM lineal y el grupo de volumen tiene cero bytes
sin asignar. Eso no significa que el sistema de archivos este lleno: carece
de espacio LVM para una instantanea convencional. No se reduce el sistema
de archivos, cambia el motor de tablas ni amplia el bloqueo en este tramo.

Quedan pendientes la representacion textual fiel del SQL de Rola, la estrategia
consistente de las dos bases grandes y la tabla independiente con error 1932.
Las copias nativas existentes de las siete bases siguen programadas: la falta
de una estrategia nueva comprobada no se resuelve desactivandolas.

Evidencias privadas: /root/codex-vigilante/tramo-25-2026-10-20; metadatos
replicados en la carpeta local ignorada. Se conservan solo SQL textual
comprimido y documentos de evidencia; los datos binarios de restauracion son
temporales y se eliminan por el mecanismo ya probado. Las copias en este
mismo VPS no cubren su perdida total. La implementacion no esta finalizada.
