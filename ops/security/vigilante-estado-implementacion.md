# Estado de implementacion del vigilante

Actualizado: 20 de octubre de 2026, tramo 27. Implementacion NO finalizada.

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
- Segunda base independiente bloqueada por error InnoDB 1932, registrada como
  incidencia de recuperacion de prioridad alta. Originales intactos; cobertura
  SQL expresamente incompleta, sin sustitucion por el candidato local distinto.
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
- Barrera de revision comun con GitHub en el preparador: bloquea trabajo local
  pendiente y divergencias; conserva los cambios ajenos. Pendiente integrar
  la comprobacion distribuida y el bloqueo antes de cualquier escritura real.
- Cron de ISPConfig liberado de una espera de AWStats de mas de diez horas;
  DNS de estadisticas desactivado y tareas atrasadas completadas. Pendientes
  regeneracion de esas estadisticas y limites generales de los trabajos.

## Cinco pasos pendientes para dar la implementacion por terminada

1. **SQL automatico seguro:** resolver la estrategia consistente de ROLEplus y
   MTGsearch frente a MyISAM, verificar copias nuevas y sus restauraciones e
   integrar la ejecucion recurrente con un unico responsable por base. Las
   cinco bases pequenas tienen copias nuevas verificadas, pero la programacion
   nativa no usa todavia el nuevo ejecutor acotado. El laboratorio acredita
   que no caben las grandes en 1.5 segundos; no aplicar esperas largas sin
   preparar una ventana o una alternativa estructural compatible con el servicio.
2. **Tabla independiente ilegible:** recuperar y contrastar la tabla con error
   InnoDB 1932 en aislamiento antes de modificar originales; su candidato
   local distinto no es una fuente de verdad acreditada. Su cobertura sigue
   bloqueada y no puede darse por protegida.
3. **Automatismos heredados:** revisar el estado junto al panel web y el
   ejecutor que termina consultas SQL; eliminar capacidades o decisiones
   contradictorias y conservar un unico control de actuaciones.
4. **Reparaciones acotadas:** conectar el ejecutor probado a destinos y
   operaciones expresamente permitidos, con revision, bloqueo coordinado,
   copia previa, comprobaciones y recuperacion ante fallo. Probar su
   aislamiento efectivo antes de permitir escrituras reales de la IA.
5. **Aceptacion conjunta:** comprobar el recorrido completo de una incidencia,
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
