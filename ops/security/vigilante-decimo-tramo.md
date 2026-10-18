# Decimo tramo: diagnostico de una incidencia real

El codigo local sigue siendo la fuente de verdad. La cola, el diario, la sesion
y las evidencias de produccion pertenecen al VPS. Este tramo es manual: no
agrega temporizadores, reinicios ni reparaciones automaticas.

## Recogida y aislamiento

El recolector consulta la politica y selecciona una incidencia TLS pendiente
del catalogo completo. No tiene una lista propia de dos webs. Se ejecuta como
administrador bajo supervision, con un plazo exterior de 45 segundos.
Compara 127.0.0.1 con hasta tres direcciones publicas obtenidas por DNS,
usando el mismo dominio como SNI. Cada conexion tiene un limite de tres
segundos. No envia peticiones HTTP ni modifica certificados.

Si falla la validacion, una segunda conexion recupera exclusivamente el
certificado publico para examinar sus nombres y fechas. Esa recuperacion no
se contabiliza como conexion validada. La conexion a una direccion publica
desde el VPS no equivale a una comprobacion desde otra red.

La instantanea contiene identificador de incidencia, nombres DNS del
certificado, fechas, huellas, codigos numericos de fallo y un mapa resumido
de los servidores virtuales TLS. No exporta claves privadas, rutas de
configuracion, contenido de usuarios, registros completos ni mensajes de error
arbitrarios. El archivo queda a nombre de root, con lectura para la identidad
aislada y montaje de solo lectura. Su estructura, destinos, tamano, huella y
antiguedad maxima de diez minutos se comprueban antes de utilizarlo.

La herramienta real se ejecuta como vigilante-ai, sin perfil de autenticacion,
sin webs y sin red, reutilizando las barreras del tramo anterior. Solo admite
read_incident, sin rutas ni instrucciones. El cliente autenticado recibe sus
datos por una conexion local privada; no monta los archivos de produccion.
El motor de codigo conserva tambien su identidad, raiz y red separadas.

## Analisis y registro

Se reutilizan el transporte, la comprobacion de cuota y la seleccion del
modelo predeterminado. Un bloqueo privado compartido evita dos investigaciones
simultaneas dentro de este ejecutor. La reserva de cuota puede impedir la
llamada antes de crear el hilo. Los datos recibidos se consideran no confiables
y no pueden ampliar permisos ni convertirse en reglas del vigilante.

Se permite exactamente una llamada a leer_incidencia_tls, con argumentos
vacios. La respuesta del modelo debe ajustarse a un esquema JSON cerrado,
referenciar la incidencia y la huella recibidas, citar evidencias existentes
y elegir propuestas de una lista fija. Se rechazan afirmaciones de reparacion,
referencias inventadas y clasificaciones incompatibles con los resultados TLS.
Las propuestas se almacenan como datos; no se interpretan como comandos.

El registro del administrador vuelve a validar la respuesta y archiva el
informe en el estado privado existente del vigilante. Agrega su referencia al
elemento original de la cola y al diario. El estado de la incidencia sigue
pendiente: guardar un diagnostico no acredita una reparacion. No se modifican
la politica, sus excepciones ni el controlador del antipanico.

## Resultado observado

La primera prueba real ha terminado con gpt-6.1-sol, esfuerzo bajo y una llamada
a la herramienta. La identidad observada del lector y del motor fue UID 999,
con capacidades vacias, NoNewPrivileges y redes privadas. Las 19 pruebas sin
modelo han pasado; la lectura real se comprobo antes de consumir cuota.

La incidencia elegida es un certificado que cubre www.kumbia.app y se sirve
tambien para kumbia.app. La validacion falla por nombre tanto en la conexion
local como en la direccion publica; ambas entregan el mismo certificado.
El modelo identifico esa discrepancia y propuso revisar el servidor virtual,
seleccionar o emitir un certificado con el nombre necesario, validar Apache
y comprobar de nuevo ambos accesos. La incidencia permanece sin reparar.
Los informes concretos se conservan en las carpetas privadas del tramo y en
el diario del VPS. Las webs y el antipanico conservan su funcionamiento.

El siguiente cambio funcional es preparar esa reparacion TLS con su propia
validacion y vuelta atras. Este ensayo no habilita al modelo para publicarla.

Protocolo contrastado con la version instalada y la documentacion oficial:
[App Server](https://learn.chatgpt.com/docs/app-server) y
[configuracion recomendada de seguridad](https://learn.chatgpt.com/docs/cyber-safety/recommended-configuration).
