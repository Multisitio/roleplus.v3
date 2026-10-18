# Undecimo tramo: reparar TLS y conciliar ISPConfig

La configuracion y los certificados efectivos del VPS, junto con la ficha de
ISPConfig, son la fuente de verdad de esta intervencion. El procedimiento se
prepara y verifica en la copia local antes de copiarlo al servidor. No se
descargan claves privadas ni se sincronizan con las claves de ROLEplus.

## Decision de mantenimiento

ISPConfig conserva la gestion del sitio y refleja SSL y Let's Encrypt
activados. La renovacion de este certificado queda en su integracion con
acme.sh, utilizando el perfil kumbia.app, que incluye kumbia.app y
www.kumbia.app. No se altera el subdominio comodin del servidor virtual ni
se pretende que este certificado cubra todos los subdominios.

El perfil de Certbot servia un certificado limitado a www.kumbia.app. Sus
enlaces recibian tambien las escrituras de dos perfiles acme.sh. Esto
constituia una competencia entre renovadores. No acredita por si solo que
un renovador concreto causase el primer cambio incorrecto de nombres.

Se conserva el certificado valido de acme.sh, comprobando nombres, vigencia
y correspondencia de la clave publica. Se sustituyen los enlaces finales
que apuntaban a Certbot por los archivos de instalacion habituales de
ISPConfig. El certificado y la clave originales del perfil permanecen en
su directorio privado. Las rutas utilizadas por Apache se conservan.

Se retira el perfil redundante www.kumbia.app de la renovacion de acme.sh
mediante su operacion remove, que conserva certificados y claves. La
configuracion de renovacion de Certbot de este dominio queda archivada en
la copia privada de seguridad. No se desinstala Certbot ni se modifica su
temporizador global. Los demas certificados conservan su gestion anterior.

Se reconcilia exclusivamente ssl_letsencrypt en la ficha existente de
ISPConfig, comprobando previamente identidad, dominio y rutas. Se registra
la reconciliacion en el diario del vigilante. No se encola una regeneracion
general de servidores virtuales ni se cambian sus parametros de PHP.

## Recarga y proteccion

El perfil activo de acme.sh instala sus archivos y llama a reload-tls del
control central. Esta accion necesita administrador, origen ispconfig-acme
y un dominio autorizado expresamente por la politica. El modelo sigue sin
acceso a este control y sin permiso para ejecutar reparaciones.

La recarga comparte el bloqueo del antipanico, exige configuracion Apache
valida y servicio activo, registra intentos y resultados y ejecuta solamente
systemctl reload apache2.service. No arranca servicios caidos ni utiliza
restart o force-reload. Los reinicios conservan su propietario y espera.

El procedimiento administrativo guarda configuraciones, enlaces y estado del
panel en una carpeta privada del VPS. Ante un fallo restaura esos elementos
y recarga el estado anterior mediante el mismo control. Nunca incluye esas
copias en Git. Para repetir una intervencion restaurada, comprueba que la
copia original sigue correspondiendo al estado actual.

## Validacion observada

Las 13 pruebas del control central han pasado, incluidas denegacion de otro
origen o dominio, configuracion invalida, bloqueo compartido, servicio caido
y ausencia de reinicios en la recarga TLS.

La primera aplicacion se revirtio automaticamente porque la comparacion de
otra web no coincidio; no se conservo su resultado posterior detallado y no
se atribuye una causa a esa diferencia. Se mejoro el procedimiento para
conservar las muestras antes y despues incluso al fallar y contrastar una
diferencia una vez, sin relajar los criterios de aceptacion.

Tras confirmar la restauracion, la segunda aplicacion supero las comprobaciones
de las once webs: las otras diez conservaron exactamente sus resultados TLS
y HTTP. kumbia.app y www.kumbia.app validan el mismo certificado correcto por
127.0.0.1 y por la direccion publica. Desde el PC, en otra conexion, ambos
dominios y ROLEplus y MTGsearch validaron TLS y respondieron HTTP 200.

Los dos nombres del certificado se comprobaron tambien mediante archivos
temporales de validacion ACME, retirados al terminar. No se solicito un
certificado nuevo ni se forzo una renovacion de produccion. La proxima
renovacion real programada todavia debe observarse; no se presenta como una
renovacion ya probada. Si ISPConfig regenera en el futuro las opciones de
instalacion del perfil, debe conservarse o restablecerse la recarga central.

Solo despues de verificar ambos entornos se cierra la incidencia TLS en la
cola y se revisa su excepcion de vigilancia. Los restantes trabajos quedan
pendientes. El informe, las copias de vuelta atras y el diario quedan en el VPS.

Referencias: [documentacion de Certbot](https://eff-certbot.readthedocs.io/en/stable/using.html)
y [control de Apache](https://httpd.apache.org/docs/2.4/programs/apachectl.html).
