# Conexion del mantenimiento al ejecutor SQL

## Alcance de este paso

El conector vigilante-sql-window.py reutiliza el aviso, la barrera Apache y
el ejecutor SQL existentes. Antes de READ comprueba el estado HTTP del sitio,
la ausencia de cookies y la respuesta 503 identificada de una peticion POST
ficticia. Espera ocho segundos de aviso previo. Comprueba la vigencia tambien
despues de inventariar las tablas y antes de empezar el volcado bajo READ.

Usa el bloqueo del controlador central para coordinar la ventana con el
antipanico. No crea un reiniciador ni modifica los horarios existentes.
Retira solo su propio aviso al terminar o fallar. El estado y el bloqueo SQL
siguen teniendo sus caducidades independientes si desaparece el coordinador.

Las bases grandes solo admiten los limites revisados de ROLEplus (15 s) y
MTGsearch (45 s), y se rechazan fuera de 03:00-06:00 de Europe/Madrid. Las
bases no revisadas no pueden ampliar el limite anterior de 1.5 segundos.
Las consultas de la copia no cambian los limites globales de MariaDB.

## Comprobacion

Unidad sin privilegios, red privada y socket de produccion inaccesible.
Apache y MariaDB temporales, tablas MyISAM/InnoDB, aviso previo, bloqueo,
volcado, restauracion y coincidencia de datos. Prueba tambien el camino de
45 segundos con una base pequena sintetica: no acredita el tiempo de la
base grande real. El aviso ausente, la barrera ausente y la caducidad antes
de READ abortan sin copiar; las escrituras de ensayo siguen disponibles.
Una excepcion retira el aviso. Las pruebas anteriores del cartel se mantienen.

El publicador de estado del laboratorio es un adaptador sin privilegios que
escribe solo en su RuntimeDirectory. No relaja las exigencias root del
publicador real. Se conservan resultados privados en
/var/lib/vigilante-sql-window-check/report.json. La instancia y sus binarios
temporales desaparecen al terminar; no se crean copias binarias permanentes.

## Pendiente del bloque SQL

Todavia no se sustituye la ejecucion SQL nativa de ISPConfig ni se modifica
el temporizador de bases independientes. Falta la programacion unica y
obtener/restaurar las primeras copias grandes durante la ventana autorizada.
No se ha activado mantenimiento ni bloqueo de produccion en este paso.
La implementacion completa del vigilante sigue pendiente de tres bloques.
