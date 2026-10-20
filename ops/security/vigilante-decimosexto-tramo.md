# Tramo 16: alarmas urgentes por ROLEplus

Activado el 20 de octubre de 2026. El operador confirmo recepcion y sonido
desde el servicio aislado. Coste adicional: 0 EUR. No se instala nada en el
movil. Se conserva WhatsApp vinculado, pero su chat propio no sirve de alarma
validada. SMS queda descartado, incluidas las pruebas antes pendientes.

## Recorrido y acceso

El detector ya existente publica la bandeja y solicita vigilante-notify.service
sin esperar su resultado, antes de cualquier analisis de IA. No se agrega otro
temporizador ni otro reiniciador. El coordinador tiene su propio bloqueo y
funciona sin red, sin acceso a las claves de la aplicacion y sin cuota de IA.
Los textos salen de categorias cerradas y dominios del catalogo; no hay
instrucciones, fragmentos de registros ni texto del modelo en las alarmas.

Un proceso PHP separado, ejecutado por systemd como el usuario de ROLEplus,
reutiliza Config y Minishlink. No ejecuta codigo PHP de la aplicacion como
administrador. Tiene el sistema de archivos de solo lectura, ninguna capacidad,
sin acceso a los estados privados, tiempo y memoria limitados y red limitada a
las familias necesarias. Solo admite los destinos FCM HTTPS guardados para el
Android del operador privado configurado. No puede recurrir a la difusion a
todos los usuarios. Los fallos de transporte no borran suscripciones.

Las claves VAPID se conservan identicas, dentro de keys.php de cada entorno;
webpush.php las lee mediante Config. No se copian claves entre local y
produccion. Las copias de seguridad se guardan fuera de las raices publicas.

La copia privada de suscripciones pertenece al administrador y caduca a los
siete dias. Se renueva, como maximo, cada hora. Permite intentar avisar si
falla MariaDB, Apache o PHP-FPM; el envio no depende de una peticion web.
No permite avisar si se apaga el VPS, pierde Internet o falla el proveedor
de notificaciones. Abrir el detalle en R+ si requiere que la web funcione.

## Duplicados y estados

Solo se envian categorias urgentes observadas en los ultimos 180 segundos.
Se agrupan hasta cinco referencias por aviso. Maximo tres envios por quince
minutos y seis por hora; hasta tres intentos por episodio y cinco minutos
entre intentos. Una aceptacion registrada detiene los reintentos. Un cierre
durante un envio puede dejar su entrega incierta; no se promete entrega unica.
Diez minutos sin observar la causa permiten abrir otro episodio. La ausencia
de nuevas observaciones no se anuncia como una reparacion comprobada.

La bandeja conserva observaciones; push-delivery.json conserva intentos y
aceptaciones por episodio, por separado. HTTP 201 no confirma entrega al movil.
No se envian las observaciones antiguas que ya no estan activas.

## Enlace de la notificacion

El enlace abre /registrados/vigilante/aviso/ con la referencia completa; al
agrupar causas, abre la primera y muestra enlaces a las demas observaciones.
El detalle requiere sesion y el identificador exacto del operador configurado;
no basta con ser administrador ni con conocer el enlace. No se incluyen
identificadores de acceso ni secretos en la URL. La respuesta no se almacena
en cache y no es indexable. La prueba abre la referencia especial prueba.

El coordinador publica una proyeccion de campos cerrados en la carpeta privada
temp/vigilante de ROLEplus, compatible con open_basedir sin modificarlo.
La carpeta pertenece a administrador, grupo lector de PHP y modo 2750; el
archivo tiene modo 0640. No se publican la bandeja privada, claves VAPID,
destinos FCM ni registros completos. La web solo puede leer esta proyeccion.

La ficha reutiliza el marco y la sesion existentes. Muestra categoria, dominio,
horas en Madrid, recuento, codigo HTTP si existe, estado observado y aceptacion
del envio. Advierte si la ultima lectura supera tres minutos. No afirma
acciones ni reparaciones que no esten acreditadas. El boton Actualizar
informacion vuelve a leer la ficha; no hay peticiones periodicas nuevas.

## Verificacion y vuelta atras

Se comprobaron seleccion, deduplicacion, antiguedad, limites, reaparicion,
contrato de observaciones, aislamiento de los campos proyectados, ejecucion
PHP sin privilegios y uso de copia privada con averia SQL simulada. La prueba
SQL no paro MariaDB. Pruebas de autorizacion con identificadores sinteticos
rechazan otro usuario, falta de sesion y referencias no validas.

La prueba real del servicio fue aceptada para un Android y el operador confirmo
sonido. Se comprobo en el navegador el detalle publicado de una observacion
real y el ciclo normal sin avisos historicos. No se reinicio ningun servicio
web ni se modifico el programa antipánico. Su huella se conserva en evidencia
privada junto con las fuentes anteriores, resultados y auditoria de activacion.

Para suspender avisos, administrador puede poner enabled=false en
/etc/vigilante/roleplus-push.json. El detector y el antipánico siguen operando;
no hay que detenerlos. La instalacion inicial empieza desactivada. El resumen
diario a las 09:00 y las reparaciones automaticas no se habilitan con este tramo.
