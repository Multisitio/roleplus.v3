# Instrucciones del proyecto RolePlus

- Registros del VPS (2026-10-18): resolver DocumentRoot y ErrorLog del servidor
  virtual del dominio antes de analizar incidencias. No deducir el sitio a
  partir de un numero webN ni atribuirle los registros de otra web.

- Certificados del VPS (2026-10-18): ISPConfig debe reflejar el estado real
  aunque la renovacion se ejecute por otra via. Conservar un unico responsable
  de renovacion por certificado, verificar los consumidores y no regenerar
  servidores virtuales del panel como efecto lateral de una reparacion TLS.
  Coordinar las recargas con el control central del antipanico.

- Vigilante y CLI (2026-10-18): antes de una prueba real de modelo, comprobar
  tambien los binarios auxiliares de su version en la unidad efectiva. La
  ayuda del CLI no valida herramientas. Exigir una llamada estructurada y la
  identidad real del ejecutor; un texto de exito no acredita ejecucion.
  Mantener el motor de codigo y las herramientas fuera del perfil autenticado.

- Vigilante del VPS (2026-10-18): su alcance incluye todas las webs y servicios
  del servidor, no solo ROLEplus y MTGsearch. Descubrir los sitios activos y
  conservar una ficha por sitio. Coordinar y centralizar las actuaciones con
  el automatismo antipánico existente; no crear reiniciadores independientes.
  Registrar los errores y permisos pendientes por sitio y corregir sus causas,
  sin ocultar registros ni aplicar cambios recursivos indiscriminados.

- Pie vacio del manual (2026-10-14): las ayudas de edicion no deben ocultar
  footer::before ni footer::after ni cambiar la posicion, altura o margenes
  del footer por no contener texto. Conservar imagen y numero de pagina
  tanto en pantalla como en impresion.

- Las bases de datos no comparten necesariamente fuente de verdad. Identificar
  la fuente correspondiente a cada conjunto de datos antes de modificarlo o
  sincronizarlo; la regla de copia local del cÃƒÆ’Ã‚Â³digo no determina la de los datos.

- La copia local de `X:\htdocs\roleplus.app` es la fuente de verdad. Aplicar
  primero aquÃƒÆ’Ã‚Â­ los cambios, verificarlos y solo despuÃƒÆ’Ã‚Â©s replicarlos en
  producciÃƒÆ’Ã‚Â³n.
- La excepciÃƒÆ’Ã‚Â³n son las imÃƒÆ’Ã‚Â¡genes y demÃƒÆ’Ã‚Â¡s archivos generados por usuarios, junto
  con sus referencias en la base de datos: su fuente de verdad es producciÃƒÆ’Ã‚Â³n y
  no se exige descargar ni mantener una rÃƒÆ’Ã‚Â©plica local de ese contenido.
- Al analizar imÃƒÆ’Ã‚Â¡genes, distinguir siempre entre el original guardado durante
  la subida y las miniaturas que `ImgController` puede generar al vuelo. No
  concluir que RolePlus no redimensiona basÃƒÆ’Ã‚Â¡ndose ÃƒÆ’Ã‚Âºnicamente en el uploader.
- Mantener las URLs histÃƒÆ’Ã‚Â³ricas de imÃƒÆ’Ã‚Â¡genes y el generador dinÃƒÆ’Ã‚Â¡mico de
  miniaturas mientras exista contenido antiguo que dependa de ellos.
- Todos los cambios deben incluir su propio commit y push ejecutados por el agente. No delegar esta tarea al usuario.

- Ante fallos masivos del RSS de videos, no dar por resuelta la incidencia solo
  porque algunos canales se recuperen con reintentos de segundos. Comparar
  los mismos canales desde produccion a la hora del cron y en otra franja,
  revisar el horario real de MTGsearch y verificar una carga completa tras
  el ajuste. Distinguir fallos del origen de una correccion verificada.

