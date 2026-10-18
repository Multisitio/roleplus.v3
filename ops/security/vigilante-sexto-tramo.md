# Vigilante: sexto tramo

Fecha: 18 de octubre de 2026. Codex CLI instalado y comprobado sin conexion.

## Resultado

Codex CLI 0.163.0 se instala como artefacto propio de administrador, fuera de
las webs y sin modificar el PATH general ni instalar Node.js o npm. La version
queda fijada en /opt/vigilante-codex/rust-v0.163.0/codex; no se programa su
actualizacion ni se inicia un proceso permanente.

Se utiliza el archivo de la publicacion oficial openai/codex, descargado por
HTTPS. Su longitud y SHA-256 coinciden con los metadatos de la publicacion.
Se copia exclusivamente el miembro regular esperado del archivo tar, sin
extraer enlaces ni el arbol completo. Se conserva tambien la huella del
ejecutable instalado. Esto no se presenta como una firma independiente.

El instalador deriva vigilante-codex-check.service de la unidad de aislamiento
existente: conserva usuario, raiz separada, red deshabilitada, capacidades y
limites. Solo agrega montajes de lectura para el CLI y la comprobacion,
dentro de la entrada seleccionada. La unidad anterior sigue intacta.

El perfil de ensayo se crea en el espacio de trabajo, con permisos privados y
sin credenciales. Las comprobaciones fuerzan el metodo ChatGPT y eliminan las
variables de claves API del entorno. No se copian sesiones del PC al VPS.

El programa reutiliza la comprobacion de aislamiento antes de ejecutar Codex.
Se admite el alias de compatibilidad /bin solo si es exactamente el enlace
relativo usr/bin y resuelve al mismo directorio de herramientas permitido.
La vista de archivos y el resto de barreras siguen comprobados.

## Verificacion

- 32 comprobaciones de aislamiento superadas en Linux, incluida la del alias.
- Seis ordenes del CLI verificadas: version, ayuda general, ayuda de login,
  estado de login, ayuda de exec y ayuda de sandbox. El estado devuelve
  especificamente Not logged in; un codigo de salida 1 aislado no basta.
- La version ejecutada coincide con la version fijada del archivo descargado.
- La unidad queda inactiva tras terminar y es estatica, sin temporizador.
- Se conservan los limites de 256 MiB, 25% de un nucleo, 32 tareas y 30 segundos.
- Las huellas del antipánico, su politica, control y unidad de aislamiento se
  conservan. Apache, MariaDB, PHP-FPM y el temporizador antipánico siguen activos.
- Fuentes y evidencias privadas comparadas entre la copia local y el VPS.

Windows comprueba sintaxis de los fuentes; la ejecucion del CLI y el aislamiento
se prueban en Linux. No se ejecutan instrucciones de un modelo ni comandos de
usuarios durante estas comprobaciones. No se llama a IA desde el vigilante.

## Pendiente antes de trabajar con incidencias

El CLI esta instalado y comprobado sin conexion; no esta autenticado ni
analizando registros. La ayuda de sandbox no acredita que se hayan ejecutado
herramientas con el aislamiento nativo de Codex. Eso y las llamadas reales al
modelo requieren pruebas propias antes de habilitar trabajo.

El siguiente paso es preparar salida exterior limitada y autenticar con
ChatGPT. La documentacion distingue el acceso de suscripcion de la facturacion
por API y recomienda codigo de dispositivo para un servidor sin navegador.
La disponibilidad de ese flujo depende de la configuracion de la cuenta.
No se ha generado aun un codigo de acceso ni introducido credenciales.

Las credenciales que se introduzcan deberan quedar fuera de las entradas y
salidas del modelo y de las evidencias. Sigue pendiente concretar esa frontera
para las herramientas, junto con la entrega de registros depurados. Ningun
archivo encontrado ni respuesta del modelo autorizara cambios privilegiados.
Las publicaciones, reparaciones autonomas, WhatsApp, resumen y control de cuota
siguen pendientes. El certificado conserva su incidencia prioritaria.

Fuentes oficiales consultadas: [CLI](https://learn.chatgpt.com/docs/codex/cli)
y [autenticacion](https://learn.chatgpt.com/docs/auth).
Evidencias privadas: scratch/vigilante/2026-10-18/tramo-6 y su replica bajo
/root/codex-vigilante/tramo-6. No se versionan el binario, registros ni perfiles.
