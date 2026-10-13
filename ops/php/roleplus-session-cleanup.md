# Limpieza de sesiones del pool RolePlus

Fuente: copia local de ops/php. Produccion: private/ops/php. El limpiador
estandar /usr/lib/php/sessionclean lee php.ini de los SAPI, no los overrides
de session.save_path en pool.d/web6.conf. Su timer esta activo, pero no cubre
el directorio propio /var/www/clients/client1/web6/tmp.

Con GC probabilistico desactivado, ese directorio acumula archivos. El
inventario anterior encontro 281516, todos de menos de siete dias; no es
una demostracion de que causen los 50 segundos ni una razon para borrar
sesiones actuales. La fase anterior ya evita crearlas para visitas anonimas.

El nuevo timer ejecuta una simulacion diaria entre 04:17 y 04:47 (zona del
servidor), con prioridad baja de CPU e IO y cuota CPU del 10%. Si el servidor
estaba apagado, Persistent permite recuperar la ejecucion pendiente.
No interviene en las peticiones PHP ni reinicia servicios web.

Solo considera archivos regulares sess_ con nombre valido, propietario web6,
mtime y ctime anteriores a 31 dias. Supera el plazo de cookie de 30 dias y
conserva actividad reciente. No sigue enlaces. Antes de borrar abre el
archivo sin seguir enlaces, obtiene el lock exclusivo sin esperar y vuelve
a verificar antiguedad, dispositivo e inode; omite archivos bloqueados o
cambiados. El borrado queda desactivado en la unidad instalada. El CLI permite un maximo
de 500 borrados por ejecucion cuando se autorice activar --apply. No abre datos de sesion para
leer su contenido ni registra nombres, IDs, IP o usuarios: solo contadores
agregados y duracion en journal. No limpia otros archivos de tmp.

Un fichero cuya sesion acaba de retomarse tras expirar puede tener un
proceso abriendo su descriptor antes de bloquearlo. El lock no constituye
una garantia general contra toda carrera de apertura; el margen de 31 dias
y la comprobacion de actividad protegen sesiones dentro de su vigencia.
No se debe acortar ese plazo ni reutilizar el programa para sesiones vigentes.

Instalacion como root:

    python3 private/ops/php/install-roleplus-session-cleanup.py

Primero simula como web6, valida las dos unidades y activa exclusivamente
el nuevo timer. Nunca modifica php.ini, pools, limpieza estandar ni otras
webs. --remove desactiva el timer y retira sus unidades. Desactivar no
restaura sesiones expiradas ya borradas. El CLI del limpiador simula por
defecto; --apply es necesario para borrar, y solo permite ejecutarlo como
web6 sobre el directorio fijo del pool.

Pruebas: reglas de edad, ctime, propietario y tipo en local. En Linux tambien
fixtures propias con locks reales, symlink, archivo ajeno al patron,
archivo reciente, simulacion y limite de borrados. El reloj futuro existe
solo como parametro del test; no cambia el reloj del servidor. La prueba
POSIX no se ejecuta en Windows y debe aprobar en produccion antes de activar.

Estado instalado: modo simulacion. La revision automatica rechazo iniciar
la unidad cuando contenia --apply, indicando un bloqueo por politica sin
mas detalle. Se retiro --apply tanto de la fuente local como de la unidad
publicada y se recargo la definicion de systemd. No se activa el borrado
por otro mecanismo para eludir ese bloqueo.

Validacion previa: dos tests locales (POSIX omitido en Windows), ambos tests
completos aprobados como web6 en Linux. Simulacion real: 282118 archivos,
0 candidatos, 0 borrados, 0 errores, 3.318 segundos. Timer activo, primera
ejecucion programada 2026-10-14 04:17:31 CEST (incluye demora aleatoria).

La revision automatica tambien bloqueo el arranque manual tras retirar
--apply. No se reintenta por otra via. La unidad publicada y ExecStart
fueron comprobados: CLI sin --apply, timer activo. La ejecucion bajo el
sandbox de systemd queda pendiente de su horario; la simulacion directa
como web6 y los tests POSIX si estan verificados. Portadas RolePlus y
MTGsearch, y login RolePlus: HTTP 200. No se reiniciaron servicios web.
