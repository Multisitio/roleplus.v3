# Decimoquinto tramo: instalacion y vinculacion de WhatsApp

La copia local controla el codigo y el archivo de versiones de dependencias.
La identidad vinculada, sus claves de sesion y los recibos pertenecen al VPS y
nunca se descargan al repositorio. No se emplea la API comercial de Meta ni se
contrata ningun servicio, numero o credito. Coste adicional: 0 EUR.

## Componentes

Node.js v24.22.0 se descarga de nodejs.org y se contrasta con su SHA-256
publicado. Se instala en /opt/vigilante-whatsapp/node, sin modificar paquetes
del sistema. La extraccion valida las rutas y los tres enlaces conocidos del
paquete. Una primera extraccion fue rechazada por el filtro de enlaces antes
de ejecutar Node; se corrigio la extraccion conservando el filtro para los
archivos y comprobando separadamente destinos y enlaces.

Baileys 7.0.0-rc14, qrcode 1.5.4 y pino 10.4.0 tienen versiones fijadas. Las
dependencias transitivas quedan bloqueadas con integridad en package-lock.json.
La instalacion se ejecuta sin privilegios, sin ejecutar instrucciones de
instalacion de los paquetes ni instalar automaticamente dependencias pares.
Despues, fuentes y dependencias quedan propiedad del administrador.

El servicio vigilante-whatsapp.service usa vigilante-wa, sin grupos adicionales,
capacidades ni elevacion. CPU maxima: 10% de un nucleo; memoria maxima: 320 MiB;
32 tareas como maximo. El proceso Node necesita generar codigo en memoria,
pero permanece separado del ejecutor de IA y de sus credenciales. No accede a
/var/www, /etc/vigilante ni a los perfiles y estados del vigilante con IA.

Solo escribe su estado privado /var/lib/vigilante-wa y el directorio temporal
/run/vigilante-wa. No abre un puerto HTTP. Su interfaz administrativa es un
socket Unix 0600 en un directorio 0700. Solo acepta consultar estado, solicitar
la vinculacion o enviar una prueba de texto fijo. No admite destinatarios,
cuerpos de mensaje, archivos, ordenes de sistema ni instrucciones de la IA.

## Identidad y vinculacion

El numero autorizado se conserva exclusivamente en
/etc/vigilante-wa/recipient.json, propiedad root:vigilante-wa 0640 dentro de
un directorio 0750. Es metadato privado, no una clave de la aplicacion web.
La sesion cifrada de dispositivo vive en /var/lib/vigilante-wa/session 0700:
es una identidad independiente, igual que la sesion aislada del CLI, y no se
replica a keys.php ni a otros entornos.

Se admite QR o codigo de vinculacion solicitado para ese numero. El codigo
se entrega al operador y no se almacena en los registros o la politica.
Antes de declarar conexion o enviar, se verifica que la identidad vinculada
coincide con el numero autorizado. No se elige otro destinatario por defecto.

No se cargan historiales ni se interpretan mensajes entrantes como ordenes o
peticiones a la IA. Se conserva unicamente el estado y el recibo del mensaje
de prueba. Un mensaje aceptado por la biblioteca no acredita recepcion, lectura
ni sonido en el telefono: esa comprobacion con el usuario sigue siendo necesaria.

## Administracion y limites

El control instalado es /usr/local/lib/vigilante/whatsapp-control.py, reservado
al administrador. Sus operaciones cerradas son check, status, pair-code y
send-test. Las pruebas rechazan destinatarios o textos arbitrarios, ordenes
ajenas, campos adicionales y entradas no validas; verifican tambien el usuario
real, las capacidades, la prohibicion de elevacion y los servicios previos.

La reconexion tiene espera creciente y los fallos del proceso tienen un limite
de cuatro arranques por media hora. Una sesion cerrada por WhatsApp requiere
intervencion de vinculacion. No se reinician servicios web.

Este tramo instala el transporte y permite vincular y probar la cuenta. La
bandeja automatica conserva su estado pendiente: no se habilita su vaciado ni
se envian avisos anteriores al vincular. La confirmacion de recuperaciones y
episodios nuevos, la comprobacion del aviso real en el movil y la activacion
del envio automatico deben completarse antes de declarar ese circuito operativo.

## Resultado comprobado

La vinculacion por codigo se completo con el numero autorizado por el usuario,
conservado solo en la configuracion privada. El primer codigo fue rechazado.
El segundo intento uso la identificacion estandar de navegador de la biblioteca,
su version actual del protocolo y una sesion nueva, conservando la fallida en
el VPS. Se completo la vinculacion, sin atribuir la causa del primer rechazo
a uno de esos cambios aisladamente.

El servicio esta habilitado para arrancar con el servidor. Un reinicio exclusivo
del conector recupero la sesion sin otro codigo. La verificacion consulta el
socket vivo: un archivo de estado anterior al arranque no acredita disponibilidad.
Doce comprobaciones reales de interfaz, permisos y aislamiento resultaron
correctas. La auditoria de las dependencias instaladas notifico cero
vulnerabilidades conocidas en ese momento.

Se envio una primera prueba de texto fijo al numero vinculado. El usuario
confirmo su recepcion, pero sin sonido ni vibracion; no se dedujo entrega por
el mero identificador devuelto al enviar. A peticion expresa del usuario se
envio una segunda prueba para contrastar con WhatsApp en segundo plano.
El usuario confirmo tambien la llegada de la segunda prueba, de nuevo sin
aviso. Este chat propio no queda validado como alarma nocturna. El usuario solo
dispone de un numero y no autoriza costes adicionales. No se activa el vaciado
de la bandeja para ocultar esa limitacion.

Se comprobo que markOnlineOnConnect=false hace que esta version de Baileys
anuncie presencia unavailable: no se atribuye el problema a presencia online
ni se introducen llamadas repetidas para cambiarla. CallMeBot anuncia una API
gratuita para uso personal, pero su pagina de alta indica actualmente que
esta completa; no se usan numeros antiguos publicados en otras guias ni se
da de alta un servicio de pago como sustituto.
Apache, PHP-FPM, MariaDB y el temporizador del antipanico permanecieron activos.
No se habilitaron reparaciones de IA ni envios automaticos de incidencias.

Fuentes primarias de referencia:

- https://github.com/WhiskeySockets/Baileys
- https://raw.githubusercontent.com/WhiskeySockets/Baileys/master/Example/example.ts
- https://nodejs.org/dist/v24.22.0/SHASUMS256.txt
