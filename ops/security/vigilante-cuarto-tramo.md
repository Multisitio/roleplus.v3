# Vigilante: cuarto tramo

Fecha: 18 de octubre de 2026. Motor de publicacion y recuperacion ensayado
fuera de las webs. La fuente local conserva la autoridad de sus instrucciones.

## Resultado

`vigilante-release-transaction.py` incorpora una transaccion de archivos
existentes, con revision privada vinculada a la huella del manifiesto del
preparador. Exige coincidencia con el commit en la copia local seleccionada.
No crea ni elimina archivos de aplicacion, no cambia datos y no ejecuta
comandos aportados por el paquete, registros o modelo.

La entrada de consola solo acepta casos dentro de
`/root/codex-vigilante/ensayos`. Los destinos se obtienen de esa estructura
fija; no admite una raiz web o un directorio arbitrario como argumento. Solo
admite fuentes Python y comprueba su sintaxis sin ejecutarlas. El modo de
produccion permanece deshabilitado y no se concede acceso al agente.

Cada ensayo verifica todos los archivos antes de escribir el primero:
contenido, longitud, huella anterior, propietario, grupo y modo. Rechaza
enlaces simbolicos en cualquier componente, enlaces fisicos, permisos
especiales y atributos extendidos que no sabe conservar. Se reutiliza el
patron del instalador existente: temporal vecino, propietario y permisos,
sincronizacion del archivo y directorio, y reemplazo de un archivo cada vez.

Antes de escribir quedan copias privadas y un diario duradero con la intencion
completa. Tras publicar se verifica el resultado. Si falla una escritura o la
comprobacion de salud, intenta restaurar. La recuperacion tambien se puede
solicitar despues de una salida abrupta del proceso, usando el diario.

La vuelta atras comprueba contenido y metadatos: conserva cualquier cambio
posterior distinto y registra que necesita intervencion. No sobrescribe esos
archivos para conseguir una aparente recuperacion completa. Se conservan las
copias y el diario, incluidos los casos fallidos.

## Coordinacion

El motor mantiene un bloqueo exclusivo durante la transaccion y recuperacion.
Una prueba usa exactamente el mismo archivo de bloqueo que el control de
reinicios, con unidad y ejecutor simulados: el reinicio se rechaza como ocupado.
Otra comprueba el sentido contrario. Los ensayos reales usan su propio bloqueo
privado; no pausan el antipánico activo ni solicitan reinicios.

Se conserva un catalogo privado para las once webs, con correspondencias
observadas de los directorios publico y privado, sin autorizar publicaciones.
Los destinos de herramientas operativas siguen sin asignar. Los repositorios
pendientes y las diferencias del tramo anterior conservan sus bloqueos.

## Comprobaciones

- Windows: analisis de sintaxis de los nuevos fuentes; no se afirma que el
  motor Linux se haya ejecutado en Windows.
- VPS: 14 pruebas del motor, siete del preparador y ocho del control central.
  Incluyen fallo en la segunda escritura, proceso terminado abruptamente tras
  la primera, recuperacion, cambios posteriores, copia alterada, enlaces,
  sintaxis invalida y exclusion mutua con reinicios. No se llama a servicios.
- Consola real en el VPS: paquete de un commit de un repositorio de ensayo,
  publicacion, restauracion explicita y fallo de salud con restauracion
  automatica. Se verifican contenido, propietario, grupo, modo y codigo de
  salida. Una publicacion fallida sigue devolviendo error aunque se revierta.
- Huellas del control instalado, del antipánico y de su politica conservadas.
  La IA sigue desactivada y se conserva la excepcion del certificado pendiente.
- Fuentes y evidencias privadas replicadas y comparadas por SHA-256.

## Limites antes de produccion

Este ensayo no valida aplicaciones PHP, bases de datos, permisos con ACL,
metadatos especiales, copias integrales ni recuperacion sin espacio disponible.
La comprobacion de salud del ensayo es sintetica. El reemplazo individual no
hace atomico un conjunto de archivos y no coordina escritores ajenos al bloqueo.
Un administrador root comprometido sigue fuera de estas protecciones.

Para abrir escrituras en las webs faltan el aislamiento y permisos del agente,
la autorizacion de destinos y cambios concretos, comprobaciones propias de cada
sitio y recuperacion integral verificada. Tambien falta conectar y acotar la
ventana real de mantenimiento del control central. Cambiar una opcion JSON no
habilita esos mecanismos ni levanta la restriccion de consola.

Codex CLI, WhatsApp y el nuevo resumen diario siguen pendientes. El certificado
sigue en la cola prioritaria; este tramo no modifica certificados.

Evidencias privadas: scratch/vigilante/2026-10-18/tramo-4 y su replica bajo
/root/codex-vigilante/tramo-4. No se publican detalles del catalogo ni registros.
