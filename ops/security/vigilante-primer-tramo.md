# Vigilante: primer tramo de implementacion

Fecha: 18 de octubre de 2026. Estado: inventario inicial completado; agente
no instalado y reparaciones autonomas no habilitadas.

## Alcance comprobado

Inspeccion de solo lectura de produccion: recursos, servicios, escuchas,
configuracion seleccionada de Apache y PHP-FPM, tareas, automatismos existentes,
metadatos de registros y copias, certificados y contadores globales de MariaDB.
Comprobaciones HTTP puntuales de ROLEplus y MTGsearch. Sin pruebas de carga,
reinicios, cambios de configuracion ni modificaciones de datos de usuarios.
La escritura remota queda limitada al archivo privado de este inventario.

Las fuentes de verdad actuales de codigo y datos no cambian en este tramo.
La propuesta de Git como codigo aceptado debe concretarse en el tramo de
despliegue; no se reemplazan las reglas actuales por una decision implicita.

## Evidencia y privacidad

El repositorio es publico. Inventario detallado, permisos, puertos, horarios y
huellas se conservan en scratch/vigilante/2026-10-18 (ignorado por Git) y en
una replica privada del VPS, fuera de las raices web. No se versionan secretos,
contenido de registros, credenciales ni el informe de exposicion del servidor.
Las replicas se comparan por SHA-256 antes de cerrar el tramo.

Una fotografia favorable no demuestra estabilidad historica. Los contadores
acumulados se distinguen de las mediciones del intervalo. La existencia de una
copia no acredita su restauracion. Un puerto en escucha no acredita acceso
externo ni justifica cerrarlo. Una alerta no acredita por si sola un ataque.

## Condiciones para los siguientes tramos

1. Reutilizar la instrumentacion existente de ROLEplus documentada en
   ../php/roleplus-observability.md y coordinar los automatismos ya activos.
2. Definir reglas vigentes, excepciones, aprobaciones y prohibiciones antes
   de conceder capacidad de reparacion; el agente no puede cambiarlas solo.
3. Resolver las condiciones de aislamiento y recuperacion recogidas en el
   informe privado antes de habilitar despliegues automaticos.
4. Distinguir vigilancia convencional de activaciones del modelo. Agrupar
   incidentes, limitar concurrencia y priorizar la reduccion de causas reales.
5. Mantener todo en el VPS por decision del usuario, documentando que una
   toma de control de administrador o caida completa afecta al vigilante.
6. WhatsApp sera el canal previsto para incidencias/aprobaciones y el correo
   diario a las 09:00 Europe/Madrid mostrara porcentajes y renovaciones de uso.
   Ninguna notificacion ni tarea nueva queda activada por este documento.

Siguiente tramo: reglas y decisiones operativas. La instalacion de Codex CLI
corresponde a un tramo posterior.