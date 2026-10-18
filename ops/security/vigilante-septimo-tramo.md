# Vigilante: septimo tramo

Fecha: 18 de octubre de 2026. Salida exclusiva para autenticacion y sesion
separada de las herramientas del vigilante.

## Separacion implantada

Se conserva la cuenta vigilante-ai y su entorno sin red. Se crea vigilante-auth,
tambien bloqueada, sin grupos adicionales y con shell nologin. Esta segunda
identidad solo puede ejecutar las operaciones fijas login, status y check del
programa de autenticacion. No recibe instrucciones ni ejecuta tareas del modelo.

Su perfil privado no se monta en el entorno del vigilante. Los codigos efimeros
se escriben en una carpeta de control de administrador, fuera de ambos entornos.
No se copian sesiones del PC, claves API ni credenciales a los informes.

El cliente sigue dentro de una raiz separada y una red privada. Solo admite
TCP de bucle local para comunicarse con un puente propio; la conexion al
exterior pasa por un socket Unix que exige la identidad de autenticacion.
La cuenta del vigilante no puede leer ni usar ese socket, perfil o control.

La pasarela corre con identidad dinamica sin privilegios, en otra raiz separada.
Solo permite CONNECT a auth.openai.com:443. Comprueba tambien el SNI de TLS,
rechaza resoluciones privadas/locales y conecta a la direccion numerica revisada.
No acepta HTTP plano, otros destinos ni puertos. TLS sigue entre el cliente y
OpenAI, con verificacion normal de certificados; la pasarela no descifra datos
de autenticacion ni registra cabeceras, cuerpos, codigos o credenciales.

No hay puerto publico, cambio de cortafuegos ni temporizador. El cliente y la
pasarela tienen un maximo de 15 minutos por inicio manual. La pasarela se detiene
cuando deja de ser necesaria. Se conservan limites de memoria, CPU y tareas;
solo admite cuatro conexiones simultaneas de autenticacion con transferencias
acotadas. Estos limites son del tramite de acceso, no del trafico de las webs.

## Comprobaciones

- Diez pruebas Linux: destino exacto, puertos y metodos no admitidos, Host
  contradictorio, cabeceras excesivas, SNI real, SNI de otro dominio, todos los
  truncamientos del saludo, texto plano, DNS privado/mixto e identidad ajena.
- Conexion real: otro dominio recibe rechazo, la salida directa esta bloqueada,
  el puente local funciona y se verifica TLS hacia el destino autorizado.
- La peticion de codigo de dispositivo del CLI funciona a traves del puente y
  la pasarela. No se confunde la respuesta 403 de un HEAD generico a la portada
  de autenticacion con el resultado del flujo de acceso.
- El perfil y el control son ilegibles para vigilante-ai tambien desde el host;
  no se depende exclusivamente de que no aparezcan en su raiz separada.
- Propiedades efectivas de systemd verificadas: identidades, capacidades vacias,
  red privada de los clientes, limite temporal y unidades estaticas.
- Huellas del antipánico, politica, control y unidad base conservadas. Apache,
  MariaDB, PHP-FPM y el temporizador antipánico siguen activos.

Windows valida sintaxis; las pruebas de red e identidad se ejecutan en el VPS.
Las fuentes locales se comparan con las copias instaladas por huella/contenido.

## Estado del acceso y siguientes condiciones

La retransmision de ambos extremos del tunel permite hasta 900 segundos,
dentro del limite temporal de la unidad. El anterior limite de 180 segundos
cortaba una conexion reutilizada durante la espera del usuario. Una prueba
comprueba que sigue transmitiendo despues de tres minutos.

El codigo de dispositivo se ha solicitado mediante el flujo oficial. Completar
el acceso requiere confirmacion del usuario en el navegador de OpenAI. No se
deduce que haya sesion por iniciar la unidad ni por emitir el codigo: se exige
archivo privado con permisos correctos y confirmacion de login status. El estado
concreto verificado queda en el informe privado y en la respuesta al usuario.
Al confirmar el acceso se retira el codigo efimero de la salida de control,
conservando el perfil de sesion exclusivamente en el VPS.

Esta pasarela es solo de autenticacion. No habilita llamadas al modelo,
herramientas, publicaciones ni reparaciones. Antes de ejecutar trabajo falta
comprobar la frontera entre el cliente con sesion y las herramientas sin
credenciales, asi como sus destinos autorizados y el control de cuota.
Un proxy de comandos por si solo no limita el trafico del cliente de modelos.

La implementacion acepta el saludo TLS completo en un primer registro y falla
si no puede comprobarlo. Una actualizacion del CLI requiere repetir las pruebas,
sin abrir la red como alternativa automatica. El aislamiento comparte nucleo
con el VPS; administrador comprometido sigue fuera de estas protecciones.

Fuentes oficiales: [autenticacion](https://learn.chatgpt.com/docs/auth),
[seguridad del cliente y comandos](https://learn.chatgpt.com/docs/agent-approvals-security)
y [separacion de credenciales](https://developers.openai.com/api/docs/guides/agents-api/environments/security).
Evidencias privadas: scratch/vigilante/2026-10-18/tramo-7 y su replica bajo
/root/codex-vigilante/tramo-7. El perfil y las salidas del acceso quedan fuera
de esas evidencias y de Git. El certificado mantiene su incidencia prioritaria.
