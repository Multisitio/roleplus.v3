<?php
/**
 */
class Kumbia_css
{
    # 1
    public function links()
    {
        return [
			t('Empezando') => [
				'installation'=>t('Instalación'),
				'themes-and-colors'=>t('Temas y colores'),
			],
			t('Disposición') => [
				'containers'=>t('Contenedores'),
				'grid'=>t('Rejilla'),
				'horizontal-scroll'=>t('Desplazamiento horizontal'),
			],
			t('Elementos') => [
				'buttons'=>t('Botones'),
				'forms'=>t('Formularios'),
				'tables'=>t('Tablas'),
				'typographic'=>t('Tipografía'),
			],
			t('Componentes') => [
				'accordions'=>t('Acordeones'),
				'nav-bars'=>t('Barras de navegación'),
				'progress-bars'=>t('Barras de progreso'),
				'dropdowns'=>t('Listas desplegables'),
				'cards'=>t('Tarjetas'),
				'modals-windows'=>t('Ventanas modales'),
			],
			t('Utilidades') => [
				'loading'=>t('Cargando'),
				'tooltips'=>t('Información emergente'),
			],
			t('Clases') => [
			],
			t('Anexo') => [
				'inspiration'=>t('Inspiración'),
				'partners'=>t('Socios'),
			],
		];
    }
}
