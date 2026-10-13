# DiagnÃƒÂ³stico continuo de RolePlus

Activado el 13 de octubre de 2026. Fuente de verdad: estos ficheros locales; en producciÃƒÂ³n se replican en private/ops/php. El pool del servidor mantiene su configuraciÃƒÂ³n especÃƒÂ­fica y se modifica ÃƒÂºnicamente la lÃƒÂ­nea auto_prepend_file y un include.

## QuÃƒÂ© se mide

- requests.jsonl: familia de ruta de una lista cerrada, mÃƒÂ©todo, estado HTTP, duraciÃƒÂ³n de ejecuciÃƒÂ³n PHP, memoria mÃƒÂ¡xima, PID y presencia de usuario en la sesiÃƒÂ³n al terminar. No contiene IP, ID de usuario, cookies, query, cuerpo, prompts ni textos de errores.
- Peticiones de menos de un segundo y sin error 5xx: muestra aleatoria de una cada 20, indicada por sample_every=20. Lentas PHP a partir de un segundo, esperas anteriores de al menos un segundo, 5xx y todas las peticiones de /ev/manuales/ia_html: todas. Los recuentos de esta muestra no son el total de peticiones. No se registra el nÃƒÂºmero de consultas SQL.
- fpm.jsonl: estado agregado del pool por socket Unix cada cinco segundos. Cola actual y mÃƒÂ¡xima, procesos, conexiones, avisos de capacidad y peticiones lentas. No obtiene ni guarda las URIs de los procesos. Los contadores se reinician al recargar FPM; start since permite reconocer esos reinicios. Un fallo de sonda se registra como probe_error, nunca como cola cero.
- slow.log: trazas de ejecuciÃƒÂ³n FPM al superar dos segundos, con funciones y ficheros para localizar esperas. No es un volcado de parÃƒÂ¡metros o credenciales.

La duraciÃƒÂ³n PHP excluye la espera previa en la cola de FPM y no equivale al tiempo completo del navegador. La cola es una medida del pool, no una atribuciÃƒÂ³n exacta a cada peticiÃƒÂ³n. Una muestra de cinco segundos puede omitir un pico breve; los mÃƒÂ¡ximos y contadores del pool proporcionan evidencia adicional.

## ProtecciÃƒÂ³n y conservaciÃƒÂ³n

Registros en private/var/observability, directorio 0700 y ficheros 0600, propietario web6. No hay endpoint HTTP de estado: la sonda usa /run/php/roleplus-metrics.sock. El servicio corre como web6, con permisos restringidos, sin acceso de red IP.

RotaciÃƒÂ³n diaria, siete archivos comprimidos y maxsize de 10 MB evaluado cuando se ejecuta logrotate. No es un lÃƒÂ­mite instantÃƒÂ¡neo de tamaÃƒÂ±o. copytruncate puede perder algunas lÃƒÂ­neas durante la rotaciÃƒÂ³n; estos registros de rendimiento no son una auditorÃƒÂ­a transaccional.

El wrapper PHP ejecuta el WAF global existente sin modificarlo. El pool sigue con diez procesos y conserva sus tiempos, memoria y autenticaciÃƒÂ³n. En la activacion inicial no se cambio Apache. Para activarlo se aplicÃƒÂ³ recarga gradual de PHP-FPM, conservando el servicio y comprobando HTTP durante y despuÃƒÂ©s.

## OperaciÃƒÂ³n en producciÃƒÂ³n

Instalar una vez tras replicar los fuentes:

    python3 /var/www/clients/client1/web6/private/ops/php/install-roleplus-observability.py

El instalador guarda una copia privada del pool y rechaza archivos o instalaciones previas inesperadas. Valida PHP-FPM, logrotate y systemd antes de activar. No se debe volver a ejecutarlo para actualizar sin revisar el estado existente.

Estado y sonda:

    systemctl status roleplus-fpm-metrics.service
    runuser -u web6 -- python3 /var/www/clients/client1/web6/private/ops/php/sample-roleplus-fpm.py --once

Retirar el diagnÃƒÂ³stico, conservando los registros:

    python3 /var/www/clients/client1/web6/private/ops/php/install-roleplus-observability.py --remove

