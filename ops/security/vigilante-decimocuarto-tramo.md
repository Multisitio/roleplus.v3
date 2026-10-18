# Decimocuarto tramo: preparacion de los avisos por WhatsApp

La fuente de verdad del codigo sigue siendo la copia local. La bandeja efectiva
pertenece al VPS. Este tramo prepara la recogida persistente de avisos; no declara
WhatsApp operativo ni registra mensajes como enviados.

## Integracion

El detector existente registra los eventos urgentes antes de comprobar la cuota
y antes de esperar al modelo. No hay otra lectura de registros, temporizador,
peticion HTTP ni reiniciador. Se mantienen el aislamiento sin red y los permisos
del servicio existente. La IA no interviene en el destinatario ni en el texto.

La bandeja /var/lib/vigilante/notifications.json es privada del administrador,
con permisos 0600, escritura atomica y el mismo bloqueo del estado del detector.
Acepta solamente las observaciones anonimizadas del contrato existente y las
webs del catalogo. Los textos se construyen con etiquetas fijas: caida de web o
servicio, error fatal, memoria agotada, espacio o inodos criticos. Los avisos PHP
ordinarios quedan para el resumen diario pendiente de implementar.

Una causa repetida conserva una referencia, primera y ultima observacion. La
ausencia en un ciclo no demuestra recuperacion: se marca unicamente que no fue
observada y nunca como solucionada. Una reaparicion mantiene su aviso pendiente;
la confirmacion de recuperacion y la apertura de episodios nuevos deben completarse
antes de activar un emisor. No se reenvian avisos antiguos automaticamente.

No se guardan registros originales, IP, parametros, texto del modelo, telefono ni
claves en esta bandeja. Se limita a 4096 referencias y 4 MiB; el exceso o un fallo
de persistencia se muestra en el registro del servicio y no detiene el analisis.
No se purgan referencias automaticamente ni se afirma entrega por encolarlas.

## Dependencia pendiente para activar el canal

No se encontro integracion de WhatsApp en el codigo ni servicios de envio
instalados en el VPS. La configuracion local no contiene seccion WhatsApp.
Se pregunto al usuario si tiene una cuenta o proveedor existente o prefiere
preparar el alta oficial con Meta. No se han contratado servicios ni enviado
mensajes. No se han creado credenciales vacias ni copiado claves entre entornos.

La via oficial necesita concretar cuenta y numero emisor, destinatario confirmado,
credenciales y plantilla aprobada para iniciar los avisos fuera de la ventana de
24 horas. La API puede aplicar costes. No se elige un proveedor ni un mecanismo
de vinculacion a la cuenta personal sin resolver esta dependencia.

Referencias oficiales consultadas el 18 de octubre de 2026:

- https://business.whatsapp.com/policy/preview?lang=es_LA
- https://www.postman.com/meta/whatsapp-business-platform/documentation/wlk6lh4/whatsapp-cloud-api

## Verificacion

Diez pruebas nuevas comprueban prioridad, repeticion, reaparicion, ausencia sin
falsa recuperacion, reloj, contrato, catalogo y rechazo de texto libre. Se ejecutan
tambien las dieciocho pruebas del detector existente. La comprobacion en el VPS
debe confirmar la bandeja privada, el ciclo natural, igualdad de fuentes y que
Apache, PHP-FPM, MariaDB y el temporizador del antipanico siguen activos.

La activacion y una entrega real confirmada de WhatsApp siguen pendientes.

## Decision posterior del usuario: coste adicional cero

El usuario descarta cualquier gasto adicional. La dependencia del alta de Meta
descrita arriba queda sustituida por preparar una vinculacion de dispositivo.
OpenClaw documenta el uso de WhatsApp Web mediante Baileys y vinculacion por QR,
incluido el uso de un numero personal. Baileys tiene licencia MIT y no requiere
contratar la API de mensajes de Meta. Se puede estudiar el mismo transporte
aislado para la bandeja existente, sin instalar todo OpenClaw.

La biblioteca no esta autorizada ni respaldada por WhatsApp. La sesion puede
desconectarse o requerir nueva vinculacion; no se promete disponibilidad ni
ausencia de restricciones de cuenta. Sigue pendiente comprobar la notificacion
real en el telefono, especialmente si se usa el chat propio, antes de declarar
operativo el canal. Este cambio de decision no instala ni vincula sesiones.

Fuentes primarias:

- https://docs.openclaw.ai/channels/whatsapp
- https://github.com/WhiskeySockets/Baileys

Resultado del despliegue: 28 pruebas satisfactorias tanto en local como en el
VPS. El siguiente ciclo natural creo la bandeja root:root 0600, sin avisos
urgentes presentes ni entregas declaradas. El detector termino con estado 0;
el panel estaba reciente y la lectura de registros no presentaba errores.
Apache, PHP-FPM, MariaDB y webwatchdog.timer continuaron activos. El archivo del
antipanico conserva su huella anterior. No se reiniciaron servicios ni se
modifico la politica de cuota o las reparaciones automaticas.
