# Estado de implementacion del vigilante

Actualizado: 23 de octubre de 2026. Implementacion NO finalizada.

## Implantado y comprobado

- Descubrimiento de sitios y servicios, detector y cola de causas pendientes.
- Control central de reinicios con el antipÃ¡nico existente.
- Diagnostico de IA aislado, autenticacion separada, limites de investigaciones
  y reserva de cuota para el operador.
- Comprobacion automatica de cuota y renovaciones sin llamadas al modelo.
- Avisos de ROLEplus recibidos con sonido y ficha ampliada para el operador.
- Informe diario a las 09:00 de Madrid, con envio inicial recibido.
- Restauracion aislada de los siete volcados de bases asociadas a webs:
  197 tablas, dos vistas operativas y 992 600 filas. Se conserva la evidencia
  de cada archivo; los de Controlate y Delixio son de abril y no acreditan
  copias actuales. Los temporales SQL y binarios de los ensayos se eliminan,
  tambien ante fallo comprobado; retirados los 488 MiB del ensayo inicial.
- Correspondencias reparadas de dos bases y retencion ampliada de las siete
  bases cuya web esta identificada.
- Copias SQL nuevas y restauradas de Controlate, Delixio, Mansion y Multisitio:
  30 tablas y 127 437 filas coinciden con los recuentos bajo bloqueo. Registradas
  en ISPConfig, conservando su programacion nativa. El resultado parcial inicial
  de ese plan queda conservado.
- Rechazo textual de Rola resuelto mediante expresiones SQL equivalentes:
  copia nueva de 58 tablas y 18 080 filas restaurada, con todos los datos
  comparados por SHA-256 frente al SQL original de la misma captura. Ningun
  valor de produccion se modifica; temporales retirados. Las cinco bases
  pequenas ya tienen copias nuevas comprobadas, sin cambiar su programador.
- Copia SQL semanal de una base independiente, restaurada en aislamiento:
  una tabla y 67 filas. Bloqueo de lectura acotado y liberacion comprobada
  al cancelar, matar o suspender su controlador. Proxima ejecucion:
  25 de octubre a las 04:30 de Madrid.
- Segunda base independiente recuperada: c1_deportes.usuarios, nueve columnas
  y una fila. Definicion extraida del .frm original y datos del .ibd original;
  importacion, integridad, segunda restauracion y copia nueva de produccion
  contrastadas en aislamiento, comparando todos los valores y la estructura.
  Originales conservados sin modificar su contenido; directorio corregido de
  0555 a 0750, sin cambios recursivos. Sin reiniciar MariaDB ni otros servicios.
  Ambas bases independientes cubiertas por el programador semanal existente,
  con siete versiones por base; no se crea otro programador ni se usa el
  candidato local distinto. Laboratorio y copias binarias temporales retirados.
- Copias semanales solo de texto para once webs, ROLEplus incluida, y las
  dependencias compartidas core/vendor; dos versiones por raiz. Las copias
  completas web anteriores quedan desactivadas. Binarios fuera de cobertura
  por decision del usuario; SQL conserva su politica independiente.
- Recuperacion de todos los textos seleccionados: trece raices, 36 483
  archivos y 165 340 088 bytes, con contenido y modo contrastados. Propietario
  y grupo verificados en metadatos, sin aplicarlos desde el usuario aislado.
  Temporales y duplicaciones de entrada retirados; servicios sin reinicios.
- Bases grandes medidas en laboratorio: ROLEplus tarda 8.86 segundos en
  exportarse y MTGsearch 29.69; ambas se rechazan con el limite de 1.5 segundos.
  No son tiempos garantizados de produccion ni autorizan una pausa real.
- Aviso de mantenimiento instalado e inactivo en las siete webs con bases
  asociadas, con configuracion reflejada en ISPConfig. Informa de solo lectura
  y tiempo estimado; rechaza peticiones dinamicas antes de PHP, mantiene
  archivos estaticos existentes y caduca sin depender del coordinador.
  Ensayo privado completo y activacion real solo en nombres ficticios de
  ROLEplus y MTGsearch: paginas normales disponibles y ningun bloqueo SQL.
  Recargas coordinadas con el control central, sin reiniciar servicios.
