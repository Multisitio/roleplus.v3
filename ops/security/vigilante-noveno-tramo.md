# Noveno tramo: ensayo de IA con herramientas externas

El codigo local es la fuente de verdad. La sesion y la medicion de cuota son
propias del VPS. Las fuentes se verifican antes de instalarlas; las evidencias
concretas quedan en las carpetas privadas del tramo, sin credenciales.

## Separacion

El cliente de modelos conserva la identidad vigilante-auth y el perfil privado.
Su hilo se crea con environments vacio: no dispone de un entorno local para
ejecutar herramientas. Se desactivan shell, herramientas de entorno estables,
complementos, aplicaciones, navegador, ordenador, memoria, agentes adicionales,
busqueda web y modos de codigo opcionales. Solo se registra una herramienta
externa sintetica con argumentos vacios y publicacion directa.

El distribuidor de herramientas del CLI requiere el auxiliar code-mode-host.
Se instala el artefacto oficial de la misma version fijada, comprobando longitud,
SHA256 del archivo y contenido de la extraccion. Se incorpora al prefijo privado
del vigilante; no se cambia PATH global ni se actualiza el CLI.

El auxiliar se ejecuta con vigilante-ai dentro de su raiz y red privadas.
El cliente solo recibe un puente binario fijo, sin interpretacion de codigo ni
lectura de perfiles. Ambos extremos se comunican mediante un socket de systemd
propiedad de root, accesible al grupo de autenticacion. No hay escucha publica.
El motor no recibe el entorno del proceso autenticado ni monta su perfil.

La herramienta sintetica se atiende en otro servicio vigilante-ai, con el mismo
aislamiento externo. Solo admite la accion fija probe; rechaza rutas, comandos
y campos adicionales. El resultado contiene identidad, numero de comprobaciones
y valores booleanos, nunca contenido de archivos. La prueba exige una llamada
estructurada, una respuesta valida y un UID distinto del cliente. Un texto de
exito generado por el modelo no acredita ejecucion.

El auxiliar JavaScript necesita memoria ejecutable. La excepcion se limita a
su servicio sin credenciales, sin red y sin acceso a las webs. El cliente
autenticado y la herramienta conservan MemoryDenyWriteExecute. Se mantienen
capacidades vacias, NoNewPrivileges, raiz limitada, restricciones de llamadas
al sistema y limites de recursos en las tres identidades de ejecucion.

## Limites del ensayo

La entrada es sintetica y fija. Primero se consulta cuota actual y se aplica
la reserva del tramo anterior. Se usa el modelo predeterminado anunciado por
la cuenta, sin sustitucion automatica por otro y con esfuerzo bajo si admite
ese nivel. Solo se autoriza una llamada a la herramienta. Las solicitudes
inesperadas del servidor o las respuestas invalidas cierran el ensayo.

El cliente tiene 110 segundos; el motor y su puente, 100; la herramienta, 25;
la pasarela exterior, 120. Los grupos internos de Tokio y Rayon se limitan a
dos hilos. El maximo de tareas sigue en 32 por servicio. Los limites de CPU
son 10 % para cliente, motor y pasarela, y 5 % para la herramienta: un maximo
configurado conjunto de 35 % de un nucleo. No se crea ningun temporizador.

Las conexiones desaparecen al terminar sus consumidores. El cliente, el motor
y sus descendientes se terminan al cerrar el ensayo. La prueba no habilita
lectura de registros reales, reparaciones, publicaciones ni reinicios.
El antipánico mantiene su propietario y su politica.

## Incidencias encontradas y criterio de validacion

El ensayo detecto que la ayuda del CLI no acreditaba herramientas funcionales:
faltaba el auxiliar oficial. Tambien detecto el distribuidor desactivado,
agotamiento del limite de tareas y una terminacion por SIGTRAP del motor.
La ayuda del auxiliar tampoco se acepto como prueba de ejecucion de herramientas.
Se ajustaron los grupos de hilos y se traslado el motor fuera de la sesion,
con una excepcion de memoria ejecutable limitada a su servicio aislado.

El sandbox nativo se estudio aparte. El paquete bubblewrap disponible en la
distribucion no satisface los requisitos del CLI fijado; no se usa como prueba
de aislamiento. Este tramo utiliza servicios externos aislados por el sistema
operativo. La instalacion exploratoria del paquete se revierte al cerrar el
tramo; sus informes se conservan en privado.

La validacion automatizada comprueba argumentos, cuota, ausencia de entorno,
peticiones inesperadas, necesidad de una llamada real y el transporte RPC.
La validacion en el VPS comprueba la herramienta por su conexion real y observa
las identidades y espacios de nombres durante el ensayo completo. El resultado
concreto de ese ensayo queda en el informe privado; no se infiere de esta guia.

El ensayo completo ha terminado correctamente con el modelo predeterminado
gpt-6.1-sol y esfuerzo bajo: una llamada externa, 37 comprobaciones superadas
y UID 999. La observacion del administrador confirma ese UID tambien en el
motor, redes privadas diferentes de la del host, capacidades efectivas vacias,
NoNewPrivileges y ausencia del perfil y las webs en ambos entornos.

Los limites no protegen de un administrador del VPS comprometido ni permiten
afirmar ausencia de vulnerabilidades del nucleo o del motor. La ampliacion a
herramientas reales requiere su propia politica y pruebas; este ensayo solo
expone una operacion sintetica cerrada. Los trabajos pendientes de las webs
conservan su cola.

Fuentes: [OpenAI Docs: App Server](https://learn.chatgpt.com/docs/app-server),
[seguridad de aprobaciones](https://learn.chatgpt.com/docs/agent-approvals-security)
y el protocolo y fuentes de la version instalada. La interfaz experimental
se ha contrastado con el esquema generado por ese ejecutable.
