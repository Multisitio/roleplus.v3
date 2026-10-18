# Octavo tramo: porcentaje de cuota y renovaciones

La fuente de verdad del codigo es la copia local. El estado de cuota lo comunica
OpenAI desde la sesion propia del VPS. Se conserva evidencia privada local y
remota, sin copiar el perfil de autenticacion ni datos de identidad.

## Resultado

Se instala una consulta manual y acotada mediante el protocolo oficial de
Codex App Server. El programa solo envia initialize, initialized y
account/rateLimits/read. No acepta instrucciones, conversaciones, turnos,
comandos de herramientas, renovaciones extraordinarias ni compras de credito.
Una peticion iniciada por el servidor provoca el cierre de la consulta.

Se conserva la identidad separada de autenticacion. Su perfil no se monta en
el entorno de herramientas. El cliente de consulta utiliza una red privada y
un puente local hacia un socket Unix exclusivo. Su pasarela solo permite TLS
para chatgpt.com:443, con comprobacion de destino, SNI, DNS publico e identidad.
La pasarela de autenticacion conserva auth.openai.com como destino predeterminado.
No se abre ninguna escucha publica ni se modifica el cortafuegos de las webs.

Las unidades son estaticas, sin temporizadores. La consulta tiene un limite de
60 segundos y la pasarela de 75 segundos; se cierra al terminar su consumidor.
El proceso del cliente se termina junto con sus descendientes, tambien si falla.
Las salidas y errores del cliente no se vuelcan en registros generales.

## Interpretacion de cuota

Se prefiere rateLimitsByLimitId al resultado antiguo rateLimits. Solo se
conservan porcentajes, duraciones, fechas de renovacion y la indicacion de limite
alcanzado. No se recopilan recuentos de tokens ni identidad de la cuenta.
Los valores ausentes son desconocidos, nunca cero. Se requiere cuota conocida
del grupo codex. Las demas cuotas se conservan separadas para su uso posterior.

La funcion de admision bloquea trabajo ordinario con datos de mas de 300 segundos,
fechas futuras de consulta, renovaciones ya vencidas, cuota desconocida o limite
alcanzado. Reserva el ultimo 10 % para el usuario: desde el 90 % no admite trabajo
ordinario. Esta funcion se ha probado; todavia no hay un ejecutor de tareas al
que aplicarla. Ningun resultado disponible habilita reparaciones por si mismo.

Se calcula la proxima comprobacion de renovacion cinco segundos despues de la
fecha comunicada. Alcanzar esa fecha no acredita cuota renovada: hay que volver
a consultarla. No se instala aun el programador de consultas, el informe diario
ni las notificaciones. La cuota es compartida por la cuenta y no representa
consumo exclusivo del vigilante. Si OpenAI devuelve solo una ventana, no se
infiere el estado de las demas.

La consulta no abre el destino de autenticacion como alternativa automatica.
Si la sesion necesita una renovacion que esta pasarela no admite, falla de forma
cerrada y requiere revisar el flujo separado de autenticacion.

## Validacion

- Doce pruebas de pasarela: controles anteriores de destino, DNS, SNI, UID y
  duracion, mas exclusividad de chatgpt.com respecto a auth.openai.com.
- Doce pruebas de cuota: datos ausentes, valores numericos invalidos, varias
  cuotas, antiguedad, fechas de renovacion, reserva, agotamiento, segunda
  ventana y secuencia real del protocolo con un proceso de prueba.
- Consulta real en el VPS, sin crear conversaciones ni turnos.
- Rechazo real de example.com y auth.openai.com en la pasarela de consulta.
- Denegacion real al usuario de herramientas del perfil, salida y socket.
- Unidades inactivas al acabar, sin habilitacion automatica; servicios web y
  temporizador antipánico activos. Huellas del controlador, politica y
  antipánico conservadas.

El primer ensayo de comprobacion de destinos no mantuvo un consumidor de la
pasarela, que se cierra al dejar de ser necesaria. Se corrigio el procedimiento
de prueba usando una dependencia temporal acotada; el ensayo completo paso.

Las evidencias concretas quedan en scratch/vigilante/2026-10-18/tramo-8 y en
su replica privada de administrador. La separacion entre un cliente que ejecute
modelos y herramientas reales requiere su propia prueba antes de habilitarlo.
No se atribuye esa garantia a la mera lectura de cuota. El certificado pendiente
y los demas trabajos de las webs siguen en su cola, sin modificarlos aqui.

Fuente: [OpenAI Docs: App Server](https://learn.chatgpt.com/docs/app-server),
inicializacion y account/rateLimits/read. La version instalada del CLI se
mantiene fijada y se ha contrastado con su ayuda real.
