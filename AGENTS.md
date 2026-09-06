# Instrucciones del proyecto RolePlus

- La copia local de `X:\htdocs\roleplus.app` es la fuente de verdad. Aplicar
  primero aquí los cambios, verificarlos y solo después replicarlos en
  producción.
- La excepción son las imágenes y demás archivos generados por usuarios, junto
  con sus referencias en la base de datos: su fuente de verdad es producción y
  no se exige descargar ni mantener una réplica local de ese contenido.
- Al analizar imágenes, distinguir siempre entre el original guardado durante
  la subida y las miniaturas que `ImgController` puede generar al vuelo. No
  concluir que RolePlus no redimensiona basándose únicamente en el uploader.
- Mantener las URLs históricas de imágenes y el generador dinámico de
  miniaturas mientras exista contenido antiguo que dependa de ellos.
- Todos los cambios deben incluir su propio commit y push ejecutados por el agente. No delegar esta tarea al usuario.