- La sesiÃƒÆ’Ã‚Â³n de un usuario activo debe renovarse de forma deslizante dentro de
  sus peticiones normales, con una frecuencia limitada. No aÃƒÆ’Ã‚Â±adir pings,
  polling ni peticiones keep-alive desde JavaScript para conservarla.
- Las acciones largas y AJAX no deben retener el bloqueo de la sesiÃƒÆ’Ã‚Â³n despuÃƒÆ’Ã‚Â©s
  de escribir en ella. Una sesiÃƒÆ’Ã‚Â³n ausente en AJAX debe responder como error de
  autenticaciÃƒÆ’Ã‚Â³n, nunca devolver silenciosamente un formulario con estado 200.

- Ante un fallo visual en un manual, inspeccionar el elemento real y sus estilos
  efectivos en el manual afectado antes y despues del cambio. Una prueba del
  selector de subida o de una maqueta aislada no valida el resultado del manual.
- No cambiar el encaje de las previsualizaciones del drop como sustituto de
  corregir el tamano de las imagenes en el documento.
- Respetar el destino indicado por el usuario para una imagen (por ejemplo,
  footer); si aparece guardada en otro campo, investigar el guardado y corregir
  la asignacion, sin reinterpretarla como una decision de usarla como fondo.
- En las cajas drop, mostrar la imagen completa ajustada al ancho o al alto
  disponible, conservando proporciones y sin recortes ni desbordamiento
  y sin ampliar nunca por encima del tamano original (`scale-down`). Aplicar
  la misma regla a imagenes guardadas, seleccionadas, pegadas y arrastradas.
  Este encaje de previsualizacion es independiente del manual.
- El boton de editar de cada pagina del manual debe quedar anclado a su
  esquina superior derecha y desplazarse con ella, sin heredar la posicion
  fija de los botones globales. Verificar varias paginas y el desplazamiento.
- Conservar el desplazamiento historico del boton de editar de pagina:
  `top: -15px; right: -15px`, relativo a su pagina.

- **Lectura de instrucciones tÃƒÆ’Ã‚Â©cnicas:** Antes de modificar cÃƒÆ’Ã‚Â³digo fuente, leer el `SKILL.md` asociado a su tecnologÃƒÆ’Ã‚Â­a (ej. `kumbiaphp` para MVC, `z_index_hierarchy` para capas). Si `view_file` no estÃƒÆ’Ã‚Â¡ disponible, usar directamente un lector equivalente, sin pedir permiso ni detener el trabajo por el nombre de una herramienta. No se asume el conocimiento de memoria.
- **DiagnÃƒÆ’Ã‚Â³stico de estilos:** Distinguir una variable CSS ausente de un conflicto entre reglas. Comprobar los estilos efectivos y el bundle publicado antes de atribuir la causa; usar las variables del tema existente en lugar de introducir una paleta duplicada.

- **ImpresiÃƒÆ’Ã‚Â³n CSS (Chrome Bug):** Para que Chrome imprima los colores de fondo es necesario usar `-webkit-print-color-adjust: exact`. Sin embargo, si un contenedor con imagen de fondo (`background-image`) tiene `background-color: transparent`, Chrome descartarÃƒÆ’Ã‚Â¡ el canal alfa (transparencias) de todos sus elementos hijos al imprimir en PDF. AsegÃƒÆ’Ã‚Âºrate siempre de que el contenedor padre mantenga un color de fondo sÃƒÆ’Ã‚Â³lido.
- **CachÃƒÆ’Ã‚Â© CSS:** Tras cualquier modificaciÃƒÆ’Ã‚Â³n de un archivo `.min.css`, es obligatorio incrementar la variable `$version` en `ev_controller.php` o similar, y realizar el despliegue al servidor, de lo contrario los cambios no se reflejarÃƒÆ’Ã‚Â¡n. No intentes sobrescribir CSS personalizados del usuario sin consultarlo (ej. variables como `--table-zebra-bg`).

