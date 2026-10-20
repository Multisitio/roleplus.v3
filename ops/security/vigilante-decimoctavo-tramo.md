# Tramo 18: comprobacion automatica de renovaciones de cuota

Las comprobaciones de cuota dejan de depender de que se solicite un analisis.
Se reutiliza el ciclo existente del detector: incluso sin incidencias, lee la
fecha de la proxima comprobacion y solicita vigilante-quota-refresh.service
solo cuando corresponde. No se agrega otro temporizador ni un proceso de IA
permanente. La consulta al proveedor se ejecuta en una unidad separada y no
retiene el bloqueo de estado del detector ni retrasa las alarmas de R+.

## Fechas y presupuesto de consultas

La siguiente comprobacion queda prevista cinco segundos despues de la primera
renovacion anunciada, o dentro de seis horas si esa fecha queda mas lejos.
La ejecucion ocurre en el primer ciclo posterior del detector; no se promete
precision de cinco segundos si el detector esta ocupado. La revision cada
seis horas tambien permite detectar cambios anticipados de disponibilidad.

Una denegacion nueva del analisis puede adelantar la consulta a la fecha de
renovacion que el proveedor acaba de comunicar. No puede anular la espera
despues de una consulta fallida ni saltarse el presupuesto. Los fallos se
reintentan a los 15 minutos, 30 minutos, una hora y despues con separaciones
crecientes hasta seis horas. Maximo ocho intentos independientes en 24 horas.
Se reserva cada intento antes de consultar para acotar tambien las caidas.

El servicio reutiliza vigilante-quota.service, su perfil separado, la pasarela
limitada y el bloqueo compartido de investigacion. Solo ejecuta initialize,
initialized y account/rateLimits/read, sin turnos, herramientas, compras ni
consumo de renovaciones extraordinarias. La lectura comun del resultado nuevo
tambien se reutiliza en el correo diario; no se interpreta una salida anterior
como el resultado de una consulta nueva.

## Retirada de esperas

El detector registra expresamente cuando una espera procede de una denegacion
de cuota que no llamo al modelo. Solo una consulta posterior, con menos de
300 segundos de antiguedad y admision disponible, puede retirarla. La mera
llegada de la fecha anunciada, datos desconocidos, una reserva del 90% o un
limite agotado no habilitan el trabajo. Un fallo borra la admision anterior de
la copia de cuota para evitar que una disponibilidad vieja retire una espera.

Retirar la espera conserva los intentos, el historial de causas informadas,
el maximo de dos intentos de analisis en 24 horas y la separacion minima de
una hora. No se vuelven a analizar causas ya informadas solo por renovarse la
cuota. El ejecutor sigue comprobando cuota real antes de cada analisis y las
reparaciones automaticas permanecen deshabilitadas.

Habia una espera antigua de varios dias aunque una consulta posterior ya
indicaba disponibilidad. La migracion administrativa exigio el informe original
de denegacion, sin llamada al modelo, y que su fecha coincidiera exactamente
con la espera guardada. Solo despues de una consulta real nueva se retiro la
espera. Se conservaron y compararon los intentos y el historial, con copia
privada del estado anterior y registro en el diario. No se migran de forma
general bloqueos cuyo origen no este acreditado.

## Aislamiento, estado y verificacion

El nuevo coordinador funciona sin red, capacidades ni acceso al perfil
autenticado o a las webs. Sus escrituras se limitan al estado privado del
vigilante. Usa bloqueo propio y archivos de administrador para la copia de
cuota, intentos y fechas. Su lectura y la solicitud de la unidad suceden antes
del analisis; un fallo de este mecanismo no impide los avisos de R+.

La activacion se controla con automatic_quota_refresh_enabled en la politica
privada. Desactivarlo no detiene el detector, el correo, las alarmas ni el
antipanico. Las reglas y excepciones anteriores se conservan. No se cambia
modelo, autenticacion, cortafuegos, certificados ni servicios de las webs.

Se verificaron 52 pruebas en Linux: consulta cerrada de cuota, renovaciones,
reintentos, presupuesto, reloj, rechazo de confirmaciones viejas, conservacion
de intentos e historial, migracion acreditada y compatibilidad con detector y
correo diario. La prueba real uso la unidad aislada sin llamar al modelo.
Las pruebas de tuberias del protocolo requieren Linux; el selector de Windows
no admite esas tuberias. Las pruebas puras de fechas y decisiones tambien se
comprobaron localmente. Evidencias y copias permanecen fuera del repositorio.

Referencia: [OpenAI Docs: App Server](https://learn.chatgpt.com/docs/app-server),
account/rateLimits/read. Las cuotas son compartidas por la cuenta; una fecha de
renovacion no sustituye la confirmacion de disponibilidad comunicada por OpenAI.
