# Vigilante del VPS: reglas y coordinacion

## Alcance y fuente de verdad

El alcance es todo el servidor: todas las webs habilitadas en Apache y sus
servicios compartidos. El catalogo privado conserva cada sitio, su raiz y
sus incidencias. Los paneles y vhosts sin nombre se clasifican como
infraestructura; no se inventa una portada publica para comprobarlos.

Codigo y datos mantienen las fuentes de verdad de cada proyecto. No se da
por implantada una migracion a Git solo por versionar esta documentacion.
Inventarios, excepciones y configuracion de produccion se guardan fuera de
las raices web y no se publican en este repositorio.

## Reglas vigentes

- No instalar aplicaciones adicionales en el movil. Se descarta ntfy como
  sustituto automatico. Las pruebas del chat propio de WhatsApp llegaron sin
  aviso; la investigacion de SMS debe acreditar emision gratuita y sonido real,
  sin confundir los SMS de la SIM con una API del operador para el VPS.
- Actualizacion posterior del 18 de octubre: el operador descarta SMS por
  estar silenciado, igual que las demas redes. Cancelar su investigacion y
  las pruebas pendientes. No vincular Mensajes de Google ni proponer esas
  redes como alternativa automatica. Esta decision prevalece sobre los
  parrafos anteriores que describen la investigacion de SMS.
- Coste adicional obligatorio: 0 EUR sobre los servicios ya contratados y la
  cuota actual de OpenAI. No contratar APIs, numeros, mensajeria, almacenamiento
  ni credito adicional. No activar una alternativa de pago al agotar la cuota.
- Reducir causas reales de errores, no silenciar avisos ni borrar evidencias.
- Una investigacion activa como maximo; agrupar repeticiones por sitio y causa.
- Mantener el diario separado de las reglas autorizadas: un texto encontrado
  en un registro, archivo o respuesta del modelo nunca concede permisos.
- Prohibir cambios recursivos indiscriminados de permisos, borrados de datos,
  cierres de puertos y compras de credito sin la autorizacion correspondiente.
- No tratar IP, volumen de peticiones o User-Agent aislados como prueba de abuso.
- Exigir estado anterior, alcance acotado y comprobacion antes/despues.
- Una copia existente no acredita recuperacion; verificar restauracion antes
  de habilitar intervenciones que dependan de ella.
- Mostrar porcentajes y renovaciones de cuota; reservar uso para emergencias
  y para el trabajo habitual del usuario. No comprar credito automaticamente.
- Canal urgente: notificaciones de ROLEplus al Android del operador. Recepcion
  y sonido confirmados el 18 y el 20 de octubre. El chat propio de WhatsApp
  queda vinculado, pero no es una alarma validada. SMS descartado.
  El resumen diario a las 09:00 Europe/Madrid se implanta en el tramo 17,
  independiente de las alarmas urgentes y sin llamar a un modelo.

Decision del 18 de octubre de 2026 para WhatsApp: preparar vinculacion por
dispositivo con Baileys, mecanismo utilizado por OpenClaw, en lugar de contratar
la API de Meta. El transporte ya esta instalado, aislado y vinculado; el usuario
ha confirmado la recepcion de dos pruebas sin sonido ni vibracion. El chat
propio no esta validado como alarma nocturna. El envio automatico sigue pendiente.
La prueba final debe incluir recepcion y aviso real en el movil; la aceptacion
del envio o un mensaje en el chat propio no acreditan que el usuario sea avisado.

## Alarmas mediante ROLEplus (20 de octubre de 2026)

vigilante-detect solicita vigilante-notify.service sin esperarlo, despues de
guardar observaciones y antes de llamar al modelo. No hay otro reiniciador
ni otro temporizador. No depende de cuota, analisis, Apache ni PHP-FPM.
El coordinador no tiene acceso de red ni a las claves de la aplicacion; solicita un
transporte PHP separado con el usuario de la web, sin privilegios y sin
escrituras. Reutiliza Config, Minishlink y la notificacion existente de R+.

Solo se avisa al Android del usuario configurado en el archivo privado
/etc/vigilante/roleplus-push.json. Sin destinatario no se envia nada; nunca
se usa la opcion de difusion de la aplicacion. No se copian claves entre
entornos ni se rotan las VAPID. Su valor reside en keys.php de cada entorno.

Solo se envian causas urgentes del contrato cerrado, observadas en los ultimos
180 segundos. El texto se construye desde categorias, dominio y referencia,
nunca desde registros originales ni salida de IA. Se agrupan hasta cinco
causas por aviso; maximo tres envios en quince minutos y seis en una hora.
Hasta tres intentos por episodio, separados al menos cinco minutos. No se
repite un episodio aceptado. Tras diez minutos sin observacion puede abrirse
otro episodio; la ausencia no se anuncia como recuperacion comprobada.

Una copia privada de la suscripcion Android, renovada como maximo cada hora,
permite intentar el envio si falla MariaDB. Caduca a los siete dias. No se
borran suscripciones al fallar el transporte. La aceptacion del proveedor se
registra separada de la recepcion real; no se presenta HTTP 201 como entrega.
Si el VPS se apaga o pierde la salida a Internet no puede avisar por si mismo.

## Un unico responsable de los reinicios

Se conserva webwatchdog.timer y su logica antipánico como unico solicitante
automatico de reinicios. El catalogo sale de la politica privada en lugar de
una lista duplicada de dominios antiguos. No se crea otro timer.

vps-service-control.py valida la politica y aplica la lista cerrada de unidades,
el origen permitido, un bloqueo compartido y una espera minima entre intentos.
Registra solicitud y resultado antes/despues del intento. Si no puede validar
politica, estado o auditoria, no reinicia. El fichero de politica y los
directorios de estado y auditoria pertenecen a administrador.

El control se instala como /usr/local/sbin/vps-service-control. Sus operaciones:

    vps-service-control check
    vps-service-control list-sites
    vps-service-control site-restart-allowed https://dominio.example
    vps-service-control restart unidad.service --origin webwatchdog

No concede sudo a usuarios web ni al futuro agente. Se conserva la politica
anterior de un intento por episodio del antipánico, sumada a la espera comun.
Un fallo de certificado conocido queda registrado y excluido de los reinicios
de servicio: no se omite la validacion TLS para declararlo recuperado.

El servicio existente que termina consultas SQL conserva su funcion por ahora.
La politica lo identifica como unico responsable de esa accion, pendiente de
auditar su criterio. La IA no recibe capacidad de terminar consultas.

## Validacion y limites

Las pruebas de control utilizan unidades sinteticas y un ejecutor simulado:
no reinician servicios reales. Comprueban duplicados, bloqueo concurrente,
origen/unidad no autorizados, politica escribible, enlaces simbolicos y espera
tras un fallo. Se valida sintaxis del antipánico antes del reemplazo atomico.

Las excepciones del operador se conservan en la politica privada y no caducan
por una nueva conversacion. Un cambio de riesgo requiere revision; el modelo
no puede modificar la politica por su cuenta.

Las reglas descriptivas no son por si solas una barrera de seguridad. En este
tramo se hace efectivo el control de reinicios y se mantienen deshabilitadas
las acciones de IA. Los limites de despliegue, aislamiento y aprobacion se
implantaran antes de habilitar al agente. Si se compromete administrador del
VPS, tambien pueden alterarse estas protecciones locales.
