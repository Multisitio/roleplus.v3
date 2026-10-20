# Tramo 31: automatismos heredados y coordinacion

Fecha: 20 de octubre de 2026.

Se inventarian cron de administrador y getmail, cron del sistema, unidades y
temporizadores, tareas internas de ISPConfig, panel del antipanico y controlador
SQL. Se conservan responsables nativos de certificados, correo, sesiones y
copias. El archivo antiguo de cron de AWStats con sufijo de copia no es un
segundo programador activo. No se modifican las tareas de las aplicaciones.

## Control de consultas

El servicio mysql-query-killer ejecutaba cada segundo KILL de cualquier conexion
en Creating sort index o Copying to tmp table desde un segundo. No verificaba
aplicacion, coste, autenticacion o abuso. Se detiene, deshabilita y enmascara.
El script pierde su credencial literal y toda capacidad de KILL. No se atribuye
una incidencia pasada a este mecanismo: no hay mensajes de terminacion en las
ultimas 24 horas revisadas que lo acrediten.

La observacion central, una vez por ciclo del antipanico y sin otro temporizador,
consulta solo agregados de PROCESSLIST y trabajos internos activos del panel.
No exporta INFO, parametros, nombres, IP ni claves. Tiene plazo de consulta y
de unidad. No se concede KILL a la IA ni se sustituye por otro terminador.

## Estado y mantenimiento

Las decisiones y contadores se trasladan a /var/lib/vigilante/antipanico con
modo 0700. Se migran 74 archivos contrastados conservando episodios y fechas;
no se reinician contadores artificialmente. El detector consume la instantanea
privada. El panel recibe los mismos datos mediante reemplazo atomico y conserva
su interfaz de lectura. Se archivan en texto los contadores publicos antiguos
y se retiran solo esas 74 replicas obsoletas, tras un ciclo nuevo correcto.

Una segunda instancia del script se rechaza con bloqueo propio. El responsable
de reinicios, sus unidades permitidas, espera y bloqueo comun se conservan.
Una ventana de mantenimiento valida evita contar fallos en esa web y el control
central rechaza reinicios mientras la ventana sigue vigente. Se ensaya con un
nombre inexistente, sin poner las webs reales en mantenimiento ni ejecutar
systemctl restart. La ventana de ensayo queda retirada.

Se corrige ademas el consumo prematuro del intento por episodio: un rechazo
por ocupacion, espera o mantenimiento permite reintentar en el ciclo siguiente;
un reinicio aceptado no se repite. Tres ciclos sinteticos comprueban este caso
con control, HTTP, correo y directorios sustituidos, sin acciones reales.

## Tareas programadas

Nueve entradas CLI de cron pasan por un catalogo cerrado. Se conservan horarios,
rutas, acciones, identidades de ejecucion existentes y destinos de registro.
Se conservan los directorios de trabajo de cron y del ejecutor de ISPConfig.
No se ejecutan ahora para probarlas, pues incluyen publicaciones y correo.
Los bloqueos de sincronizacion de Arena y boletin se mantienen dentro de la
unidad, de modo que sobreviven al intermediario.

Una unidad unica por tarea evita duplicados, incluido el caso de perderse la
conexion o el intermediario. Plazos de 15 o 45 minutos segun la tarea, una CPU,
1 GiB, 128 procesos, prioridad reducida y terminacion del grupo completo. No hay
reintento automatico de una tarea parcial ni comandos arbitrarios del modelo.
Se conserva cron como responsable del horario; no se anaden nueve temporizadores.

ISPConfig conserva sus dos entradas de cron, bloqueos internos y escrituras.
No se aplica un timeout global que pueda interrumpir configuracion o SQL.
Las esperas internas observadas durante mas de 15 minutos se registran y
escalan, sin eliminar marcas running ni matar procesos por su nombre.

El generador externo de AWStats pasa por una envoltura con argumentos, dominios
y destinos fijos contrastados con el panel, unidad unica y limite de 180 segundos.
La ruta se actualiza en server.config, por lo que ISPConfig refleja el mecanismo.
No se regeneran servidores virtuales ni se duplican las renovaciones TLS.

## Prueba real y limites

Ensayo sintetico real: una segunda instancia se rechaza; se mata el intermediario
y systemd termina padre e hijo al vencer el plazo. Catorce pruebas del control
de reinicios pasan; once pruebas de avisos verifican, entre otras cosas, que un
fallo de tarea no se anuncie como caida de servicio. Sintaxis Bash comprobada.

La regeneracion real de ROLEplus no termina: se corta a los 180.379 segundos.
La estadistica mensual anterior sigue identica, sin reiniciar servicios. No se
afirma que DNSLookup=0 reparase la causa. Se registra una incidencia operativa
normal para investigar esa espera; ampliar el plazo no es su solucion.

La observacion distingue fallos normales de tareas de problemas urgentes del
panel o de lectura SQL. Las estadisticas no generan una alarma nocturna de
servicio caido. El mecanismo existente conserva el diario y la deduplicacion.

Un ciclo natural publica datos identicos en privado y en el panel; once webs
responden 200. Detector y observador completan sus unidades. Los PID principales
de Apache, PHP-FPM y MariaDB permanecen iguales; temporizador antipánico activo.
Las dos incidencias de auditoria del terminador y estado legado se resuelven.

Evidencias privadas: /root/codex-vigilante/tramo-31-2026-10-20. Fuentes y catalogo
sin secretos publicados desde R2-D2; ninguna clave ni registro SQL completo en Git.
Este tramo centraliza los automatismos; no habilita reparaciones de la IA.
Quedan SQL seguro y programacion unificada, reparaciones acotadas y aceptacion.
