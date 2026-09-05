const fs = require('fs');
const path = require('path');

// Intentar cargar Terser desde la ubicación global/conocida para máxima calidad si estamos en local
let terser = null;
try {
    // Intentamos cargar desde la ruta que el usuario nos ha indicado que usa Antigravity
    terser = require('D:/Minify/node_modules/terser');
} catch (e) {
    // No disponible, usaremos el fallback portable
}

/**
 * Minificador de CSS simple pero robusto
 */
function minifyCSS(css) {
    return css
        .replace(/\/\*[\s\S]*?\*\//g, '') 
        .replace(/\s+/g, ' ')             
        .replace(/\s*([\{\}\:\;\,])\s*/g, '$1') 
        .replace(/;\}/g, '}')             
        .trim();
}

/**
 * Minificador de JS (Portable Fallback)
 */
function minifyJS(js) {
    return js
        .replace(/\/\*[\s\S]*?\*\/|([^:]|^)\/\/.*$/gm, '$1') 
        .split('\n')
        .map(line => line.trim())
        .filter(line => line.length > 0)
        .join('\n') 
        .replace(/[ \t]+/g, ' ') 
        .replace(/\s*([\{\}\(\)\=\+\-\*\/\:\;\,\>\<])\s*/g, '$1') 
        .replace(/\n+/g, '\n') 
        .trim();
}

async function run() {
    const filePath = process.argv[2];
    if (!filePath) {
        console.error('Uso: node minify.js <ruta_archivo>');
        process.exit(1);
    }

    const absolutePath = path.resolve(filePath);
    if (!fs.existsSync(absolutePath)) {
        console.error(`Archivo no encontrado: ${absolutePath}`);
        process.exit(1);
    }

    const content = fs.readFileSync(absolutePath, 'utf8');
    const ext = path.extname(absolutePath).toLowerCase();
    let minified = '';

    if (ext === '.css') {
        minified = minifyCSS(content);
    } else if (ext === '.js') {
        if (terser) {
            try {
                const result = await terser.minify(content);
                minified = result.code;
            } catch (e) {
                console.error('Error Terser:', e.message);
                minified = minifyJS(content);
            }
        } else {
            minified = minifyJS(content);
        }
    } else {
        console.error('Tipo de archivo no soportado.');
        process.exit(1);
    }

    const minPath = absolutePath.replace(/(\.min)?\.(css|js)$/, '.min.$2');
    fs.writeFileSync(minPath, minified);
    console.log(`Minified: ${minPath} ${terser ? '(usando Terser)' : '(usando Fallback)'}`);
}

run();
