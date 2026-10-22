# PHP: seguridad y reduccion de ramas, 22 de octubre de 2026

La configuracion de produccion es la fuente de verdad de los consumidores.
Los ejecutores administrativos se mantienen primero en R2-D2 y se publican
en GitHub antes de instalarlos. Los valores privados y las pruebas completas
permanecen en /root/codex-vigilante/php-security-2026-10-22.

## Inventario previo

Todas las webs de ISPConfig tienen server_php_id=11 (PHP 8.4). Los nombres
historicos de algunos sockets contienen php8.1, pero sus procesos son 8.4;
no cambiar sus rutas por una deduccion basada en el nombre.

El panel ISPConfig 3.2.12p1 utiliza PHP CGI 8.1 y Apache conserva el manejador
general 8.1. El interprete CLI principal es 8.4. Las dos unidades Adapterman
mantienen procesos PHP CLI de larga duracion y requieren activacion expresa
tras actualizar el binario. No basta con recargar sus trabajadores.

## Parches

El inventario firmado de APT contiene PHP 8.4.27 y PHP 8.1.34-9 de Sury.
Los registros de cambios de ambos paquetes acreditan las correcciones de
CVE-2026-109618 y CVE-2026-109619. La rama 8.1 recibe en este repositorio las
correcciones adaptadas por el proveedor; su numero no debe compararse con
el de una version oficial de otra rama.

El ejecutor fija las 39 versiones de paquetes utilizadas, comprueba sus
huellas con el indice firmado y prueba los binarios nuevos y las
configuraciones FPM antes de instalar. Conserva las configuraciones y evita
reinicios automaticos desde los paquetes. Activa FPM y Apache mediante recarga
y los dos procesos persistentes mediante el control central. Verifica cada
pool realmente ejecutado y las once webs del inventario del vigilante.

## Ramas sin consumidores

Se identificaron nueve ramas (5.6, 7.0, 7.1, 7.2, 7.3, 7.4, 8.0, 8.2, 8.3)
con 200 paquetes, sin asignaciones en ISPConfig ni referencias activas en
Apache, tareas programadas o servicios personalizados. Su retirada tiene
simulacion exacta, parada centralizada, copia textual de configuraciones y
opciones del panel, y verificacion posterior. No utiliza autoremove.
Las opciones retiradas se desactivan en ISPConfig, conservando sus fichas.

## Unificacion del manejador en PHP 8.4

La entrada y el formulario de acceso del panel se han ejecutado aisladamente
con CGI 8.1 y 8.4: sin errores y con los mismos modulos disponibles.
Esta prueba no acredita las operaciones administrativas de ISPConfig 3.2.12p1.
La migracion del panel y del manejador general queda condicionada a actualizar
y validar ISPConfig, comprobar el socket generico de destino y conservar sus
rutas en el panel. No basta con cambiar update-alternatives ni el nombre de
un socket. Mientras tanto se conserva 8.1 parcheado, sin ramas inutilizadas.

Referencias oficiales:
- https://www.php.net/releases/8_4_27.php
- https://www.ispconfig.org/blog/ispconfig-3-3-1-released/

El resultado real se recoge tras cada ejecucion en verification.json y
retirement-verification.json privados; este documento describe las barreras
y no sustituye esos resultados.
