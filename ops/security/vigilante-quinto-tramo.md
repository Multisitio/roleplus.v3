# Vigilante: quinto tramo

Fecha: 18 de octubre de 2026. Cuenta separada y aislamiento real comprobado
en el VPS. Las fuentes locales siguen siendo la referencia del codigo.

## Instalacion

- Cuenta de sistema vigilante-ai con contrasena bloqueada, sin grupos
  adicionales y con shell nologin. No se concede sudo ni acceso al agente.
- Espacio propio de escritura separado de la cola, diario y reglas del
  administrador. La entrada seleccionada pertenece a administrador y se
  presenta solo para lectura. Contiene un ejemplo sin registros reales.
- Raiz de archivos independiente mediante RootDirectory y montajes explicitos
  de herramientas, bibliotecas, entrada y espacio propio. Los programas de
  /usr/local y las otras carpetas del servidor no se incorporan a esa vista.
- Red privada sin familias IPv4/IPv6, capacidades vacias, prohibicion de ganar
  privilegios, filtro de llamadas, dispositivos y temporales privados.
- Limites efectivos: 256 MiB de memoria, 25% de un nucleo de CPU, 32 tareas,
  30 segundos para la comprobacion, archivos de hasta 16 MiB y sin volcados.

La unidad vigilante-isolation-check.service ejecuta exclusivamente el programa
de comprobacion. Es manual, sin temporizador ni activacion en el arranque;
queda inactiva tras terminar. No es todavia el proceso que ejecutara Codex.
No se han instalado paquetes, modificado webs ni reiniciado sus servicios.

El instalador rechaza identidades y destinos existentes para no reutilizarlos
sin revision. Si falla, retira solo las dos entradas nuevas de programa y
unidad que aun coincidan con sus fuentes, y conserva la cuenta bloqueada y
las evidencias. No elimina directorios de forma recursiva ni toca servicios web.

## Verificacion real

El programa de comprobacion supero 31 condiciones desde la unidad aislada.
Comprueba identidad, grupos, capacidades, filtro, escalada rechazada, entrada
legible y no escribible, escritura propia y rechazo de acceso a webs, claves,
copias, registros, reglas, cola y conexiones de los servicios del servidor.
No lee secretos ni escribe en recursos prohibidos: comprueba la apertura y
cierra inmediatamente si una barrera falla. No envia paquetes de red.

Un archivo de ensayo externo pertenece a la propia cuenta, por lo que el
rechazo de escritura valida el montaje de solo lectura y no solo sus permisos.
Se verifica que su contenido permanece intacto. Los espacios de nombres de
red y montajes difieren de los del instalador. La vista raiz tambien se limita
a los directorios y entradas previstos, incluidos los temporales privados.

Se comprobaron las propiedades efectivas de systemd, no solo el texto fuente:
identidad, raiz, montajes, red, capacidades, limites y resultado success.
Apache, MariaDB, PHP-FPM y el temporizador antipánico continúan activos. Las
huellas del control de reinicios, del antipánico y de su politica se conservan.
El certificado pendiente mantiene su excepcion y su prioridad en la cola.

Windows valida sintaxis de los fuentes. Las comprobaciones de aislamiento se
realizan en Linux; no se atribuye a Windows una ejecucion de estas barreras.
La copia instalada de programa y unidad coincide con los fuentes locales.

## Siguiente integracion y limites

Codex CLI y su autenticacion siguen pendientes. No se ha llamado a IA desde
el vigilante ni se han habilitado publicaciones o reparaciones autonomas.
La compatibilidad del CLI con esta raiz, sus herramientas y limites requiere
una comprobacion propia antes de introducir credenciales o permitir red.
La futura conexion exterior debe limitarse a los destinos necesarios.

Antes de analizar incidencias falta entregar entradas acotadas y depuradas de
secretos. Los resultados del usuario aislado seran datos no confiables, nunca
autorizaciones para cambiar reglas o desplegar. El control privilegiado sigue
fuera de su alcance. El alcance del catalogo sigue incluyendo las once webs.

La cuenta por si sola no da este aislamiento: el proceso debe arrancarse con
las restricciones verificadas de la unidad. La raiz separada comparte nucleo
con el VPS y bibliotecas de ejecucion; no protege frente a administrador
comprometido ni acredita una auditoria de todo el software compartido.

La separacion de cargas, red y credenciales sigue el criterio de la
[documentacion oficial de OpenAI](https://developers.openai.com/api/docs/guides/agents-api/environments/security).
Esta implementacion usa las herramientas de Linux disponibles en el propio
VPS; no crea una maquina virtual ni utiliza la API de agentes.

Evidencias privadas: scratch/vigilante/2026-10-18/tramo-5 y su replica bajo
/root/codex-vigilante/tramo-5. Las fuentes genericas estan versionadas; los
resultados detallados, identidades y configuraciones privadas no se publican.
