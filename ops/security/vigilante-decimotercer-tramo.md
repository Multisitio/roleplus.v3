# Decimotercer tramo: deteccion automatica y analisis sin escrituras en las webs

La copia local es la fuente de verdad del codigo. Politica, registros,
observaciones, cuota y diario efectivos pertenecen al VPS. La actuacion no
modifica las fuentes de verdad de las aplicaciones ni de sus datos.

## Disparador y alcance

El servicio existente webwatchdog conserva su temporizador y su control de
reinicios. Un complemento de su unidad solicita de forma asincrona el nuevo
detector al terminar el ciclo; OnFailure lo solicita tambien si falla. No se
crea otro temporizador ni otro reiniciador. El analisis no hace esperar al
ciclo siguiente del antipanico y nunca recibe capacidad para reiniciar.

El detector usa el catalogo completo, actualmente once webs. Consume el estado
reciente del vigilante en lugar de duplicar sus peticiones HTTP. Verifica
propietario, permisos, tamano, antiguedad y coincidencia del catalogo antes de
usar ese estado. Lo trata como observaciones, nunca como instrucciones.

Resuelve los ErrorLog de los servidores virtuales reales. Diez webs tienen
registro dedicado. kumbia.app hereda el registro global: se incorpora el
Main ErrorLog confirmado por Apache, sin atribuir indiscriminadamente los
mensajes compartidos a ese dominio. Se distingue esta cobertura en el estado.

La lectura incremental tiene un limite de 128 KiB por archivo y ciclo, conserva
la ultima linea incompleta y contempla rotacion y truncado. Esos cambios y los saltos por
exceso de volumen quedan registrados como posibles huecos de cobertura; no se presenta
la muestra como un recuento integro. Se descartan entradas de mas de cinco
minutos, futuras, mal formadas o excesivamente largas.

Se agrupan errores HTTP 5xx por web, familia de ruta y estado. Los mensajes PHP
se convierten en categorias cerradas: advertencia, fatal, imagen, memoria,
permisos, controlador ausente o variable no declarada. No se exportan mensajes
originales, IP, variables, parametros, contenido de usuarios ni rutas privadas.
Se comprueban tambien estados de Apache, FPM, MariaDB y del propio vigilante,
ademas del espacio e inodos de las ubicaciones principales.

## Cuando consume cuota

Las caidas confirmadas por el vigilante y los fallos graves de servicio o
memoria pueden entrar directamente. Los errores ordinarios requieren repeticion:
cinco errores HTTP o de imagen, o diez avisos de otras categorias, en la ventana
de cinco minutos. Estos valores no son limites de peticiones ni prueba de DoS:
solo deciden cuando tiene sentido estudiar un error observado.

Se permite un maximo inicial de dos intentos de analisis en cualquier periodo
movil de 24 horas y una hora entre intentos. Se reservan antes de llamar al
modelo para que una caida no permita un bucle de gasto. Un retroceso del reloj
no levanta los limites. Las causas ya informadas no se reanalizan por aumentar
su recuento; una subida de prioridad requiere al menos 24 horas o una revision
administrativa del estado. Hasta dieciseis grupos entran en una sola llamada.

La cuota se consulta inmediatamente antes del turno con el mecanismo ya
probado. Desde el 90 % de consumo, con cuota desconocida o con datos invalidos,
se bloquea el analisis ordinario. Se respeta la fecha de nueva comprobacion
comunicada, sin suponer que la cuota se haya renovado. No se compra credito.
Si no hay incidencias elegibles no se consulta al proveedor ni se llama al modelo.
Esto no instala una consulta independiente de cuota cuando el servidor esta sano.

## Barreras y resultados

El detector administrativo solo puede escribir su estado, diario, informes y
la instantanea acotada. Su unidad tiene el perfil de autenticacion inaccesible,
red privada, solo conexiones Unix, limites de CPU y memoria y sistema de archivos
de solo lectura fuera de las salidas expresas. No modifica aplicaciones.

Se reutilizan el cliente autenticado, la comprobacion de cuota, el motor y las
barreras de los tramos anteriores. El hilo no recibe entorno local. La unica
herramienta registrada es leer_observaciones, de argumentos vacios, atendida
como vigilante-ai sin perfil ni webs y sin salida de red. El transporte compartido
comprueba identidad, hilo, nombre, argumentos, tamano y una unica llamada.

La respuesta exige identificador y huella de la instantanea, referencias reales,
prioridad, resumen limitado y propuestas de una lista fija. changes_executed debe
ser falso. Los datos y las propuestas no conceden permisos ni se convierten en
reglas o comandos. Se archivan en el estado privado y se vinculan a la cola y
el diario. Un diagnostico no resuelve automaticamente el incidente.

automatic_analysis_enabled queda activado; ai_actions_enabled sigue desactivado.
El control central y el programa del antipanico conservan sus huellas. No se
reinicia Apache, FPM, MariaDB ni las webs para instalar esta conexion.

## Comprobaciones

Dieciocho pruebas nuevas comprueban privacidad, ventanas temporales, lectura
incremental, rotacion, truncado, limites, deduplicacion, bloqueo por cuota,
retroceso del reloj y rechazo de referencias o reparaciones inventadas. Pasan
en local y VPS. Las otras diecinueve pruebas del cliente y contrato TLS compartidos
tambien pasan tras la reutilizacion del transporte.

La lectura real por la conexion privada devuelve UID 999 y datos reales. Un
ensayo administrativo --validate-current permite comprobar el recorrido completo
con recuentos reales incluso por debajo del umbral ordinario. Se identifica como
validation en el diario y consume el mismo presupuesto; no crea una caida ficticia
ni escribe registros de aplicaciones. La modificacion temporal de la unidad se
retira antes de activar el funcionamiento normal.

El ensayo termino con gpt-6.1-sol, esfuerzo bajo y una llamada a la herramienta.
El modelo propuso revisar controladores ausentes y avisos PHP de ROLEplus,
reconociendo que los datos agregados no acreditan su causa ni un ataque. La
observacion del lector y del motor confirma UID 999, capacidades vacias,
NoNewPrivileges, redes privadas y ausencia del perfil y las webs.

Los ciclos anteriores a la activacion ya verificaron la deteccion sin consumir
modelo. Tras activarla se comprueba un ciclo natural y la ausencia de una segunda
llamada para las mismas causas. Las evidencias concretas y su vuelta atras quedan
en las carpetas privadas del tramo 13, con las fuentes comparadas por SHA-256.

## Pendiente

Esta conexion hace clasificacion y propuestas a partir de datos acotados; no
habilita herramientas para investigar fuentes o reparar aplicaciones. WhatsApp,
el correo diario a las 09:00 Europe/Madrid, la programacion independiente de
cuota y las reparaciones autonomas autorizadas siguen siendo pasos posteriores.
Las auditorias y recuperaciones pendientes conservan sus elementos de la cola.

Para desactivar la conexion se cambia solo automatic_analysis_enabled a falso,
se retira el complemento 30-vigilante-detect.conf de webwatchdog y se recargan
las unidades de systemd. Se conservan diario, cola, politica restante e informes;
no se restaura ciegamente una copia completa de politica que pudiera contener
excepciones posteriores. Los programas compartidos tienen sus copias previas
privadas; su restauracion requiere comprobar que nadie los ha cambiado despues.

Protocolo contrastado con la version instalada y
[OpenAI Docs: App Server](https://learn.chatgpt.com/docs/app-server).
