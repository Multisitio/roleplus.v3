# Tramo 17: correo diario a las 09:00 de Madrid

El resumen se genera de forma determinista en el VPS y se entrega al correo
del usuario operador ya configurado para ROLEplus. No crea conversaciones,
turnos ni llamadas al modelo. No utiliza una API de pago ni otro proveedor.
Las alarmas urgentes de R+ y el control de reinicios mantienen su recorrido.

## Contenido y limites de las cifras

Antes de preparar el informe se ejecuta la consulta de cuota ya instalada.
Se utiliza solo el nuevo resultado de account/rateLimits/read: porcentajes,
duracion de ventanas y fechas de renovacion. No se solicita account/usage/read
ni se recopilan cantidades de tokens. Los limites son compartidos por la
cuenta; no se presentan como consumo exclusivo del VPS. Los datos ausentes,
caducados o futuros se indican como desconocidos, nunca como 0% consumido.
Una fecha de renovacion vencida no acredita cuota renovada.

La consulta de cuota adquiere ahora el mismo investigation.lock que el analisis
autenticado. Si esta ocupado falla de forma cerrada y el informe puede indicar
cuota desconocida; no interrumpe el analisis ni solicita herramientas nuevas.
No se consumen renovaciones extraordinarias ni se compra credito.

El correo incluye todos los sitios de la politica privada, estado reciente
del panel existente, servicios compartidos, disco e inodos, causas urgentes
observadas en las ultimas 24 horas y enlaces a sus fichas privadas. El recuento
de cada causa es el de su ultima observacion: no se presenta como suma de
errores de todo el dia. Los estados de la cola y el numero de analisis iniciados
se muestran por separado. No se copian textos originales de registros ni
propuestas libres del modelo. No constituye un inventario exhaustivo de cada
advertencia PHP emitida durante el dia.

Se consultan los porcentajes al preparar el correo. Este tramo no instala una
comprobacion independiente en cada fecha de renovacion: sigue pendiente.
La reserva del ultimo 10% y las barreras de las investigaciones permanecen.

## Aislamiento y correo

vigilante-daily.service tiene red privada, capacidades vacias, sistema de
archivos de solo lectura y escritura limitada a su estado privado. No puede
leer el perfil autenticado ni las raices web. Lee solo una copia montada del
status.json del panel en su directorio de ejecucion. Este montaje especifico
permite mostrar las webs sin ampliar el acceso al resto de esa raiz web.

El envio se ejecuta como el usuario PHP de ROLEplus en una unidad separada,
sin capacidades ni escrituras y con salida IP limitada al equipo local.
Resuelve el correo del identificador privado del operador mediante una
consulta de solo lectura; conserva una copia privada de hasta siete dias
para poder intentar el envio si falla SQL. No admite difusion general.

Se reutiliza _mail y PHPMailer. La opcion LocalSMTP solo se aplica en CLI:
conecta al Postfix ya existente por 127.0.0.1:25 sin autenticacion. Evita depender
de la elevacion setgid de postdrop dentro del proceso aislado. Los consumidores
habituales conservan isMail; no se abren puertos ni se cambian sus credenciales.
Todas las claves siguen en keys.php del entorno correspondiente.

## Horario, repeticiones y mantenimiento

vigilante-daily.timer usa OnCalendar con Europe/Madrid, a las 09:00, incluyendo
el cambio entre horario de verano e invierno. Persistent permite recuperar una
ejecucion perdida tras una parada. Es un temporizador de informes; no solicita
reinicios ni sustituye el temporizador antipánico.

Un bloqueo propio evita informes concurrentes. Se reserva el intento diario
antes de entregar el correo. El resumen inicial ocupa la misma fecha que el
ordinario para evitar dos envios al habilitar el horario. No hay reintentos
automaticos de una entrega incierta; se conserva el estado para investigarla.
El historico de intentos queda acotado a 90 dias y solo se mantiene el ultimo
informe, ademas de una vista previa administrativa separada. --preview permite
validar contenido y permisos sin enviar correo ni reservar la fecha.

El ensayo inicial fue recibido por el operador. Su apartado de webs no pudo
leer el panel por el aislamiento de la unidad. Se corrigio mediante el montaje
de solo lectura y se verificaron los once sitios en una vista previa posterior,
sin repetir el envio inicial. La cuota del ensayo si procedia de una consulta
real. Se conserva el correo original como evidencia, sin cambiar su contenido.

Pruebas: porcentajes compartidos, ausencia y antiguedad de datos, renovacion
vencida, campos no confiables excluidos, filtro temporal de observaciones,
cambio de hora de Madrid y conservacion del transporte habitual del correo.
Se comprobo la identidad sin privilegios del proceso que envio el ensayo.
No se reinicio ningun servicio web ni se modifico el programa antipánico.

Para suspender el informe se deshabilita y detiene solo vigilante-daily.timer;
no se detienen el detector, las alarmas ni el control de reinicios. Las copias
previas y resultados concretos permanecen en las carpetas privadas del tramo.

Referencia del protocolo: [OpenAI Docs: App Server](https://learn.chatgpt.com/docs/app-server),
account/rateLimits/read. Se contrasta con la consulta efectiva de la version
instalada; no se cambia el modelo ni el metodo de autenticacion.
