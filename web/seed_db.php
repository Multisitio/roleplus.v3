<?php
require '../private/config/databases.php';
$db = $databases['default'];
try {
    $pdo = new PDO($db['dsn'], $db['username'], $db['password'], $db['params']);
    
    // Insert selectores
    $pdo->exec("INSERT INTO plantillas_selectores (idu, nombre, selector) VALUES
        ('sel_h1', 'Encabezado principal', 'h1'),
        ('sel_p', 'Párrafos', 'p'),
        ('sel_btn', 'Botones', 'button, .button')
    ");

    // Insert propiedades
    $pdo->exec("INSERT INTO plantillas_propiedades (idu, nombre, propiedad) VALUES
        ('prop_color', 'Color de texto', 'color'),
        ('prop_bg', 'Color de fondo', 'background-color'),
        ('prop_size', 'Tamaño de fuente', 'font-size')
    ");

    // Insert valores
    $pdo->exec("INSERT INTO plantillas_valores (idu, propiedades_idu, nombre, valor) VALUES
        ('val_red', 'prop_color', 'Rojo', 'red'),
        ('val_blue', 'prop_color', 'Azul', 'blue'),
        ('val_bg_dark', 'prop_bg', 'Oscuro', '#333'),
        ('val_bg_light', 'prop_bg', 'Claro', '#f4f4f4'),
        ('val_s_small', 'prop_size', 'Pequeño', '12px'),
        ('val_s_large', 'prop_size', 'Grande', '20px')
    ");

    echo "Datos insertados.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
