# Instrucciones del proyecto RolePlus

- Las bases de datos no comparten necesariamente fuente de verdad. Identificar
  la fuente correspondiente a cada conjunto de datos antes de modificarlo o
  sincronizarlo; la regla de copia local del código no determina la de los datos.

- La copia local de `X:\htdocs\roleplus.app` es la fuente de verdad. Aplicar
  primero aquí los cambios, verificarlos y solo después replicarlos en
  producción.
- La excepción son las imágenes y demás archivos generados por usuarios, junto
  con sus referencias en la base de datos: su fuente de verdad es producción y
  no se exige descargar ni mantener una réplica local de ese contenido.
- Al analizar imágenes, distinguir siempre entre el original guardado durante
  la subida y las miniaturas que `ImgController` puede generar al vuelo. No
  concluir que RolePlus no redimensiona basándose únicamente en el uploader.
- Mantener las URLs históricas de imágenes y el generador dinámico de
  miniaturas mientras exista contenido antiguo que dependa de ellos.
- Todos los cambios deben incluir su propio commit y push ejecutados por el agente. No delegar esta tarea al usuario.

- Ante fallos masivos del RSS de videos, no dar por resuelta la incidencia solo
  porque algunos canales se recuperen con reintentos de segundos. Comparar
  los mismos canales desde produccion a la hora del cron y en otra franja,
  revisar el horario real de MTGsearch y verificar una carga completa tras
  el ajuste. Distinguir fallos del origen de una correccion verificada.

- La sesión de un usuario activo debe renovarse de forma deslizante dentro de
  sus peticiones normales, con una frecuencia limitada. No añadir pings,
  polling ni peticiones keep-alive desde JavaScript para conservarla.
- Las acciones largas y AJAX no deben retener el bloqueo de la sesión después
  de escribir en ella. Una sesión ausente en AJAX debe responder como error de
  autenticación, nunca devolver silenciosamente un formulario con estado 200.

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

- **Lectura de instrucciones técnicas:** Antes de modificar código fuente, leer el `SKILL.md` asociado a su tecnología (ej. `kumbiaphp` para MVC, `z_index_hierarchy` para capas). Si `view_file` no está disponible, usar directamente un lector equivalente, sin pedir permiso ni detener el trabajo por el nombre de una herramienta. No se asume el conocimiento de memoria.
- **Diagnóstico de estilos:** Distinguir una variable CSS ausente de un conflicto entre reglas. Comprobar los estilos efectivos y el bundle publicado antes de atribuir la causa; usar las variables del tema existente en lugar de introducir una paleta duplicada.

- **Impresión CSS (Chrome Bug):** Para que Chrome imprima los colores de fondo es necesario usar `-webkit-print-color-adjust: exact`. Sin embargo, si un contenedor con imagen de fondo (`background-image`) tiene `background-color: transparent`, Chrome descartará el canal alfa (transparencias) de todos sus elementos hijos al imprimir en PDF. Asegúrate siempre de que el contenedor padre mantenga un color de fondo sólido.
- **Caché CSS:** Tras cualquier modificación de un archivo `.min.css`, es obligatorio incrementar la variable `$version` en `ev_controller.php` o similar, y realizar el despliegue al servidor, de lo contrario los cambios no se reflejarán. No intentes sobrescribir CSS personalizados del usuario sin consultarlo (ej. variables como `--table-zebra-bg`).

- **Entornos (Local vs Remoto):** Los archivos .htaccess, index.php y el contenido de la carpeta config tienen configuraciones y comportamientos espec�ficos que difieren entre el entorno local (oleplus.vh) y el servidor remoto de producci�n. Nunca se deben sobrescribir los de producci�n con los locales (ni viceversa) sin revisar cuidadosamente las implicaciones de seguridad, rutas o directivas HTTPS (como CSP).

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