- Aviso sobre el documento abierto: cliente cargado desde 50 plantillas de
  produccion en las siete webs. Conserva formulario, texto, casillas y archivo
  seleccionado; se retira sin recargar ni reenviar. Estado estatico sin PHP,
  SQL ni cookies, con pausa de consultas en pestanas ocultas. Antelacion
  predeterminada de ocho segundos antes de cerrar el acceso dinamico.
  Las paginas anteriores al despliegue reciben el cliente en su siguiente
  navegacion normal; no se modifica retroactivamente su JavaScript cargado.
- Barrera de revision comun con GitHub en el preparador: bloquea trabajo local
  pendiente y divergencias; conserva los cambios ajenos. Pendiente integrar
  la comprobacion distribuida y el bloqueo antes de cualquier escritura real.
- Automatismos heredados centralizados: terminador SQL generico retirado y
  enmascarado, observacion agregada sin credenciales, estado del antipanico
  privado y replica de lectura para el panel. Nueve tareas CLI conservan sus
  horarios y dos bloqueos existentes, con unidad unica, limites de tiempo y
  recursos. La desaparicion del intermediario no elimina el plazo ni permite
  otra instancia. Las ventanas de mantenimiento inhiben fallos y reinicios.
- ISPConfig conserva su programador y sus operaciones internas. Se limita solo
  su generador externo AWStats a 180 segundos, reflejado en el panel. La prueba
  real agota ese plazo y conserva la estadistica mensual anterior; su causa
  sigue como incidencia operativa, sin declarar regeneracion correcta. Las
  esperas internas prolongadas se detectan y escalan, sin matar globalmente
  tareas que puedan estar escribiendo configuracion o copias. Once webs HTTP
  200 y procesos principales de Apache, PHP-FPM y MariaDB sin reinicios.

## Mantenimiento previo cerrado el 23 de octubre de 2026

ISPConfig actualizado y validado en 3.3.2p1. Panel, aplicaciones y manejador
general en PHP 8.4.27; PHP 8.1 retirado. Once webs comprobadas, configuracion
y coordinacion de certificados conservadas. Detalles e incidencia del
primer intento en ispconfig-php84-2026-10-23.md. Este mantenimiento no cambia
los tres criterios de cierre pendientes que siguen a continuacion.

## Copias en tres destinos: requisito confirmado el 23 de octubre

Tanto los textos seleccionados como el SQL deben tener copia en VPS, R2-D2
y GitHub. Esta cobertura triple aun NO esta implantada ni verificada. Las
copias de codigo publicadas no acreditan una copia SQL ni de todos los textos
privados seleccionados. Tres destinos no sustituye la retencion de versiones.

R2-D2 esta encendido habitualmente; su conexion depende del movil del
operador. Si queda sin Internet, la transferencia debe quedar pendiente y
reanudarse cuando vuelva, mostrando fecha de ultima copia verificada y
antiguedad por destino. No exigir Internet nocturno para generar la copia
local del VPS; tampoco contar como completa una transferencia pendiente.

Los SQL y textos privados se cifran antes de GitHub; las claves no se
publican y deben poder recuperarse sin depender solo del VPS. Comprobar
capacidad y limites del destino sin introducir gasto adicional. La replica
de R2-D2 se guarda fuera de sus webs y bases activas, sin sustituir su fuente
de verdad. La aceptacion exige recuperar y contrastar las mismas capturas
desde los tres destinos. El requisito inicial era documental; la primera transferencia y sus limites
actuales se detallan en el avance siguiente.

## Avance de replicas del 23 de octubre

Primera copia real en R2-D2: 21 archivos, trece conjuntos de texto y siete
bases distintas, verificados por huella y contenido. Reanudacion real por
SSH comprobada. La primera recogida se ejecuto desde C-3PO; despues se ha
activado y verificado una ejecucion de la tarea propia de R2-D2, al arrancar
y cada quince minutos, sin depender de C-3PO. GitHub cifrado y las nuevas copias grandes siguen
pendientes. No confundir esta transferencia con cobertura triple completa.

Detectadas siete capturas de texto ausentes del almacen anterior. La
incompatibilidad con la limpieza de ISPConfig se reproduce en aislamiento.
Almacen separado, seis copias preservadas y siete capturas actuales nuevas;
no se afirma recuperar los textos historicos desaparecidos. Se conserva el
programador original. Detalles en vigilante-replica-r2d2-2026-10-23.md.

## Tres pasos pendientes para dar la implementacion por terminada