- **Entornos (Local vs Remoto):** Los archivos .htaccess, index.php y el contenido de la carpeta config tienen configuraciones y comportamientos especÃƒÂ­ficos que difieren entre el entorno local (
oleplus.vh) y el servidor remoto de producciÃƒÂ³n. Nunca se deben sobrescribir los de producciÃƒÂ³n con los locales (ni viceversa) sin revisar cuidadosamente las implicaciones de seguridad, rutas o directivas HTTPS (como CSP).

- Las tarjetas de mamposteria deben respetar un maximo de 640px CSS y quedar
  centradas tambien con zoom al 125%. Conservar el zoom elegido por el usuario;
  cambiarlo no sustituye corregir el ancho ni verificar el CSS publicado.

- El editor de manuales no debe inyectar espacios de ancho cero (U+200B /
  &ZeroWidthSpace;) ni dejar atributos class vacios. Verificar el DOM durante
  la edicion y el HTML enviado al guardar, conservando las clases reales.

- Al validar la impresion, comprobar tambien la ultima hoja y los nodos de
  texto directamente bajo body, incluidos U+FEFF/BOM de parciales PHP. Una
  copia serializada del DOM puede estar truncada: verificar su longitud y
  cierre HTML, extraerla por bloques cuando sea necesario y preservar los
  caracteres invisibles. Contrastar el PDF con el manual publicado.


- Correccion del usuario (2026-10-06): `main` es la raiz de la plantilla del
  manual; no restaurar una clase `.plantilla` para resolver selectores antiguos.
  Adaptar los selectores a `main`, incluidas combinaciones como `main.a5`.
- Antes de recomendar mantener plantillas legacy, inventariar que estilos
  reproduce el sistema de BD y que funciones faltan, valorando cada ampliacion.
  El destino preferido para `srd20`, `for_the_quest` y `dragonbane` es una
  migracion al sistema de BD, evitando mantener dos sistemas de plantillas.

- Correccion del usuario (2026-10-07): las carpetas de fuentes CSS no deben
  contener archivos .min.css. En web/css/editor/ solo van los fuentes;
  el conjunto se compila en web/css/editor.min.css. Tras compilar y desplegar,
  comprobar que no quedan minificados individuales dentro de la carpeta.
  Antes de retirar un minificado individual, compararlo con su fuente y el
  bundle utilizado; conservar en el fuente cualquier funcionalidad necesaria
  que exista solo en ese minificado y verificar el resultado compilado.

- Correccion del usuario (2026-10-07): las ayudas visuales de elementos vacios
  no deben sustituir el fondo de la plantilla, ni en reposo, hover, seleccion
  o impresion. Una pagina nueva debe conservar su fondo aunque este vacia.

- Correccion del usuario (2026-10-07): los detalles decorativos de plantillas
  deben editarse dentro del panel del manual, sobre un fondo solido del tema.
  Cargar solo el fragmento por AJAX; no abrir popups ni cargar el layout
  completo. Guardar sin recargar el manual ni interrumpir la escritura.

- Correccion del usuario (2026-10-07): el boton para quitar una imagen debe
  recibir el clic por encima del input transparente de subida y evitar la
  activacion del label. Comprobar el elemento real bajo el centro de la X.

- Correccion del usuario (2026-10-07): al generar CSS de plantillas por CLI,
  conservar el propietario del directorio web. Verificar una reescritura
  como usuario de PHP-FPM; una compilacion correcta como root no valida el
  guardado desde el editor. No informar exito si la compilacion falla.

- Correccion del usuario (2026-10-07): el fondo de paginas pares es opcional.
  Si esta vacio o se quita, hereda el fondo de las impares. Verificar este
  comportamiento tanto en plantillas de BD habituales como en las migradas;
  no obligar a seleccionar dos veces la misma imagen.

