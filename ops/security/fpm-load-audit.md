# Investigación de saturación de PHP-FPM en RolePlus

Fecha: 13 de octubre de 2026, Europe/Madrid. Fuente: registros y configuración de producción. No se modificaron bases de datos, reglas de bloqueo ni el número de procesos.

## Hallazgo principal

Hay ráfagas de scraping que recorren enlaces privados y generan tráfico PHP y redirecciones. La saturación del pool existía antes de retirar la cuota por IP el 9 de octubre. Los registros históricos no contienen tiempos de petición ni trazas actuales de ejecución, por lo que no permiten identificar retrospectivamente en qué función estaba esperando cada worker ni medir cuánto duró la cola.

## Picos concretos

- **12 de octubre, 23:52:** 968 peticiones en el minuto. Una sola IP con User-Agent Firefox/47.0 hizo 894 GET, sin Referer, sobre 441 rutas distintas; 442 peticiones eran a rutas privadas. Sus respuestas fueron 592 HTTP 302, 230 HTTP 200, 66 HTTP 403 y otras seis respuestas. Es un patrón de automatización; el User-Agent no acredita identidad ni navegador real. El aviso `max_children=10` aparece a las 23:52:17. El pico del minuto fue de 46 peticiones en un segundo. Esa IP no estaba en `bot_blocker` al revisarla.
- **7 de octubre, 05:43:** antes de retirar la cuota, una IP con User-Agent Chrome/131.0.0.0 hizo 1822 GET en un minuto, sobre 270 rutas distintas. Hubo 878 HTTP 429, 533 HTTP 301, 281 HTTP 302 y 118 HTTP 200, además de otros errores. Coincide con otro aviso de límite del pool; el pico global fue de 172 peticiones en un segundo. La cuota rechazaba tráfico, pero no evitaba toda saturación.
- **13 de octubre, 15:53:** 340 peticiones desde 138 direcciones observadas, 336 GET y tres POST. Pico de 43 peticiones en el segundo 59, cuando aparece el aviso de PHP-FPM. Hubo 177 HTTP 302, 148 HTTP 200 y siete HTTP 500, además de otros estados. En la ventana 15:52–15:55 fueron 1332 peticiones: no deben interpretarse como una tasa uniforme de carga.
- **12 de octubre, 11:13:** 65 peticiones en el segundo 02, coincidiendo con otro aviso de límite. A las 12:16:46 hubo otro aviso, con un pico de 41 peticiones en ese segundo.

Estas coincidencias y los patrones de scraping identifican una fuente de presión local. No prueban por sí solas que explique la latencia de una petición concreta de IA.

## Evolución diaria

| Día completo | Peticiones HTTP | Avisos max_children del pool web6 |
| --- | ---: | ---: |
| 2026-10-05 | 292207 | 0 |
| 2026-10-06 | 251973 | 0 |
| 2026-10-07 | 284879 | 4 |
| 2026-10-08 | 467575 | 1 |
| 2026-10-09 | 531108 | 6 |
| 2026-10-10 | 351623 | 4 |
| 2026-10-11 | 273261 | 1 |
| 2026-10-12 | 228187 | 4 |

Los avisos no son una medida de duración de saturación ni un contador de todas las veces que estuvieron ocupados diez procesos. El día 13 está incompleto y se excluye de esta comparación. El volumen total baja después del 9; no hay evidencia de un aumento general causado por la retirada de la cuota.

## Qué hacen esas rutas

`UsuariosController::formularios()` redirige a `/usuarios/identificarse`. `RegistradosController::initialize()` envía a `/usuarios/formularios` las peticiones no AJAX sin sesión. Así, un enlace privado puede provocar dos redirecciones y una carga del formulario de login. Los enlaces de experiencia, notificaciones y comentarios están publicados con `rel=nofollow`, pero esa indicación no impide que un scraper los solicite. Predominan GET, no POST de credenciales: no presentar estos volúmenes como intentos de autenticación.

El WAF comprueba nombres de bots y la query, pero un scraper que se presenta como Firefox/Chrome no coincide con esa lista. El bloqueo kernel es por IP y la IP del pico del día 12 no estaba en él. `mod_evasive` está cargado; su configuración declara 600 peticiones por sitio/IP en un segundo y 150 a una página en un segundo, valores superiores a las ráfagas observadas de scraping distribuido entre muchas rutas. Apache supera la comprobación de sintaxis. No se han cambiado esas reglas.

## Medición temporal en producción

Se habilitaron durante una muestra de unos 40 segundos el estado de FPM por socket Unix local, un access log sin IP/query/cuerpo y slowlog a partir de dos segundos. Se aplicó una recarga gradual de PHP-FPM para activar y retirar la medición. La muestra es posterior a esa recarga y no representa el estado histórico anterior.

- 20 muestras de estado: cola máxima 0, procesos activos máximos 1.
- 173 peticiones PHP medidas: mediana 23,575 ms, percentil 95 88,308 ms, máximo 524,439 ms.
- Slowlog vacío: no se capturó ninguna petición de más de dos segundos.
- El front controller aparece como `/index.php` en el access log de FPM; no atribuir esas duraciones a una acción individual sin instrumentación adicional de ruta.
- Se confirmó que el socket web6 lo atiende PHP 8.4, aunque conserve `php8.1-fpm` en su nombre.
- Se restauró byte a byte el archivo del pool y se retiró el include temporal. `pm.max_children` sigue en 10. Los registros de diagnóstico permanecen privados en `/var/log/php8.4-fpm/roleplus-diagnostic-*.log` como evidencia.

## Conclusión y siguiente ajuste justificado

Existe scraping con ráfagas capaces de competir por el pool compartido y amplificación mediante redirecciones al login. La aplicación responde rápido en la muestra actual; no se ha observado una cola sostenida en ella. Los HTTP 503 recibidos dentro de cURL desde Google son un fallo distinto y no pueden atribuirse a una espera anterior en la cola PHP.

La mitigación a evaluar es frenar las ráfagas sobre rutas privadas antes de ocupar PHP y reducir redirecciones innecesarias al login, preservando usuarios autenticados, assets y miniaturas dinámicas. No restaurar indiscriminadamente la cuota global que bloqueaba navegación legítima, ni aumentar procesos sin una medición de memoria y concurrencia. Para explicar un episodio futuro de espera local hace falta capturar cola y trazas durante ese episodio; los registros antiguos no contienen esa evidencia.