Acceso de R2-D2: SSH nativo activado y conexion real desde C-3PO verificada
el 23 de octubre. Tarea de replicas activa, con ejecucion reciente terminada
y 21 archivos comprobados. Salida 1 porque faltan las dos bases grandes;
no es cobertura completa. Ver r2d2-acceso-mantenimiento-2026-10-23.md.

1. **SQL automatico seguro:** resolver la estrategia consistente de ROLEplus y
   MTGsearch frente a MyISAM, verificar copias nuevas y sus restauraciones e
   integrar la ejecucion recurrente con un unico responsable por base y las
   replicas verificadas en VPS, R2-D2 y GitHub descritas arriba. Las
   cinco bases pequenas tienen copias verificadas. La programacion de ISPConfig
   ya delega en el ejecutor acotado para las nueve bases revisadas; el
   temporizador independiente anterior esta deshabilitado. El laboratorio acredita
   que no caben las grandes en 1.5 segundos; no aplicar esperas largas sin
   el aviso previo de mantenimiento ya instalado. Falta verificar las primeras
   copias grandes, restaurarlas y completar las replicas.
   El conector entre aviso HTTP, barrera Apache y ejecutor SQL ya esta probado
   en aislamiento con restauracion MyISAM/InnoDB, caducidad y fallos del aviso.
   Los bloqueos largos solo se admiten de 03:00 a 06:00 de Madrid. No se ha
   activado una ventana real ni obtenido aun copias nuevas de las dos grandes.
   Primera ventana prevista: domingo 25/10/2026 a las 04:45 de Madrid.
   Ocho pruebas del programador y quince del conjunto HTTP/SQL pasan; en
   produccion el nuevo ejecutor se aplaza por horario sin bloquear ni reiniciar.
   El detector incluye fallos y ventanas perdidas. Detalles en
   vigilante-sql-programacion-2026-10-23.md.
2. **Reparaciones acotadas:** conectar el ejecutor probado a destinos y
   operaciones expresamente permitidos, con revision, bloqueo coordinado,
   copia previa, comprobaciones y recuperacion ante fallo. Probar su
   aislamiento efectivo antes de permitir escrituras reales de la IA.
3. **Aceptacion conjunta:** comprobar el recorrido completo de una incidencia,
   diagnostico, accion permitida o escalado, aviso y diario; verificar limites
   y continuidad de los servicios, incluido el aviso ante fallo de las nuevas
   copias de texto y SQL, la recuperacion desde los tres destinos y la
   reanudacion tras perder Internet en R2-D2; cerrar la lista y declarar expresamente
   finalizada la implementacion.

La recuperacion de los textos seleccionados ya esta verificada. Los binarios
quedan fuera del alcance autorizado y las copias conservadas en el mismo VPS
no protegen de su perdida total. El usuario exige ahora replicarlas tambien
en R2-D2 y GitHub; esa proteccion queda pendiente dentro de los bloques existentes.

Los errores PHP y otras incidencias que se descubran despues pertenecen a la
operacion normal del vigilante: no amplian indefinidamente esta implementacion.
Un bloqueo concreto que impida satisfacer estos criterios debe explicitarse,
en lugar de marcarlo como terminado o agregar tramos sin criterio de cierre.


## Copias del panel corregidas el 23 de octubre

El trabajo sysbackup de ISPConfig ya no genera archivos completos con
binarios: conserva las 03:00 y tres generaciones textuales, con SQL InnoDB
transaccional sin bloquear escrituras. Primera captura verificada y las
79 tablas restauradas en aislamiento; monitor del panel ok y once webs
HTTP 200. Mismos tres archivos recogidos y verificados por la tarea propia
de R2-D2, que ahora acredita 24 archivos en total. Sus dos bases grandes
siguen pendientes y GitHub cifrado tambien. No se eliminan copias antiguas.
Detalles: vigilante-panel-copias-2026-10-23.md. Se mantienen los tres bloques
de cierre anteriores; este ajuste cumple la politica textual existente.

Autorizada la conversion de tablas a InnoDB donde resulte adecuada, previa
prueba de compatibilidad, copia y proteccion del servicio. Hay 182 tablas
MyISAM en bases activas, incluidas 68 de ROLEplus y 28 de MTGsearch. Aun no
se han convertido. Esta alternativa se evaluara dentro del trabajo SQL
pendiente; no se declara resuelto el bloqueo de las grandes por inventariarlo.