- Correccion del usuario (2026-10-08): los nombres de fuente seleccionada en
  el panel conservan la previsualizacion personalizada, salvo la alineacion:
  siempre a la izquierda para identificar la etiqueta. Comprobar tambien
  justify-content del enlace flex, sin cambiar la alineacion del manual.

- Correccion del usuario (2026-10-08): el subrayado de H1-H6 es una linea
  bajo todo el bloque, con el grosor elegido, no text-decoration de la fuente.
  La migracion debe reflejar el borde original en el control editable y
  comprobar el CSS efectivo al cambiarlo, quitarlo y volver al valor base.

- Correccion del usuario (2026-10-08): no guardar .min.css ni .min.js
  dentro de carpetas de fuentes; usar el bundle exterior. Antes de retirar
  uno, comparar con las fuentes y comprobar referencias y codigo exclusivo.

- Tipografias del editor (2026-10-08): al activar el subrayado de bloque
  en H1-H6, inicializar el interlineado a 1.0 una sola vez y mostrarlo en
  el combo; permitir cambiarlo despues, incluso al ajustar el grosor.
  Imponer solo margin-bottom de 10px y conservar el color de la fuente.

- CSS del editor (2026-10-08): concentrar la base del manual main en
  web/css/editor/8-plantilla.css. Los otros fuentes deben contener solo
  controles/ayudas del editor, utilidades compartidas o reglas contextuales
  de impresion; no repartir entre ellos estilos por defecto del manual.

- Correccion del usuario (2026-10-08): ante espaciados residuales del
  sistema anterior, identificar y eliminar las reglas obsoletas; no taparlas
  con !important en el reset. Usar !important solo cuando sea estrictamente
  necesario. Si el usuario se ofrece a borrar reglas de BD, indicar sus
  bloques y dejar esa edicion al usuario. El subrayado conserva sus 10px
  inferiores, incluso si el titulo es ultimo hijo.

- Celdas del manual (2026-10-08): excluir td y th de los ajustes de
  margen y padding para first-child y last-child de la base del editor.
  El sangrado de las celdas debe seguir las reglas de la tabla.

- Sangrado de celdas migradas (2026-10-08): compilar siempre los valores
  de los controles horizontal y vertical, incluidos cero y el valor base;
  no depender de que sobreviva una regla del sistema anterior.

- Grupos del manual (2026-10-08): p+p, table+table y h+h son grupos
  diferentes; h+p y cualquier elemento seguido de blockquote, ol o ul
  pertenecen al mismo grupo. Distancia entre bloques, en Pagina, usa 15px
  por defecto y no agrega gap en los extremos ni entre columnas.

- Correccion del usuario (2026-10-08): h+p es un unico bloque. Su
  parrafo no debe conservar un margen superior residual ni sumar el
  margen de parrafos al gap cero. Validar margin-top efectivo y distancia
  entre cajas, no solo la variable de agrupacion.

- Gap del manual (2026-10-08): comprobar el primer y ultimo bloque
  real, incluidos padding y margenes legacy de titulos. Un gap no
  justifica espacio exterior; retirar las declaraciones antiguas que
  lo provocan sin reforzar el reset con !important.

- Retirada gradual de legacy (2026-10-08): eliminar las reglas antiguas
  poco a poco al trabajar cada parte del editor. No hacer limpiezas
  generales ni extender una retirada a otras plantillas por iniciativa
  propia. Si el usuario pide los bloques para quitarlos, identificarlos
  y dejarle esa edicion; no borrarlos automaticamente.

- Ornamentacion y gap (2026-10-08): no recomendar borrar margenes de
  caption, h5 ornamentados o sus contenedores solo por ser legacy.
  Comprobar desplazamientos y espacio reservado para el adorno; separar
  ese espacio interno del gap entre grupos. No recomendar poner todos
  los margenes verticales a cero sin revisar estas dependencias.

