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
- Canal previsto de avisos: WhatsApp. Resumen diario a las 09:00 Europe/Madrid.
  Este tramo no instala mensajeria ni un nuevo programador de avisos.

Decision del 18 de octubre de 2026 para WhatsApp: preparar vinculacion por
dispositivo con Baileys, mecanismo utilizado por OpenClaw, en lugar de contratar
la API de Meta. El transporte sigue pendiente de instalar, aislar y vincular.
La prueba final debe incluir recepcion y aviso real en el movil; la aceptacion
del envio o un mensaje en el chat propio no acreditan que el usuario sea avisado.

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
