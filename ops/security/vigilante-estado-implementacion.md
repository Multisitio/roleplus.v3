# Estado de implementacion del vigilante

Actualizado: 20 de octubre de 2026, tramo 31. Implementacion NO finalizada.

## Implantado y comprobado

- Descubrimiento de sitios y servicios, detector y cola de causas pendientes.
- Control central de reinicios con el antipánico existente.
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

## Tres pasos pendientes para dar la implementacion por terminada

1. **SQL automatico seguro:** resolver la estrategia consistente de ROLEplus y
   MTGsearch frente a MyISAM, verificar copias nuevas y sus restauraciones e
   integrar la ejecucion recurrente con un unico responsable por base. Las
   cinco bases pequenas tienen copias nuevas verificadas, pero la programacion
   nativa no usa todavia el nuevo ejecutor acotado. El laboratorio acredita
   que no caben las grandes en 1.5 segundos; no aplicar esperas largas sin
   integrar y comprobar el aviso previo de mantenimiento ya instalado. Falta
   conectar la barrera al ejecutor y su programacion unica; no se ha activado
   una ventana real ni se han obtenido aun copias nuevas de las dos grandes.
2. **Reparaciones acotadas:** conectar el ejecutor probado a destinos y
   operaciones expresamente permitidos, con revision, bloqueo coordinado,
   copia previa, comprobaciones y recuperacion ante fallo. Probar su
   aislamiento efectivo antes de permitir escrituras reales de la IA.
3. **Aceptacion conjunta:** comprobar el recorrido completo de una incidencia,
   diagnostico, accion permitida o escalado, aviso y diario; verificar limites
   y continuidad de los servicios, incluido el aviso ante fallo de las nuevas
   copias de texto y SQL independientes, cerrar la lista y declarar expresamente
   finalizada la implementacion.

La recuperacion de los textos seleccionados ya esta verificada. Los binarios
quedan fuera del alcance autorizado y las copias conservadas en el mismo VPS
no protegen de su perdida total. Esos limites no se convierten en nuevos pasos.

Los errores PHP y otras incidencias que se descubran despues pertenecen a la
operacion normal del vigilante: no amplian indefinidamente esta implementacion.
Un bloqueo concreto que impida satisfacer estos criterios debe explicitarse,
en lugar de marcarlo como terminado o agregar tramos sin criterio de cierre.
