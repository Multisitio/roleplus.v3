# Tramo 19: comprobacion de copias y restauracion aislada

Fecha: 20 de octubre de 2026.

Se ha restaurado el volcado de ROLEplus del 18 de octubre en una instancia
temporal de MariaDB: 80 tablas, importacion completa y comprobacion de tablas
correctas. El SQL descomprimido coincide por SHA-256 con la lectura del archivo
original. El ensayo termino en 124 segundos. No se han escrito datos en la base
de produccion ni reiniciado servicios de las webs.

## Procedimiento reproducible

`vigilante-restore-drill.py` solo funciona como usuario sin privilegios dentro
de `vigilante-restore-drill.service`. Usa los binarios ya instalados, sin otro
servicio contratado. La unidad es manual: no tiene temporizador ni habilitacion
al arranque, y la IA no tiene permiso para iniciarla.

El administrador selecciona un archivo concreto mediante un montaje de solo
lectura en `/run/vigilante-restore-drill/input.sql.gz`. Para el ensayo real se
creo una copia identica dentro del directorio privado de evidencias; no se
ampliaron los permisos del original. La primera ejecucion fallo precisamente
por falta de lectura y se conservaron sus evidencias antes de repetirla.

La instancia usa un usuario dinamico, espacio propio de datos y socket propio.
No tiene red, capacidades de administrador, acceso a las webs, al directorio y
socket de MariaDB en uso, ni al perfil autenticado del vigilante. El cliente
usa `--no-defaults`, socket explicito y modo binario; el servidor desactiva red,
enlaces simbolicos, carga local de archivos y registro binario. Los mensajes
SQL permanecen privados, nunca se incorporan al informe ni al contexto de IA.

Limites: CPU del 25%, memoria de 768 MiB, prioridad baja, diez minutos para la
unidad y 1 GiB de SQL descomprimido. Se rechazan destinos ya existentes: no
hay borrado ni sustitucion automatica de ensayos previos. La instancia se
detiene al terminar; sus datos y el informe quedan privados para revision.

## Cobertura y carencias detectadas

- Los ocho volcados localizados bajo `/var/backup/web*` superan la lectura
  completa de gzip, incluida su comprobacion de integridad. Esto acredita
  integridad del archivo comprimido, no restauracion de las otras siete bases.
- ISPConfig configura las nueve bases activas con frecuencia semanal y una
  sola copia conservada. Cinco archivos son del 18 de octubre y tres del
  12 de abril. Falta un archivo para otra base activa en ese directorio.
- Cuatro bases activas no tienen una correspondencia vigente con un dominio
  en la relacion `parent_domain_id` del panel. No se deduce su web a partir
  del nombre de la base ni del antiguo numero webN.
- Las copias de archivos de las webs estan desactivadas o sin frecuencia en
  ISPConfig. Git no sustituye los archivos aportados por usuarios ni los
  secretos y configuraciones de cada entorno.
- Las copias y el ensayo estan en el mismo VPS: no protegen de perdida total
  del disco o de un administrador comprometido.

La tarea `restore_validation` conserva estado pendiente y prioridad alta,
con evidencia de esta comprobacion parcial. Antes de habilitar reparaciones
autonomas faltan resolver cobertura, frecuencia y retencion, probar las demas
bases y recuperar archivos y configuraciones necesarios para una web completa.
No se ha modificado la configuracion de copias del panel en este tramo.

## Verificacion

Cuatro pruebas de lectura exacta, archivo truncado, expansion excesiva y
conservacion de destino existente, tanto en local como en Linux. Validacion
de la unidad con systemd y ensayo real aislado en el VPS. Las fuentes locales
y desplegadas coinciden; el antipánico conserva su huella y las acciones de
IA siguen deshabilitadas. Evidencias privadas en
`/root/codex-vigilante/tramo-19-2026-10-20` y su copia local ignorada por Git.
