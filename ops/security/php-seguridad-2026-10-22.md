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

## Resultado comprobado del parche

Actualizadas las 39 versiones fijadas: PHP 8.4.27 para las webs y PHP
8.1.34-9 para el panel y el manejador general. Los 17 pools ejecutan las
versiones nuevas, las once webs responden HTTP 200 y ROLEplus y MTGsearch
responden tambien desde el PC del operador. Las configuraciones de sitios,
los pools personalizados y los interpretes seleccionados se conservaron.
Apache se recargo; no se reinicio el VPS ni MariaDB.

La primera comprobacion posterior agoto el tiempo de espera de Contrólate.
Se conservo el resultado y se repitio exclusivamente la verificacion, sin
reinstalar ni recargar otra vez: once respuestas HTTP 200 y dpkg sin pendientes.
No se atribuye una causa al agotamiento puntual sin evidencia adicional.

## Resultado comprobado de la retirada

Las nueve ramas y sus 200 paquetes quedaron retirados. Las nueve opciones
correspondientes permanecen desactivadas en ISPConfig, conservando sus fichas.
La comprobacion posterior acredito once webs HTTP 200, los 17 pools nuevos,
ninguna rama retirada activa y dpkg sin operaciones pendientes. No se utilizo
autoremove. Se conservan PHP 8.4 y PHP 8.1 porque ambos tienen consumidores.
La migracion del manejador general y del panel a 8.4 sigue pendiente de la
actualizacion y validacion de ISPConfig; no se declara realizada.

## Cierre posterior: 23 de octubre de 2026

La situacion provisional anterior queda superada: ISPConfig 3.3.2p1 ya
esta actualizado y validado; panel y manejadores generales ejecutan PHP
8.4.27. Retirados los 20 paquetes restantes de PHP 8.1. Solo queda PHP 8.4.
Resultado y evidencias en ispconfig-php84-2026-10-23.md.
