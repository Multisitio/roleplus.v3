// Rebuild just this component inside the existing bundle, preserving the other
// components and their order (the legacy bundle has no full build manifest).
const fs = require('fs');
const path = require('path');
const terser = require(require.resolve('terser', { paths: [process.cwd(), 'D:/Minify/node_modules'] }));
const root = path.resolve(__dirname, '../..');
(async () => {
    const target = path.join(root, 'web/javascript/principal.min.js');
    const bundle = fs.readFileSync(target, 'utf8');
    const start = bundle.indexOf('Kumbia.utils.on("paste","textarea",');
    const end = bundle.indexOf('window.replaceAccents=', start);
    if (start < 0 || end < start) throw new Error('Drop component boundaries missing');
    const source = fs.readFileSync(path.join(root, 'web/javascript/principal/4-drop-or-paste-image.js'), 'utf8');
    const { code } = await terser.minify(source);
    fs.writeFileSync(target, bundle.slice(0, start) + code.replace(/;$/, ',') + bundle.slice(end));
})();
