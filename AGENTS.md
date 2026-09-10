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

- Ante un fallo visual en un manual, inspeccionar el elemento real y sus estilos
  efectivos en el manual afectado antes y despues del cambio. Una prueba del
  selector de subida o de una maqueta aislada no valida el resultado del manual.
- No cambiar el encaje de las previsualizaciones del drop como sustituto de
  corregir el tamano de las imagenes en el documento.
- Respetar el destino indicado por el usuario para una imagen (por ejemplo,
  footer); si aparece guardada en otro campo, investigar el guardado y corregir
  la asignacion, sin reinterpretarla como una decision de usarla como fondo.
- En las cajas drop, mostrar la imagen completa ajustada al ancho o al alto
  disponible, conservando proporciones y sin recortes ni desbordamiento
  (`contain`). Este encaje de previsualizacion es independiente del manual.