- Gap de parrafos (2026-10-08): usar exclusivamente --block-gap-before
  para margin-top; no sumar --p-margin-top ni emitir un override del
  compilador migrado que vuelva a agregarlo. Verificar p+p de 15px reales.

- Correccion de proceso (2026-10-08): ante una correccion puntual y
  explicita, ejecutarla en el mismo turno; no limitarse a explicar el
  cambio ni esperar a que el usuario vuelva a pedirlo. Mantener el
  alcance concreto, compilar y desplegar los artefactos necesarios,
  verificar el resultado real afectado y cerrar con commit y push.
  No ampliar pruebas, repetir comprobaciones ya satisfactorias ni
  investigar asuntos independientes sin un fallo o riesgo concreto
  que lo justifique. Una disculpa no sustituye registrar y aplicar
  la correccion de proceso.

- Gap de todos los bloques (2026-10-08): no sumar ningun margen al gap,
  ni en los fuentes CSS ni en el compilador. Eliminar esas sumas para
  todos los tipos de bloque, no corregir solo el ejemplo senalado.

- Correccion de proceso reiterada (2026-10-08): tras verificar el cambio
  puntual publicado, cerrar inmediatamente con commit y push. No seguir
  explorando ni ampliar la validacion de una correccion ya comprobada.

- Personajes (2026-10-09): escribir en una ficha nueva debe crear y guardar
  automaticamente el personaje, conservando su identificador en los guardados
  siguientes. Duplicar crea una copia y nunca es requisito para guardar.
  Confirmar el guardado real y advertir al salir con cambios pendientes.
- Salvar es Crear si no hay personaje guardado y Actualizar si ya existe su ID;
  el autoguardado debe usar Salvar. Duplicar siempre crea otra copia.

- Pegado del editor (2026-10-10): revertido el pegado HTML por la basura
  que Chrome incorpora. Mantener el pegado como texto plano; no restaurar
  la conservacion de HTML y style sin una nueva peticion del usuario.

- Edicion compartida de personajes (2026-10-12): guardar como guardian no
  cambia el propietario ni vincula automaticamente. Vincular solo toma un
  personaje libre; Desvincular permite a otros tomarlo. Sincronizar las hojas
  abiertas sin F5 y guardar solo campos modificados: el ultimo guardado gana
  en el mismo campo. Una fila activa por personaje y campo. Archivar las
  alternativas antes de consolidar duplicados y validar InnoDB en ambos
  entornos: una prueba con SQLite no acredita transacciones en produccion.

- Alineacion de pinceles (2026-10-13): por peticion expresa, left/center/right
  de Formato editan text-align en el style de la etiqueta seleccionada del
  manual. Left retira text-align y style si queda vacio; center/right
  conservan las demas propiedades. Excepcion limitada al contenido editable.

- Correccion del usuario (2026-10-13): ante lentitud de IA, diagnosticar primero las caracteristicas y cambios del servidor. Conservar `gemini-flash-lite-latest`, preferido expresamente para evitar actualizaciones manuales; una prueba mas rapida con otro modelo no acredita la causa ni autoriza sustituirlo.

- Correccion del usuario (2026-10-13): para explicar una regresion de latencia tras cambios anti-scraping, contrastar el mismo prompt, clave y endpoint entre rutas y entornos comparables. Una medicion de cURL no demuestra por si sola la causa de la regresion; revisar la cronologia y los cambios de filtros antes de atribuirla al proveedor.

- Prueba autorizada por el usuario (2026-10-13): usar `gemini-3.8-flash` en lugar de `gemini-flash-lite-latest` para comparar su rendimiento. Esta peticion expresa sustituye la preferencia anterior durante la prueba.

- Correccion del usuario (2026-10-13): ante HTML generado mostrado en un toast de error, revisar la integracion propia y finishReason antes de atribuirlo al proveedor. El presupuesto de salida incluye razonamiento; no leer solo parts[0], no incorporar MAX_TOKENS ni renderizar HTML recibido como error.

