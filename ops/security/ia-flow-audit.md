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

## Contraste independiente tras la corrección del usuario

Se ejecutaron simultáneamente dos peticiones REST con la misma clave de
producción, endpoint `gemini-flash-lite-latest`, cuerpo JSON y User-Agent:
una desde el equipo del usuario mediante urllib y otra desde el servidor
mediante PHP/cURL CLI, fuera de Apache, PHP-FPM y el WAF.

| Ruta | Tiempo hasta finalizar | Respuesta de la API |
| --- | --- | --- |
| Equipo del usuario, llamada directa | 3,125 s | HTTP 503, UNAVAILABLE |
| Servidor, llamada directa | 1,744 s | HTTP 503, UNAVAILABLE |

Esto acredita que ese fallo concreto ocurre también fuera de los filtros del
servidor. No reproduce una generación correcta de 50 s ni determina por sí
solo cuándo o por qué comenzó la regresión de latencia. La atribución histórica
al proveedor queda sin confirmar; no presentar el tiempo de cURL aislado como
prueba de esa causa.

La cronología revisada sitúa `zzz-antihang.conf` en noviembre de 2025,
`antiddos.conf` en abril de 2026, el bloqueador de bots en septiembre de 2026
y el último cambio del WAF PHP el 9 de octubre de 2026 (retirada de la cuota por
IP que afectaba a la navegación normal). Las fechas de archivo no sustituyen
una comparación de comportamiento antes y después.

El aviso del editor queda como «Petición enviada. Espera la respuesta.».
Se comprobó en el formulario publicado `disabled=true` y `aria-busy=true`
durante la espera; al finalizar, `disabled=false` y el atributo busy retirado.

## Prueba autorizada de Gemini 3.8 Flash (2026-10-13)

Por peticion expresa se cambia el endpoint a `gemini-3.8-flash`,
conservando los parametros de generacion y los limites existentes.
La prueba desde el editor con el mismo prompt y contenido (3594 bytes,
12 paginas de contexto) produjo dos respuestas HTTP 503 UNAVAILABLE:
918 ms y 650 ms de llamada API, solicitud `b86f87321755`.
No hubo generacion correcta para comparar la latencia. El boton quedo
reactivado al finalizar. El modelo permanece configurado para la prueba.

## Corte de salida confirmado (2026-10-13)

La captura de Resolucion de viajes muestra HTML dentro del toast ERROR.
El registro de esa peticion (`d6e11f580242`) contiene HTTP 200 del proveedor;
el controlador rechazaba el fragmento sin cierre article y devolvia el HTML
con estado 400, que el toast renderizaba.

Reproduccion con el prompt y contenido del formulario: HTTP 200,
finishReason MAX_TOKENS, 1962 tokens de razonamiento y 81 de salida,
254 bytes de texto sin cierre article. El limite propio era 2048 tokens.
Se corrige a 8192 y thinkingLevel LOW; se concatenan las partes de texto
final (excluyendo thought), se detecta MAX_TOKENS y el error de formato
responde texto plano sin el HTML generado. El frontend escapa el error.
Pruebas locales: partes multiples, truncamiento, formato invalido,
timeout, reintento limitado y autenticacion.

Verificacion publicada: solicitud 9ae3d1db7194, mismo formulario y prompt
(3346 bytes), HTTP 200, gemini-3.8-flash, STOP, 337 tokens de salida,
11922 ms de API. HTML completo incorporado con siete entradas de cartas;
boton reactivado. Prueba temporal sin guardar cambios del manual.
