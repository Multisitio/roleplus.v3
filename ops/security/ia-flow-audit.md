# Auditoría del recorrido de IA — 13 de octubre de 2026

## Resultado

Se conserva `gemini-flash-lite-latest`. Una respuesta real identificó
`gemini-3.5-flash-lite` y tardó 35,959 s para «Responde solo: hola».
La protección global `zzz-antihang.conf` impone `Timeout 30`; al no existir
`ProxyTimeout` en RolePlus, Apache aplicaba esos 30 s también a PHP-FPM.
La petición del editor terminaba con un 504 mientras PHP seguía esperando.

Además, la API de Google devolvió un 503 en una prueba real del editor tras
19,968 s. No atribuir ese fallo del proveedor al firewall local, ni considerar
que ampliar la espera garantiza que Google vuelva a responder rápidamente.

## Recorrido revisado

| Fase | Archivos/configuración | Comprobación |
| --- | --- | --- |
| Botón y POST | `web/javascript/editor/9-editor.js`, bundle publicado `web/javascript/editor.min.js`, formulario de regla | Una llamada fetch con prompt, ID del manual y HTML actual; no guarda la página en BD. El botón se deshabilita hasta finalizar. |
| Red entrante | iptables/nftables, ipset `bot_blocker`, Fail2ban | La IP del navegador no está en el conjunto de bloqueo. La petición llega a Apache. No hay regla de salida que bloquee Google. |
| Defensa HTTP | `antiddos.conf`, `zzz-antihang.conf`, `evasive.conf`, `reqtimeout.conf`, vhost y `.htaccess` de producción | Límites de cabeceras/cuerpo y reglas de bots no bloquean este POST. El límite de espera al backend de 30 s sí genera el 504. |
| PHP inicial | pool `/etc/php/8.4/fpm/pool.d/web6.conf`, `/usr/share/php/global_waf.php`, `.user.ini`, `web/index.php` | WAF por UA y query; no filtra el cuerpo del prompt. PHP-FPM 8.4 usa el socket llamado `php8.1-fpm/web6.sock`: el nombre no identifica la versión activa. |
| Framework | bootstrap/router/autoload del core, `private/libs/general_controller.php`, `private/libs/ev_controller.php` | Limpieza XSS de GET/POST, identificación, actividad del usuario y datos comunes. El controlador real hereda de EvController. |
| Sesión | `private/libs/session.php`, `private/models/usuarios/trait_sesion.php` | Lecturas no reabren la sesión. Se cierra en initialize y justo antes de la llamada larga. Medición real: `session_locked=false`. |
| Permisos y contexto | `private/controllers/ev/manuales_controller.php`, `private/models/manuales.php`, `private/models/manuales_reglas.php` | Se comprueban permisos y se obtienen resúmenes de las páginas, más el contenido actual. |
| Petición a Google | `private/libs/_ia.php`, `private/libs/_curl.php`, Config para la clave propia de cada entorno | Gemini REST v1beta, alias latest, sin cambio de razonamiento. IPv4, TLS verificado, espera total de IA de 60 s. |
| Respuesta | `_ia.php` → `ia_html()` → fetch en `9-editor.js` | Se extrae el article y solo se incorpora con HTTP correcto. Un fallo devuelve texto y 503; no sustituye el contenido del formulario. |

## Mediciones en producción

Petición real `e04bb690f4bb`, con el contenido de la página afectada:

- Antes de entrar en la acción: 8 ms.
- Permisos, consultas y preparación del contexto: 7 ms.
- Contexto de 12 páginas; prompt de 3594 bytes.
- Resolución DNS: 10 ms; conexión TCP: 14 ms; conexión lista: 95 ms.
- Respuesta de Google: HTTP 503, 19 968 ms.
- Sin bloqueo de sesión ni cola en el socket PHP-FPM durante esa prueba.

También se observó en otra petición que Google había confirmado todos los
bytes enviados por TCP, sin retransmisiones. La espera ocurría después de
entregar el prompt, no en una regla DROP del servidor.

El pool alcanzó sus 10 procesos a las 15:53:59. Es un posible factor de cola
en esa franja, pero no explica la demora de las llamadas CLI ni la prueba
instrumentada; no se aumentó su capacidad sin necesidad demostrada.

## Cambios aplicados

- Include propio de los dos VirtualHost de RolePlus: `ProxyTimeout 75`,
  separado de los límites anti-DoS de las conexiones del navegador.
  Fuente: `ops/security/roleplus-upstream.conf`; instalación idempotente:
  `ops/security/install-roleplus-upstream.sh`. El instalador conserva el
  vhost específico de producción y deja una copia de recuperación.
- Se elimina el envío síncrono de correo tras fallar Gemini. Las métricas
  se escriben en el log de PHP, sin claves, prompts ni respuestas.
- Un único reintento ante 502/503/504 del proveedor, con una pausa breve
  y dentro del presupuesto original de 60 s. No se reintentan errores de
  configuración ni se prolonga un timeout completo.
- Errores claros, 503 para fallo de Gemini y 401 para sesión ausente.
- Se desactiva también el layout AJAX para esta respuesta: no añadir toasts
  ni otros fragmentos HTML al article generado ni al texto de error.
- Se conserva el máximo de salida de 2048 tokens que ya usaba producción,
  corrigiendo la discrepancia de la copia local.
- La preferencia de conservar latest queda registrada en `AGENTS.md`.

## Verificación

Sintaxis PHP local y remota, `apachectl configtest`, recarga correcta de
Apache y comparación exacta de los archivos desplegados. Pruebas del
pipeline: respuesta HTML, timeout, recuperación de 503 transitorio,
máximo de dos intentos y sesión ausente. La prueba real de error mantuvo
intacto el contenido del formulario y volvió a habilitar el botón.

La validación final `ef20199dba58` midió 16 ms antes de la acción y 14 ms de
preparación. Google respondió `503 UNAVAILABLE` en ambos intentos (7,113 s y
0,542 s). RolePlus devolvió su error 503, conservó el HTML y habilitó de nuevo
el botón.

Tras retirar el layout AJAX, la petición `61cf5185e205` terminó correctamente:
16 ms antes de la acción, 12 ms de preparación y 43,565 s en Google. HTTP 200,
`gemini-3.5-flash-lite`, article completo recibido por el formulario, sin
toasts/layout añadidos y con el botón habilitado. Superó los antiguos 30 s sin
504: el ajuste del backend está verificado con una generación real. La prueba
se hizo en un formulario temporal y no se guardó ninguna página en BD.
Sigue existiendo lentitud e indisponibilidad intermitente del proveedor;
la generación rápida no está garantizada ni se declara recuperada.

Si ISPConfig regenera el vhost, volver a ejecutar el instalador para conservar
el include de RolePlus. No copiar el vhost, `.htaccess`, `index.php` ni claves
entre local y producción.
