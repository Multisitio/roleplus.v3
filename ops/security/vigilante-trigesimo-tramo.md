# Tramo 30: recuperacion de la tabla con error 1932

Fecha: 20 de octubre de 2026.

Se recupera c1_deportes.usuarios desde sus propios archivos. ISPConfig mantiene
esta base independiente, database_id 7 y parent_domain_id 0; no se le asigna
una web ni se sustituyen datos por el candidato distinto de R2-D2.

## Fuente y contraste aislado

La definicion se extrae del .frm original con el lector de dbsake, revision
48e6d429bc8de2cfea5e56651f87d2e94927c775, descargado temporalmente y sin instalar
dependencias. Quedan su procedencia y la definicion SQL en la evidencia privada.
Nueve columnas y clave primaria id. Los SHA-256 de frm e ibd coinciden con los
registrados en el tramo 23, antes y despues de tomar las copias de trabajo.

Una unidad con usuario dinamico, sin red ni acceso al socket, datos o claves
de produccion, importa una copia del ibd en un MariaDB temporal. Entradas
privadas mediante LoadCredential, 512 MiB, CPU 75% y plazo de tres minutos.
Se verifican las sumas de las paginas, CHECK TABLE EXTENDED y una fila.
El SQL recuperado se restaura en otra base y se comparan estructura, volcado
canonico y todos los valores, incluidos NULL y bytes de los textos.

Se reproduce tambien el error 1932 con los originales copiados en una base
sin entrada InnoDB. Se ensaya preparar otra tabla, comprobarla, cerrar la
entrada afectada, apartar los originales y renombrar la tabla preparada.
La sustitucion conserva todos los valores y la estructura.

## Aplicacion de produccion

Ejecutor puntual de administrador, bajo el bloqueo central de actuaciones y
el bloqueo de las copias independientes. Comprueba nuevamente error, ausencia
del diccionario, inventario y huellas originales. No habilita reparaciones
automaticas ni da a la IA acceso a este ejecutor.

El primer intento de preparar la tabla encuentra errno 13: el directorio
mysql de esta base tiene modo 0555. Se detiene sin mover los originales ni
crear tablas. Se registra el intento y se corrige exclusivamente ese directorio
a 0750, conservando mysql:mysql; no hay cambios recursivos. Esto explica el
impedimento para preparar la tabla, no la causa historica del error 1932.

Segundo intento: preparacion y contraste completos antes de apartar los
originales. Se conservan ambos archivos originales, con identicas huellas,
en el directorio privado originals de la evidencia; no se borran ni se crean
archivos de respaldo binarios. Sustitucion mediante RENAME TABLE en 0.0437 s.
La tabla final supera CHECK TABLE y conserva exactamente estructura y valores.
El proceso principal de MariaDB sigue siendo 1544432; ningun servicio se
reinicia. No se altera otra base, permisos de otros sitios o datos locales.

## Proteccion y cierre

Copia SQL nueva de produccion: 2502 bytes de texto, 1007 bytes comprimidos,
una fila, 0.2869 s de exportacion acotada y sin bloqueo global. Se restaura en
el laboratorio y vuelve a coincidir en todos los valores y la estructura con
la recuperacion original. No contiene binarios.

La base se incorpora al catalogo y al registro del programador independiente
existente. Se retira el bloqueo 1932; quedan ambas bases independientes bajo
el mismo responsable, domingo a las 04:30 de Madrid y siete versiones por
base. No se vuelve a exportar la otra base ni se crea otro temporizador.

Incidencia 080ccf40-b487-53cb-9577-e6370eeae0c4 resuelta y anotada en el diario.
El criterio general de restauraciones sigue pendiente por las bases grandes.
Se eliminan la instancia temporal y las copias binarias de trabajo; se
conservan los originales apartados, SQL de texto y evidencias privadas.
Apache, MariaDB, PHP-FPM y los temporizadores existentes siguen activos.

Evidencia: /root/codex-vigilante/tramo-30-2026-10-20. Los SQL con datos de
usuarios permanecen privados y fuera de Git. Fuentes, configuracion sin
secretos y documentacion quedan publicados en GitHub desde R2-D2.

Quedan cuatro pasos de implementacion: SQL grande y programacion unificada,
automatismos heredados, reparaciones acotadas y aceptacion conjunta.
