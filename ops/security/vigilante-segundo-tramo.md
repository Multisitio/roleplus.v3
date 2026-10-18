# Vigilante: segundo tramo

Fecha: 18 de octubre de 2026. Alcance ampliado a todas las webs activas y a
la infraestructura compartida del VPS, por correccion expresa del usuario.

## Resultado

- Catalogo privado de los 11 sitios con ServerName y de las tres
  configuraciones adicionales de infraestructura encontradas en Apache.
- Reglas, excepciones, diario inicial y cola de trabajo separados, fuera de
  las raices web. Los detalles de exposicion y registros no se publican.
- El antipánico existente obtiene las webs del catalogo actual y canaliza
  los reinicios por vps-service-control.py. Se conserva su timer y no se crea
  otro reiniciador. Hay lista cerrada de unidades, origen permitido, bloqueo,
  espera comun y auditoria privada con rotacion.
- Correccion puntual de cinco directorios escribibles por otros usuarios y
  del acceso al archivo de claves de ROLEplus, sin cambios de propietario ni
  operaciones recursivas. Se conservan los modos anteriores para revertir.
- Cola privada con seis familias de errores HTTP 500 por sitio y cuatro
  incidencias adicionales de TLS, recuperacion y coordinacion/seguridad.
  Los errores PHP/HTTP registrados no se dan por corregidos en este tramo.

## Comprobaciones

Ocho pruebas con ejecutor simulado, sin systemctl real: repeticion de solicitudes,
concurrencia, origen/unidad no autorizados, politica escribible, enlace simbolico,
espera tras fallo, excepcion desconocida y activacion accidental de IA.

Validacion de sintaxis del antipánico y del control, configuracion privada y
rotacion. Comparacion de fuentes locales y publicadas por SHA-256. Comprobacion
real del rechazo del origen de IA antes de ejecutar cualquier accion.

Tras el cambio de permisos, el usuario del pool conserva escritura en los cinco
directorios y lectura del archivo de claves; otro usuario web no puede leerlo.
Apache conserva acceso a su archivo de reglas. Se usan temporales propios
retirados al terminar, no datos de usuarios. Estas comprobaciones no sustituyen
una prueba completa de todos los formularios autenticados.

Dos rondas HTTP locales con nombre/SNI y validacion TLS, tras permisos y tras
actualizar el antipánico: diez portadas 200; el fallo TLS preexistente restante
queda identificado como incidencia y excluido de los reinicios. No se usa
--insecure para declararlo sano. No se realiza una prueba de carga.

El timer existente ha ejecutado el script publicado con resultado success.
Tras tres fallos consecutivos de la excepcion TLS, el ciclo natural registro
revision requerida sin solicitar reinicios. Se conserva el aviso de correo
del antipánico anterior; no se confunde con WhatsApp ni con el resumen nuevo.
La instalacion y las pruebas no solicitaron reinicios de servicios.

## Limites y siguiente tramo

No estan instalados Codex CLI, WhatsApp ni el nuevo correo diario. No hay acciones
autonomas del modelo. Las reglas de despliegue, aislamiento y aprobaciones
todavia deben implantarse antes de darle capacidad de reparacion.

Los modos corregidos no acreditan una auditoria recursiva de todos los archivos.
El criterio del terminador SQL y el estado legado del antipánico quedan como
trabajo explicito; no se deshabilitan ni se sustituyen silenciosamente.

Evidencias privadas: scratch/vigilante/2026-10-18/tramo-2 y replica del VPS.
La politica efectiva esta en /etc/vigilante; diario y cola en /var/lib/vigilante.
Las fuentes y datos siguen las instrucciones actuales de cada proyecto.

El siguiente tramo del plan es concretar Git y el despliegue. La cola prioriza
las causas recurrentes y las condiciones de recuperacion antes de habilitar
reparaciones automaticas.