La retirada restaura exactamente el pool original si no hay otras modificaciones. Si existen cambios posteriores, elimina ÃƒÂºnicamente el include propio y restaura la lÃƒÂ­nea de prepend, preservando los demÃƒÂ¡s cambios. Requiere otra recarga gradual de PHP-FPM.

## Comprobaciones realizadas

Prueba local y CLI de producciÃƒÂ³n: clasificaciÃƒÂ³n cerrada de rutas, anonimizaciÃƒÂ³n, muestreo y selecciÃƒÂ³n de lentas/errores. Sintaxis PHP y Python, configuraciÃƒÂ³n de FPM, unidad systemd y rotaciÃƒÂ³n validadas. Ficheros replicados comparados byte a byte.

Registros reales verificados para perfiles e identificaciÃƒÂ³n; sonda como web6 devolviÃƒÂ³ JSON vÃƒÂ¡lido y el servicio quedÃƒÂ³ activo. El estado interno solicitado por HTTP devuelve 404, el WAF devuelve 403 a su agente de prueba por un socket privado, AJAX de IA sin sesiÃƒÂ³n mantiene 401, y login y portada de RolePlus y portada de MTGsearch respondieron 200.

La primera activaciÃƒÂ³n mostrÃƒÂ³ nueve trazas durante el arranque, detenidas en el getLoader de Composer. Coinciden con el calentamiento tras la recarga: no se atribuyen al scraping ni se presentan como causa de la lentitud habitual. Tras la activaciÃƒÂ³n final la sonda observada devolviÃƒÂ³ cola cero y ninguna peticiÃƒÂ³n lenta desde esa recarga. Se conserva la evidencia para contrastarla con futuros picos reales.

## Tramo anterior a PHP (13 de octubre de 2026)

El include propio roleplus-upstream.conf de los dos vhosts RolePlus incorpora
RequestHeader unset/set X-RolePlus-Request-Start "%t". Apache sustituye el
valor del cliente y genera su instante de recepcion, t= en microsegundos.
Referencia oficial: https://httpd.apache.org/docs/2.4/mod/mod_headers.html

pre_php_ms mide desde esa recepcion hasta REQUEST_TIME_FLOAT de PHP;
server_to_php_end_ms suma el tramo anterior y php_ms. Incluye procesamiento
de Apache, transporte/lectura de peticion hacia FPM y espera antes de PHP.
No se debe llamar a todo ese tramo cola de FPM, ni incluye DNS, conexion/TLS
previa a la recepcion HTTP, transferencia final de respuesta al navegador o
renderizado. El sello no se devuelve como cabecera de respuesta ni se guarda
sin transformar; no se aÃ±aden identificadores de correlacion.

Un sello ausente, duplicado, futuro o con mas de 600 segundos de diferencia
produce null en ambas medidas adicionales. No se inventa un cero. Ese
plazo supera los timeouts vigentes de esta peticion. Un sello valido de cero
milisegundos si se conserva. La instrumentacion sigue sin modificar la
respuesta, filtros, autenticacion, consulta a Google o limites del pool.

Se distingue /registrados/youtube como familia cerrada: la traza nueva de
23:23 CEST aparece en DateTime::__construct durante su vista, y el registro
anterior quedaba bajo /registrados. Esa traza no demuestra por si sola que
el constructor sea la causa, ni que exista una cola general del servidor.

El timer de sesiones sigue en simulacion y aun no ha llegado a la primera
ejecucion programada. No se intenta iniciar de nuevo el servicio cuyo
arranque manual rechazo la revision automatica.

Validacion publicada: sello falso t=1 enviado por el cliente a IA sin sesion
sustituido por Apache; HTTP 401 sin llamar a Google. Registro real:
pre_php_ms=1.814, php_ms=6.864, server_to_php_end_ms=8.678,
sample_every=1. Esta prueba verifica la medicion, no la latencia de Google.
Portada y login RolePlus y portada MTGsearch respondieron 200. Configtest
Apache aprovado; activacion con graceful, sin reiniciar PHP-FPM. Los
fuentes publicados y el include efectivo se compararon byte a byte.
La prueba CLI usa php -n: la configuracion CLI normal tiene deshabilitada
http_response_code y no equivale a la configuracion FPM. Ante el primer
fallo CLI se revirtio antes de recargar Apache. La prueba HTTP real si
comprueba los valores recibidos y registrados por el PHP-FPM activo.
