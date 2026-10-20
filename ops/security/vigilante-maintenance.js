'use strict';
document.addEventListener('DOMContentLoaded', () => {
    const estimate = document.querySelector('[data-estimate]');
    const detail = document.querySelector('[data-maintenance-detail]');
    let timer;
    let running = false;
    async function check() {
        if (running) return;
        running = true;
        clearTimeout(timer);
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 4000);
        try {
            const response = await fetch('/__vigilante_maintenance?status=1', {
                cache: 'no-store', headers: { Accept: 'application/json' }, signal: controller.signal
            });
            if (!response.ok) throw new Error('No se pudo comprobar el servicio');
            const data = await response.json();
            if (!data.active) {
                estimate.textContent = 'finalizado';
                detail.textContent = 'El servicio vuelve a estar disponible.';
                if (!location.pathname.startsWith('/__vigilante_maintenance')) location.reload();
                return;
            }
            estimate.textContent = data.estimated_seconds > 0 ? `${data.estimated_seconds} segundos` : 'finalizando';
            detail.textContent = data.estimated_seconds > 0 ? 'Esta página se actualizará automáticamente.'
                : `El modo de solo lectura continúa. Límite de esta fase: ${data.max_seconds} segundos.`;
        } catch (error) {
            detail.textContent = 'Estamos esperando confirmación del servicio. No cierres tu trabajo.';
        } finally {
            clearTimeout(timeout);
            running = false;
            timer = setTimeout(check, 3000);
        }
    }
    document.querySelector('[data-maintenance-retry]').addEventListener('click', check);
    check();
});
