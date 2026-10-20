# Tramo 29: mantenimiento sobre el formulario abierto

Fecha: 20 de octubre de 2026.

La correccion del operador exige avisar sin refrescar y poder continuar con
solo esperar. Se registra en AGENTS.md. El aviso anterior de llegada no
resolvia este caso: sustituir una pagina al navegar podia perder un formulario
abierto. El nuevo cliente mantiene el documento y sus controles originales.

## Comportamiento

Un dialogo nativo muestra mantenimiento, modo de solo lectura y estimacion.
Impide la interaccion con el fondo mientras dura la ventana. No clona el
formulario, no serializa o borra datos, no cambia archivos seleccionados y
no recarga ni reenvia al terminar. Retira el dialogo y recupera el foco y,
cuando corresponde, la seleccion del campo. Conserva otras capas abiertas.

El estado lo sirve directamente Apache como JSON, sin pasar por PHP, SQL
o la sesion. El cliente omite cookies, consulta cada cuatro segundos mientras
la pagina es visible y cada segundo durante el aviso. Interrumpe consultas
en segundo plano y limita cada espera a 2.5 segundos. No usa estas lecturas
para conservar la sesion. El plazo local usa performance.now y la fecha HTTP
del servidor, evitando depender del reloj del movil.

activate anuncia por defecto ocho segundos antes de activar la barrera de
peticiones dinamicas. El futuro ejecutor SQL debe esperar a comprobar la
barrera HTTP y ajustar su plazo a la caducidad del aviso antes de tomar READ;
el anuncio por si solo no acredita que ya se pueda capturar la base.

La pagina de llegada anterior tambien pierde su recarga automatica: al
finalizar ofrece Continuar. Los documentos abiertos antes de instalar este
cliente no pueden recibir retroactivamente un script que no cargaron; lo
incorporan al navegar normalmente. Los documentos que ya llevan esta version
no necesitan recargarse durante ni despues del mantenimiento.

## Fuentes y despliegue

Cliente y CSS canonicos en ops/security, replicados de forma identica en los
activos estaticos de las siete webs. Carga externa versionada en 54 plantillas
locales; 50 tienen correspondencia en produccion. Las cuatro plantillas
locales antiguas ausentes no se instalan en el VPS. No se introducen estilos,
scripts ejecutables ni manejadores en linea.

Cada repositorio recibe su commit y push. En editor.phtml de ROLEplus se
publica solo la linea propia; se conservan los cambios previos sin commit.
En produccion, el instalador contrasta la huella completa previa y aplica
exclusivamente esa adicion, manteniendo todos los demas bytes y permisos.
No se sobrescriben plantillas enteras ni configuraciones de entorno.

El instalador prepara primero los activos y el Alias, recarga Apache por el
control central, verifica el estado estatico y solo entonces activa la carga
en las plantillas. Guarda textos previos privados y puede retirar sus propios
cambios. ISPConfig sigue reflejando los includes existentes; no se regeneran
servidores virtuales ni certificados.

## Verificacion

Apache aislado como www-data comprueba el JSON estatico sin Set-Cookie, la
antelacion respecto de la barrera, respuestas 503 y recuperacion sin recarga.
El resultado conserva las otras comprobaciones de rutas y plazo del tramo 28.

En navegador se abre un formulario, se escribe texto y se selecciona un
archivo sintetico. El aviso aparece y se retira con el mismo documento, los
mismos nodos y el mismo FileList; casillas y texto conservados. Se guarda una
sola vez mediante clic posterior, sin envio automatico. Se comprueba la vista
movil y los estilos de tema; CSS y JavaScript son externos.

Las siete portadas publicas incluyen el cliente. El estado estatico responde
sin cookies y con no-store; servicios activos y sin bloqueo SQL real. La
programacion nativa SQL no cambia: siguen pendientes su unificacion y las
copias nuevas consistentes de las dos bases grandes. Este cambio termina la
correccion del aviso, no la implementacion completa del vigilante.

Evidencias privadas en /root/codex-vigilante/tramo-29-2026-10-20 y metadatos
locales en private/temp/codex-vigilante/tramo-29-2026-10-20, fuera de Git.
