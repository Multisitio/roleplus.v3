# Diagnóstico continuo de RolePlus

Activado el 13 de octubre de 2026. Fuente de verdad: estos ficheros locales; en producción se replican en private/ops/php. El pool del servidor mantiene su configuración específica y se modifica únicamente la línea auto_prepend_file y un include.

## Qué se mide

- requests.jsonl: familia de ruta de una lista cerrada, método, estado HTTP, duración de ejecución PHP, memoria máxima, PID y presencia de usuario en la sesión al terminar. No contiene IP, ID de usuario, cookies, query, cuerpo, prompts ni textos de errores.
- Peticiones de menos de un segundo y sin error 5xx: muestra aleatoria de una cada 20, indicada por sample_every=20. Lentas a partir de un segundo y 5xx: todas. Los recuentos de esta muestra no son el total de peticiones. No se registra el número de consultas SQL.
- fpm.jsonl: estado agregado del pool por socket Unix cada cinco segundos. Cola actual y máxima, procesos, conexiones, avisos de capacidad y peticiones lentas. No obtiene ni guarda las URIs de los procesos. Los contadores se reinician al recargar FPM; start since permite reconocer esos reinicios. Un fallo de sonda se registra como probe_error, nunca como cola cero.
- slow.log: trazas de ejecución FPM al superar dos segundos, con funciones y ficheros para localizar esperas. No es un volcado de parámetros o credenciales.

La duración PHP excluye la espera previa en la cola de FPM y no equivale al tiempo completo del navegador. La cola es una medida del pool, no una atribución exacta a cada petición. Una muestra de cinco segundos puede omitir un pico breve; los máximos y contadores del pool proporcionan evidencia adicional.

## Protección y conservación

Registros en private/var/observability, directorio 0700 y ficheros 0600, propietario web6. No hay endpoint HTTP de estado: la sonda usa /run/php/roleplus-metrics.sock. El servicio corre como web6, con permisos restringidos, sin acceso de red IP.

Rotación diaria, siete archivos comprimidos y maxsize de 10 MB evaluado cuando se ejecuta logrotate. No es un límite instantáneo de tamaño. copytruncate puede perder algunas líneas durante la rotación; estos registros de rendimiento no son una auditoría transaccional.

El wrapper PHP ejecuta el WAF global existente sin modificarlo. El pool sigue con diez procesos y conserva sus tiempos, memoria y autenticación. No se cambia Apache. Para activarlo se aplicó recarga gradual de PHP-FPM, conservando el servicio y comprobando HTTP durante y después.

## Operación en producción

Instalar una vez tras replicar los fuentes:

    python3 /var/www/clients/client1/web6/private/ops/php/install-roleplus-observability.py

El instalador guarda una copia privada del pool y rechaza archivos o instalaciones previas inesperadas. Valida PHP-FPM, logrotate y systemd antes de activar. No se debe volver a ejecutarlo para actualizar sin revisar el estado existente.

Estado y sonda:

    systemctl status roleplus-fpm-metrics.service
    runuser -u web6 -- python3 /var/www/clients/client1/web6/private/ops/php/sample-roleplus-fpm.py --once

Retirar el diagnóstico, conservando los registros:

    python3 /var/www/clients/client1/web6/private/ops/php/install-roleplus-observability.py --remove

La retirada restaura exactamente el pool original si no hay otras modificaciones. Si existen cambios posteriores, elimina únicamente el include propio y restaura la línea de prepend, preservando los demás cambios. Requiere otra recarga gradual de PHP-FPM.

## Comprobaciones realizadas

Prueba local y CLI de producción: clasificación cerrada de rutas, anonimización, muestreo y selección de lentas/errores. Sintaxis PHP y Python, configuración de FPM, unidad systemd y rotación validadas. Ficheros replicados comparados byte a byte.

Registros reales verificados para perfiles e identificación; sonda como web6 devolvió JSON válido y el servicio quedó activo. El estado interno solicitado por HTTP devuelve 404, el WAF devuelve 403 a su agente de prueba por un socket privado, AJAX de IA sin sesión mantiene 401, y login y portada de RolePlus y portada de MTGsearch respondieron 200.

La primera activación mostró nueve trazas durante el arranque, detenidas en el getLoader de Composer. Coinciden con el calentamiento tras la recarga: no se atribuyen al scraping ni se presentan como causa de la lentitud habitual. Tras la activación final la sonda observada devolvió cola cero y ninguna petición lenta desde esa recarga. Se conserva la evidencia para contrastarla con futuros picos reales.
