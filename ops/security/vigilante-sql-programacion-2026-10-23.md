# Programacion SQL unica y acotada

## Alcance

Preparada la delegacion del trabajo backup de ISPConfig en
vigilante-sql-schedule.service. El panel conserva sus politicas semanales
y sus registros de copias; no se cambian dominios ni datos de las aplicaciones.
El trabajo de ISPConfig comprueba cada quince minutos. El ejecutor solo
admite un intento semanal, el domingo entre 04:45 y 05:15 de Europe/Madrid.
El temporizador independiente anterior se deshabilita al aplicar la migracion;
su ejecutor, bloqueo, retencion y copias se conservan bajo el controlador.

Son las nueve bases revisadas: siete asociadas a web y dos independientes.
El inventario distingue dbispconfig, cuya rutina sysbackup nativa es otro
alcance y no duplica estas nueve bases. Esta rutina conserva tres copias
del panel y directorios completos; su politica de archivos es una incidencia
operativa identificada, no una copia de texto creada por este controlador.
No se retiran sus archivos ni su proteccion en esta migracion.

## Protecciones

Cada base asociada anuncia el modo de solo lectura ocho segundos antes de
impedir escrituras. La barrera incluye los alias reales, comodines y
subdominios anidados. Se contrasta el servidor virtual y se detiene si
cambian sus alias o la correspondencia del panel. No se deducen nombres
de web a partir de webN sin comprobar el catalogo.

ROLEplus admite hasta 15 segundos y MTGsearch hasta 45; las pequenas
conservan 1.5 segundos. No hay bloqueo global de MariaDB ni limites globales
nuevos. La caducidad del aviso y del cliente SQL sobreviven al coordinador.
Las ventanas comparten el bloqueo del antipanico. El estado HTTP sigue
servido sin PHP, SQL ni bloqueo de sesion. Los formularios conservan sus
nodos y valores, como en la proteccion ya instalada.

La compresion y representacion textual se realizan despues de liberar READ.
Solo se publica SQL UTF-8 verificado, con cierre del volcado y huellas.
El estado indica que las copias grandes aun no estan restauradas en
aislamiento. Un intento interrumpido queda pendiente de conciliacion y
no se repite automaticamente sobre archivos ambiguos. La retencion retira
solo versiones propias conocidas y contrastadas, hasta siete por base.

La unidad solo admite red de bucle local para comprobar Apache; es ajena
al perfil autenticado de IA, las webs, /var/lib/mysql y las credenciales.
El detector reutiliza automation_failed y las notificaciones existentes
para un fallo o una ventana perdida. Un ciclo aplazado conserva el resultado
del ultimo intento real. No llama al modelo para generar ni avisar de copias.

## Aplicacion y comprobaciones

El instalador conserva los originales de texto y valida sus huellas. Toma
el bloqueo propio de cron.php de ISPConfig antes de cambiar el propietario.
El nombre del instalador conserva cron.php para respetar el protocolo de
identificacion de PID del panel. Ante un fallo recupera los archivos,
configuracion y temporizador anteriores. No reinicia Apache, PHP ni MariaDB.

Ocho pruebas aisladas del programador, conversion textual, fallo sin publicar,
intento duplicado, pendiente tras interrupcion, alerta persistente, cambio
de horario y recuperacion de la migracion pasan. Ensayo real aislado con
Apache/MariaDB como www-data: quince comprobaciones, incluidos alias,
restauracion de ensayo, caducidad y fallo antes de READ. No hay acceso al
socket SQL ni al contenido de produccion en estas pruebas.

Pendiente aplicar y acreditar un ciclo de produccion aplazado sin volcar.
La primera captura grande y su restauracion real solo pueden verificarse
tras la ventana de madrugada. La cobertura triple con GitHub cifrado y la
aceptacion integral siguen pendientes; no se declara terminado el vigilante.

Una actualizacion de ISPConfig debe conservar/revalidar la delegacion de
500-backup.inc.php antes de liberar su cron. El controlador contrasta la
huella del propietario y rechaza otra configuracion. No reponer el volcado
nativo ilimitado sobre estas bases como recuperacion automatica.
