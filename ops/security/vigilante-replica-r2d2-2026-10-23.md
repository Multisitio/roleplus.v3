# Replica privada VPS a R2-D2: 23 de octubre de 2026

## Resultado y alcance

Primera transferencia real guardada en R2-D2, fuera del directorio publico:
C:/xampp/var/vigilante-replicas. Se verificaron 21 archivos y 32 832 188 bytes:
los textos de once webs y dos dependencias compartidas, y ocho archivos SQL
correspondientes a siete bases distintas. Una base independiente conserva
dos versiones. No se restauraron datos sobre las webs ni las bases de R2-D2.

Las copias antiguas de ROLEplus y MTGsearch siguen presentes en el VPS, pero
no pasan la politica de texto por caracteres de control sin representar.
No se transfieren ni se alteran sus originales. Sus nuevas capturas seguras
siguen pendientes del bloque SQL y de la ventana de madrugada. No se afirma
cobertura SQL completa ni tres destinos: GitHub cifrado sigue pendiente.

La primera recogida se ejecuto desde C-3PO y escribio realmente en el disco
de R2-D2. El cliente y el instalador estan preparados en R2-D2, pero la tarea
NO esta registrada ni comprobada alli: WMI y tareas remotas rechazan el
acceso, SSH/WinRM no estan disponibles y falta concretar el acceso por Chrome.
No existe todavia ejecucion desatendida independiente de C-3PO.

## Fallo de integracion detectado y reparado

El registro del productor contenia siete capturas de texto cuyos archivos
ya no existian. La integracion anterior las registraba en web_backup con
prefijo text-, que get_files de ISPConfig no reconoce. Su limpieza compara
ese resultado con todos los registros del dominio y puede borrar el texto
cuando comparte carpeta con SQL. La reproduccion con la clase instalada,
archivos sinteticos y base de datos simulada elimina la copia antigua y
conserva la situada en el espacio separado. No identifica por si sola la
hora o el proceso historico que produjo cada ausencia.

Se conserva el registro anterior y sus siete referencias ausentes. Las seis
capturas existentes se copian y verifican por huella. Se crean capturas
actuales de las otras siete mediante el servicio existente, con sus limites
de recursos y sin tocar los fuentes de las webs. No se afirma haber recuperado
el contenido historico de las capturas ausentes.

El almacen del productor es ahora /var/backup/vigilante-text/<id>. Ya no
registra ni elimina referencias textuales en la tabla nativa web_backup.
Su retencion de dos versiones pertenece exclusivamente al vigilante. Los
originales de las seis capturas supervivientes no se borran en esta migracion.
El temporizador existente conserva su horario y las configuraciones nativas
de copias completas siguen desactivadas. SQL conserva su responsable actual.

## Transporte y comprobaciones

SSH con identidad del VPS fijada y clave exclusiva con restrict y orden
forzada. Solo acepta manifest o get por identificador, posicion y longitud;
no recibe rutas ni SQL del cliente. Tres intentos reales de ejecutar ordenes
fuera del protocolo se rechazan. La clave privada esta fuera de la raiz web
y del repositorio, en la carpeta de acceso restringido de R2-D2. El directorio
permite acceso solo al usuario autorizado, administradores y SYSTEM.

Las copias se transfieren en bloques de hasta 4 MiB y se verifican por bloque
y SHA-256 completo. El cliente comprueba el cierre gzip, el SQL textual y
cada archivo y metadato del tar frente a la huella del manifiesto original.
No ejecuta ni extrae codigo sobre las webs. Siete pruebas del cliente, cinco
del exportador y siete del productor pasan; tambien la reproduccion aislada
de la limpieza nativa. La reanudacion real por SSH empieza en el byte 65 536
de una copia de 20 161 092 bytes y termina con la misma huella y metadatos.

Los parciales quedan separados de las copias verificadas. Una desconexion
no borra estas ultimas ni su fecha de verificacion por conjunto. El almacen
tiene limite de 4 GiB y exige margen adicional de 2 GiB; si no cabe, conserva
lo anterior y registra pendiente, sin borrar archivos ajenos ni llenar el disco.
No se ha instalado otro generador de copias ni contratado servicio alguno.

## Activacion comprobada en R2-D2

El instalador tools/install-vigilante-replica-r2d2.ps1 verifica nombre del
equipo y dependencias, y prepara una sola tarea: al arrancar y cada quince
minutos, sin instancias simultaneas y sin guardar contrasenas de Windows.
Registrada y ejecutada en R2-D2: procesos reales Python observados como
R2-D2\Lobo Blanco, S4U y nivel limitado. Una ejecucion reciente termina
con 21 archivos verificados y sin fallo de transporte. Codigo 1 porque
siguen omitidas las dos bases grandes; no acredita cobertura total.
OpenSSH nativo y Python se han utilizado realmente en R2-D2. La recogida
ya no depende de C-3PO ni de Chrome remoto. Evidencia adicional privada:
scheduled-task-proof.json y maintenance-ssh-status.json.

La aceptacion final incluye esa ejecucion, perder/recuperar Internet y
recuperar las mismas capturas desde los tres destinos. Estos criterios
permanecen dentro de copias y aceptacion, sin crear nuevos bloques.

Evidencias privadas: /root/codex-vigilante/replica-r2d2-2026-10-23 y
C:/xampp/var/vigilante-replicas/status.json y resume-check.json de R2-D2.
