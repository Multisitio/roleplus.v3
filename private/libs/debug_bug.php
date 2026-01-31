<?php
// El contenido exacto del usuario
$input = <<<'HTML'
<article id="sec">
    <section>
        <div>
			<h4>¿Cómo jugar? Secuencia completa</h4>
			<p>Los jugadores, los protagonistas de la historia y el guardián del juego, deberían seguir una estructura de juego similar a esta. Útil para nuevos jugadores de rol.</p>
			<h5>Secuencia</h5>
			<p>Entiéndase por secuencia, el flujo de juego a seguir por fases. Las fases agrupan diferentes momentos a tener en cuenta.</p>
			<h6>Fase 0</h6>
			<ol>
				<li><b>Ambientación:</b> Una vez están todas las personas alrededor de una mesa o frente al ordenador, el jugador que hace de director del juego (DJ) cuenta a los demás a qué ambientación se va a jugar.</li>
				<li><b>Arquetipos:</b> Después, el DJ muestra todos los arquetipos de personaje (libretos) disponibles a los jugadores para que estos elijan uno diferente cada uno.</li>
				<li><b>Personalización:</b> Con el libreto en posesión de cada jugador, estos lo rellenan con las explicaciones escritas en los libretos y la ayuda del DJ.</li>
				<li><b>Presentación:</b> Una vez cumplimentados, cada jugador presenta su personaje al resto, pero solo lo que ven y saben los demás, como la apariencia, cómo viste, los objetos que porta y alguna peculiaridad que le hace único y que es difícil esconder.</li>
				<li><b>Preguntas:</b> Presentados los personajes, el DJ hará una ronda de turnos preguntado a cada jugador sobre su personaje y tomando notas sobre las motivaciones o deseos de estos, sus contactos y asuntos pendientes.</li>
			</ol>

			<h6>Fase 1</h6>
        </div>

        <div>
        </div>
    </section>

    <footer>
		¿Cómo jugar? Secuencia completa
    </footer>
</article>
HTML;

function test_cleaning($input) {
    if (trim($input) === '') return '';

    $internalErrors = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    
    // Configuración actual en GeneralController (sin flags)
    // Hack UTF-8
    $dom->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $input);
    
    // Limpieza (simulada, igual no modifica nada si no hay scripts)
    // ...

    // Extracción
    $body = $dom->getElementsByTagName('body')->item(0);
    $output = '';
    if ($body) {
        foreach ($body->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }
    }
    
    libxml_clear_errors();
    libxml_use_internal_errors($internalErrors);
    
    return $output;
}

$cleaned = test_cleaning($input);

echo "INPUT LENGTH: " . strlen($input) . "\n";
echo "OUTPUT LENGTH: " . strlen($cleaned) . "\n";
echo "OUTPUT START: " . substr($cleaned, 0, 100) . "...\n";

if (strlen($cleaned) < 100) {
    echo "FULL OUTPUT: [$cleaned]\n";
}
