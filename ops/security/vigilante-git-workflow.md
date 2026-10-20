# Coordinacion de codigo entre R2-D2, GitHub y el VPS

La fuente de codigo sigue siendo la copia local indicada por cada proyecto.
GitHub conserva las revisiones aceptadas y permite contrastar una revision
comun. Los datos, archivos de usuarios y configuraciones de cada entorno
conservan sus fuentes propias; no se sincronizan con un barrido bidireccional.

1. Trabajar en el repositorio propio del proyecto y revisar los cambios de la
   tarea. Conservar cambios ajenos, incluidos los que todavia no tienen commit.
2. Confirmar y publicar los archivos de la tarea mediante push normal. Si
   GitHub ha avanzado, contrastar e integrar los cambios antes de continuar;
   no usar force-push, resets ni sobrescrituras para resolverlo.
3. Antes de preparar un paquete, ejecutar `vigilante-git-guard.py` con la raiz
   y el identificador completo del commit. Exige que HEAD y origin/main sean
   esa revision y rechaza modificaciones locales de archivos versionados.
   Una consulta fallida tambien impide continuar. No imprime URLs ni errores
   de Git que puedan incluir credenciales.
4. La consola de `vigilante-release-plan.py` aplica esa comprobacion antes y
   despues de preparar el paquete. Registra la revision comun en el manifiesto
   y mantiene `deployment_enabled=false`. La funcion interna de preparacion
   conserva sus ensayos sin red; no concede un permiso de despliegue.
5. Antes de escribir en produccion, el futuro ejecutor debe volver a contrastar
   la fuente local, GitHub y las huellas de todos los destinos, manteniendo el
   bloqueo de la operacion durante escritura y recuperacion. Una comprobacion
   de Git es una fotografia, no un bloqueo que otros programas de Git respeten.
6. La IA del VPS sigue sin permiso de escritura. Su integracion futura debe
   comprobar la fuente de R2-D2, no solamente un clon limpio del VPS. Si no
   puede contrastarla o detecta trabajo pendiente, debe escalar y esperar.

No se ha instalado un proceso que confirme automaticamente cualquier cambio
del usuario ni un sincronizador que mezcle directorios. La comprobacion real
de ROLEplus encontro HEAD igual a GitHub, pero cambios locales pendientes;
la preparacion de despliegues quedo bloqueada y esos cambios se conservaron.
Los commits propios del vigilante siguen publicandose al cerrar cada tramo.

Esta barrera ya se aplica al preparador. La comprobacion distribuida justo
antes de una reparacion real y el bloqueo integral siguen siendo condiciones
del bloque de reparaciones acotadas, no capacidades ya habilitadas.
