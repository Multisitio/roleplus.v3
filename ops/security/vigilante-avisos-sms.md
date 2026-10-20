# Avisos SMS sin gasto adicional: estado de la investigacion

Estado final: via descartada expresamente por el usuario porque los SMS estan
silenciados, igual que las demas redes. Cancelar la prueba manual pendiente y
no continuar con la vinculacion de Mensajes de Google. Lo que sigue conserva
el contexto de la investigacion, no una tarea autorizada pendiente.

Requisitos del operador: no instalar otra aplicacion en el movil, no contratar
otra linea ni servicios de pago. El canal de WhatsApp esta vinculado, pero las
dos pruebas al chat propio llegaron sin sonido ni vibracion. Sigue pendiente
validar un canal capaz de avisar con la pantalla bloqueada. Actualizacion:
el operador confirmo recepcion y sonido en ROLEplus; esta es la via elegida.

## Proveedor externo gratuito

Textbelt anuncia un SMS gratuito diario y cobertura internacional, incluida
Espana. Una unica peticion de prueba desde el VPS con su clave publica gratuita
fue rechazada: los SMS gratuitos estan desactivados para este pais por abuso.
No se envio el SMS. No se uso una clave de pago, cuenta facturable ni recarga;
no se repite la peticion ni se habilita una alternativa de pago.

Las pruebas comerciales con caducidad no resuelven el requisito de gasto cero
permanente. Tampoco se presenta como disponible el alta gratuita de CallMeBot,
cuya pagina indica que esta completa.

## Uso de la SIM y aplicacion ya existentes

DIGI publica 1000 SMS incluidos a destinos DIGI en sus tarifas moviles. Eso no
constituye por si mismo un servicio para enviar SMS desde el VPS. Mensajes de
Google permite enviar desde un dispositivo vinculado a traves de la SIM y la
conexion del telefono; se aplican las condiciones del operador y el telefono
debe estar conectado.

La automatizacion del protocolo se ha investigado en mautrix/gmessages. Su
documentacion actual indica que Google ha retirado la antigua vinculacion por
QR y describe acceso mediante cuenta de Google con cookies de sesion y
confirmacion de un emoji en el telefono. No se han instalado ese conector,
Matrix ni un navegador en el VPS, ni se han obtenido o copiado esas cookies.

Antes de preparar esa vinculacion se ha solicitado una prueba desde la
aplicacion existente: programar un SMS propio, salir de la conversacion y
bloquear el telefono. Debe comprobarse que es SMS y que genera una notificacion
real; la mera llegada no acredita que sirva como alarma. El resultado esta
pendiente. No se activan envios automaticos por esta via.

## Fuentes primarias consultadas el 18 de octubre de 2026

- https://textbelt.com/
- https://docs.textbelt.com/faq/sending-and-receiving-messages
- https://www.digimobil.es/movil/
- https://support.google.com/messages/answer/7611075?hl=es-419
- https://support.google.com/messages/answer/10456318?hl=es
- https://docs.mau.fi/bridges/go/gmessages/authentication.html
- https://www.callmebot.com/es/blog/api-gratis-mensajes-whatsapp/
