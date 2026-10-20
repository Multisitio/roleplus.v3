# Tramo 28: barrera de mantenimiento previa a las copias SQL

Fecha: 20 de octubre de 2026. La unificacion SQL sigue pendiente.

## Condicion del operador

Antes de detener escrituras debe aparecer un cartel de mantenimiento, modo
de solo lectura y tiempo estimado. Se incorpora esta condicion a AGENTS.md
y a la cola operativa. No se interpreta como permiso para efectuar pruebas
de bloqueo prolongado en horario de uso.

Algunas consultas GET escriben accesos o renuevan sesiones. Por ello la
barrera responde antes de PHP a todas las peticiones dinamicas de la web
afectada, tambien GET, y a todos los metodos de escritura. El contenido ya
abierto permanece legible y los archivos estaticos existentes admitidos
siguen disponibles. La navegacion dinamica y los guardados se reanudan al
terminar; no se promete navegacion completa durante el bloqueo SQL.

## Instalacion comprobada

Aviso inactivo instalado en Controlate, MTGsearch, ROLEplus, Mansion, Rola,
Multisitio y Delixio. Cada correspondencia se contrasta con el dominio,
DocumentRoot, ErrorLog y usuario del servidor virtual y con ISPConfig. Los
includes quedan registrados en apache_directives del panel, sin regenerar
servidores virtuales ni certificados. Los CGI usan los directorios y usuarios
de cada web; el programa y los estilos compartidos pertenecen a root.

Un lector compartido de Apache consulta el estado publico sin SQL ni PHP.
Solo root publica ventanas. Incluyen identidad de arranque y plazo monotono,
de modo que caducan aunque el controlador muera o el reloj civil cambie.
No se recarga Apache al abrir o cerrar cada ventana. El instalador usa el
control central de recargas, su bloqueo comun con el antipanico y auditoria;
no se crea otro reiniciador ni se usa indebidamente el origen TLS.

El HTML sigue article/header/section.contenido/footer, con la clase vertical
existente para su envoltorio. CSS y JavaScript externos, sin listas ni estilos
en linea. Vista movil de 390px comprobada sin desbordamiento horizontal y con
parrafos separados. El aviso muestra estimacion, revalida el estado y vuelve
a cargar la pagina al terminar. Las peticiones de escritura reciben 503 y
un error JSON que informa del mantenimiento y la estimacion; no reciben HTML
generado como supuesto resultado ni un 200 de guardado.

## Ensayos y retorno

Apache aislado se ejecuta como www-data, en red privada y sin webs, SQL,
copias ni credenciales. Comprueba GET/POST, acceso directo a index.php,
imagenes inexistentes, consulta status=1 en una ruta de aplicacion, respuestas
JSON, lectura estatica, otros nombres de host y recuperacion tras caducar sin
recarga. El escritor sintetico no recibe ninguna peticion bloqueada y vuelve
a escribir al finalizar. El 200 de estado solo corresponde al punto reservado.

En el ensayo inicial se detectan la limpieza del entorno de RewriteMap y
la prohibicion de ejecucion en /run; ambos corregidos. El mapa compartido
requiere herencia explicita en los servidores virtuales. La primera
instalacion no supera su consulta de disponibilidad inmediata; el retorno
detecta una comparacion Python sensible a mayusculas frente a HEX de MariaDB.
Se concilia mediante las copias exactas del plan, se verifica la vuelta de
ambas portadas y se corrige el retorno. La instalacion posterior verifica
los siete puntos inactivos. No se atribuye aquella primera respuesta a una
averia de suEXEC sin evidencia. Se conservan los resultados fallidos.

Prueba real adicional en nombres ficticios aleatorios dentro de los alias
de ROLEplus y MTGsearch: aviso HTML 503, tiempo estimado y POST JSON 503.
Las portadas canonicas responden 200 durante la prueba. Se retiran ambas
ventanas y se comprueba el estado inactivo. No se bloquea ninguna base ni
se pone en mantenimiento un nombre usado por usuarios.

Las 14 pruebas del control central pasan con ejecutor simulado, sin invocar
reinicios reales. El SQL textual incorpora compresion por bloques despues
de liberar los bloqueos, verificacion SHA-256 y limites de tamano. Sus dos
pruebas pasan tambien como usuario sin privilegios en Linux. Los parametros
opcionales de plazo conservan sus valores predeterminados para los ejecutores
existentes; no se han aumentado sus pausas de produccion.

## Lo que falta de este paso

Conectar aviso, comprobacion HTTP previa y retirada al ejecutor SQL; ajustar
su plazo para liberar los bloqueos antes de caducar el aviso; obtener y
restaurar las dos copias grandes nuevas; transferir la programacion de las
bases a un solo responsable y verificar que no actuan programadores antiguos.
La politica SQL nativa y el temporizador de la base independiente siguen
sin cambios. No se declara terminado el SQL automatico ni la implementacion.

Evidencias privadas: /root/codex-vigilante/tramo-28-2026-10-20 y metadatos
replicados en private/temp/codex-vigilante/tramo-28-2026-10-20, fuera de Git.