- Fin de la prueba (2026-10-13): por peticion del usuario, restaurar `gemini-flash-lite-latest` y su razonamiento predeterminado. Conservar lectura de partes, proteccion de truncamiento y presupuesto de salida corregidos.

- Diagnostico de IA (2026-10-13): no reducir maxOutputTokens como arreglo de latencia sin comprobar salida completa y tokens de razonamiento. Registrar el commit que introduce una regresion propia; e8614ea redujo 8192 a 2048 y 8b59479 lo restauro. Distinguir saturacion de PHP-FPM previa a ejecutar PHP de tiempo medido dentro de la llamada API.

- Correccion antibots (2026-10-13): no proponer bloqueos de IP como solucion estructural ni cuotas de peticiones indiferenciadas. Una pagina con muchas imagenes rotas puede generar una rafaga legitima. Diagnosticar coste y origen por ruta, errores de imagenes, autenticacion verificada y concurrencia; priorizar respuestas baratas/cacheables, eliminar trabajo y redirecciones innecesarias y aislar recursos criticos. El volumen o User-Agent aislados no acreditan abuso.

- Credenciales de pruebas (2026-10-14): generar valores sinteticos en memoria
  durante el test; no guardar contrasenas o secretos ficticios como literales
  que puedan disparar detectores. No silenciar el escaneo global ni reescribir
  el historial por una alerta sin comprobar su contenido. Distinguir las
  credenciales reales de los dobles de pruebas, y no afirmar que se ha cerrado
  un incidente externo sin verificar su estado.

- Centralizacion de credenciales (2026-10-14): todas las claves reales de la
  aplicacion deben estar en private/config/keys.php del entorno correspondiente.
  Consumirlas exclusivamente mediante Config::get() o Config::read(), nunca
  con includes directos, literales en fuentes o logs. No sincronizar keys.php
  entre local y produccion ni versionar sus valores. Los dobles de pruebas
  usan valores sinteticos efimeros sin acceder a las claves reales.

- SSH de mantenimiento (2026-10-14): ssh_cmd.py es una herramienta local.
  Lee keys.ssh del private/config/keys.php local mediante el Config del
  framework; no guardar su password en el script ni extraer constantes por
  AST para conectar. Usar read_connection(), sin imprimir su resultado.
  No copiar esa seccion SSH a produccion. Los ejemplos antiguos comentados
  tampoco pueden conservar tokens o claves reales. Retirar un literal de
  HEAD no revoca la credencial expuesta en el historial; comprobar su uso
  y coordinar su rotacion sin cortar los accesos de mantenimiento.

- Impresion en libreto (2026-10-15): conservar dos paginas A5 juntas por cada
  hoja A4 apaisada. Al cambiar la impresion normal, verificar tambien el
  libreto: dimensiones, parejas, numero de hojas y ausencia de hoja final vacia.
- Borde del libreto (2026-10-15): dos A5 suman 296mm y el A4 apaisado mide
  297mm. Cubrir el espacio residual con el fondo de la plantilla, conservando
  el tamano A5. Revisar tambien los pixeles del borde exterior del PDF.

- Fichas de personajes (2026-10-16): no usar jQuery ni reintroducirlo para
  corregir funciones antiguas. Conservar un unico manejador de desplazamiento
  nativo. Normalizar los valores remotos como los controles del navegador
  (especialmente CRLF/LF) antes de detectar cambios pendientes. Limitar la
  espera de las peticiones y liberar su estado para reintentar sin F5.
- Reutilizar Kumbia.fx y Kumbia.utils de la clase kumbia.js propia al
  corregir interacciones y peticiones; ampliar esa API cuando sea necesario,
  manteniendo sus consumidores y evitando dependencias o mecanismos paralelos.

