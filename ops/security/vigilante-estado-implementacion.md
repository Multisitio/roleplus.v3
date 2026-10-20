# Estado de implementacion del vigilante

Actualizado: 20 de octubre de 2026, tramo 20. Implementacion NO finalizada.

## Implantado y comprobado

- Descubrimiento de sitios y servicios, detector y cola de causas pendientes.
- Control central de reinicios con el antipánico existente.
- Diagnostico de IA aislado, autenticacion separada, limites de investigaciones
  y reserva de cuota para el operador.
- Comprobacion automatica de cuota y renovaciones sin llamadas al modelo.
- Avisos de ROLEplus recibidos con sonido y ficha ampliada para el operador.
- Informe diario a las 09:00 de Madrid, con envio inicial recibido.
- Ensayo aislado de restauracion de ROLEplus y comprobacion de ocho gzip.
- Correspondencias reparadas de dos bases y retencion ampliada de las siete
  bases cuya web esta identificada.

## Bloques pendientes para dar la implementacion por terminada

1. **Recuperacion:** completar cobertura de archivos y configuraciones de todas
   las webs, resolver las bases sin correspondencia, definir una ejecucion de
   copias compatible con MyISAM y el servicio en uso, verificar nuevas copias
   y restauraciones. Las limitaciones de conservarlas en el mismo VPS deben
   quedar expresas; no se promete recuperacion tras perdida total de ese VPS.
2. **Automatismos heredados:** revisar el estado junto al panel web y el
   ejecutor que termina consultas SQL; eliminar capacidades o decisiones
   contradictorias y conservar un unico control de actuaciones.
3. **Reparaciones acotadas:** conectar el ejecutor probado a destinos y
   operaciones expresamente permitidos, con revision, bloqueo coordinado,
   copia previa, comprobaciones y recuperacion ante fallo. Probar su
   aislamiento efectivo antes de permitir escrituras reales de la IA.
4. **Aceptacion conjunta:** comprobar el recorrido completo de una incidencia,
   diagnostico, accion permitida o escalado, aviso y diario; verificar limites
   y continuidad de los servicios, cerrar la lista y declarar expresamente
   finalizada la implementacion.

Los errores PHP y otras incidencias que se descubran despues pertenecen a la
operacion normal del vigilante: no amplian indefinidamente esta implementacion.
Un bloqueo concreto que impida satisfacer estos criterios debe explicitarse,
en lugar de marcarlo como terminado o agregar tramos sin criterio de cierre.
