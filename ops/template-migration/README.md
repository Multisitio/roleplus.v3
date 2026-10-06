# Plantillas de BD

`srd20`, `for_the_quest` y `dragonbane` se importan a `plantillas_reglas`.
Los archivos de `sources/` son referencias para regenerar la importación,
no hojas que cargue el editor. No hay dependencia de esos archivos en ejecución.

`build.py` necesita tinycss2 y genera los tres JSON. Conserva el orden de la
cascada, las variantes de fuentes, SVG, tarjetas y excepciones por formato.
Los comentarios de bloque distinguen reglas repetidas sin modificar el esquema
ni infringir su índice único sobre plantilla, selector y propiedad.

Los valores medidos en los JSON `*-computed.json` alimentan los controles
habituales. Los tamaños que no estaban en sus listas también se muestran.
Las excepciones según tarjeta, posición o formato siguen siendo reglas editables
en «Detalles decorativos». Los ajustes habituales solo añaden una modificación
cuando difieren de su valor importado.

La marca `--componentes-version=1` protege esas reglas frente a la limpieza
de variables. Sin esa marca, la compilación y la limpieza mantienen el contrato
anterior. `.plantilla` es únicamente la clave interna de variables en BD;
los selectores del documento usan `main`.

La migración requiere un bootstrap CLI del entorno que cargue Config, el ORM
y los modelos, y una ruta nueva de copia de seguridad fuera del directorio web:

```
php migrate.php bootstrap.php copia-nueva.json
php verify.php bootstrap.php baseline.json
```

La copia contiene las filas originales. La importación es transaccional e
idempotente y no modifica manuales, páginas ni imágenes. Conserva las variables
personalizadas que difieren del antiguo formulario vacío; reconoce equivalencias
como `20` y `20px`. Los datos existentes de cada entorno se conservan por separado.
El catálogo con propietario `catalogo` permite crear nuevas copias completas.

`verify.php` prueba ajustes, conservación de adornos, duplicación, creación desde
el catálogo y rechazo de declaraciones que escapen de su selector. Revierte sus
cambios. Una referencia opcional de CSS permite contrastar las plantillas
ordinarias antes y después. El CSS por usuario se genera desde BD y no se versiona.
