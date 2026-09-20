const fs = require('fs');
const { exec } = require('child_process');
const path = require('path');

const folderToWatch = 'x:\\htdocs\\roleplus.app\\web';
const debounceTimers = {};

console.log(`Vigilando cambios en ${folderToWatch} para minificar automáticamente (Node.js)...`);

fs.watch(folderToWatch, { recursive: true }, (eventType, filename) => {
    if (!filename) return;
    
    // Solo css y js, excluir .min.css / .min.js
    if (!filename.match(/\.(css|js)$/) || filename.match(/\.min\.(css|js)$/)) return;

    const fullPath = path.join(folderToWatch, filename);

    // Debounce: cancelar el timer anterior si existe
    if (debounceTimers[fullPath]) {
        clearTimeout(debounceTimers[fullPath]);
    }

    // Programar la ejecución en 500ms
    debounceTimers[fullPath] = setTimeout(() => {
        delete debounceTimers[fullPath];
        
        // Verificar si el archivo realmente existe (podría haber sido eliminado)
        if (fs.existsSync(fullPath)) {
            console.log(`Minificando: ${fullPath}`);
            exec(`D:\\Minify\\minify.bat "${fullPath}"`, (error, stdout, stderr) => {
                if (error) {
                    console.error(`Error al minificar ${fullPath}:`, error);
                }
            });
        }
    }, 500);
});
