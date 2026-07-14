## Project Rules
- Escribir siempre los mensajes de los commits (git commit) en español.
- Respetar en todo momento la arquitectura CSS modular (`public/css/modules/`). Para añadir nuevos estilos, se debe modificar el archivo de módulo correspondiente o crear uno nuevo, importándolo en el índice `style.css`. No se debe añadir código CSS suelto en `style.css`.
- Respetar la arquitectura MVC (Modelo-Vista-Controlador) del proyecto. El código de acceso a datos debe permanecer en los Modelos, la lógica de negocio y ruteo en los Controladores, y la interfaz de usuario en las Vistas.
- Mantener la coherencia visual del proyecto. Los nuevos elementos y componentes añadidos (botones, tarjetas, secciones) deben heredar y seguir estrictamente las reglas de CSS y diseño existentes (colores de la paleta actual, bordes definidos, sombras en estilo pixel-art/retro y tipografías establecidas) para no romper la estética general de la aplicación.