- Personajes (2026-10-17): Enter siempre guarda. Eliminar requiere un clic
  explicito y confirmacion comprobada en servidor; conservar los datos retirados
  para recuperacion y rechazar guardados de pestanas antiguas sobre ellos.
  Salvar nunca genera ni reemplaza un idu recibido. Recuperar datos de una hoja
  abierta antes de recargarla si su copia guardada esta incompleta.
- Estado del guardado de personajes (2026-10-17): mostrar un circulo flotante
  dentro de la esquina superior derecha de Salvar: amarillo al escribir o
  guardar, verde tras confirmar el
  guardado y rojo si falla. Sin texto flotante; detalle accesible en el circulo.

- El mismo indicador de guardado muestra amarillo antes de aplicar cambios
  remotos, verde al aplicarlos y rojo si falla su recepcion o aplicacion.

- Correccion de recuperacion de personajes (2026-10-17): no mezclar una ficha
  abierta con restos de la BD suponiendo que estos son mas recientes o fiables.
  Identificar con el usuario la fuente de verdad y contrastar todos los campos
  discrepantes antes de restaurar. Conservar la copia original de la ficha antes
  de sincronizarla o recargarla; una recuperacion parcial no valida esos restos.

- Diagnostico de borrados (2026-10-17): separar la reproduccion de un riesgo de
  la causa acreditada de una incidencia. Un POST y su redireccion no identifican
  la tecla ni el boton usados; no atribuir el borrado a Enter sin esa evidencia.
  Consultar el historial antes de atribuir al usuario o al agente un orden HTML.

- Vigilante del VPS (2026-10-18): coste adicional obligatorio de 0 EUR sobre
  los servicios ya contratados y la cuota actual de OpenAI. No contratar APIs,
  mensajeria, numeros, almacenamiento ni compras de credito de pago. Para
  WhatsApp, investigar y reutilizar la vinculacion gratuita por dispositivo
  que emplea OpenClaw antes de proponer proveedores de pago. Verificar la
  recepcion y el aviso real en el movil, no solo la aceptacion del envio.

- Arranque del vigilante (2026-10-18): despues de reiniciar un conector,
  comprobar su interfaz viva y su proceso nuevo. Un status.json conservado
  del proceso anterior no acredita disponibilidad ni reconexion. Esperar
  al socket del nuevo proceso antes de probarlo, sin repetir el reinicio.

- Avisos del vigilante (2026-10-18): el usuario descarta instalar aplicaciones
  adicionales en el movil. Respetar coste adicional cero y no reiterar ntfy
  como solucion automatica. Al estudiar SMS, distinguir SMS incluidos en la
  linea de una API accesible desde el VPS y probar la notificacion real de un
  SMS propio con la pantalla bloqueada antes de montar otro conector.

- Fin de la via SMS (2026-10-18): el usuario descarta expresamente SMS porque
  lo tiene silenciado, igual que las demas redes. Detener esta via y no pedir
  mas pruebas ni vincular Mensajes de Google. No reutilizar SMS, Telegram u
  otras redes silenciadas como respuesta automatica al fallo del aviso de
  WhatsApp. Mantener coste adicional cero y ninguna aplicacion movil nueva.

- Alarmas del vigilante (2026-10-20): reutilizar las notificaciones de ROLEplus
  al Android del operador, cuya recepcion y sonido estan confirmados. Separar
  aceptacion del proveedor y entrega real. El transporte no ejecuta PHP como
  administrador, no espera al modelo ni a su cuota y no usa difusion general.
  Conservar excepciones privadas, deduplicacion y limites de reintentos.

- Maquetado del vigilante (2026-10-20): seguir la estructura real de las
  publicaciones: article, header, section.contenido con su envoltorio de texto
  y footer para las acciones. Separar datos e historial y reutilizar las clases
  existentes de espaciado. Verificar legibilidad, separacion y margenes en
  movil; que una ficha se renderice no acredita que este bien maquetada.
