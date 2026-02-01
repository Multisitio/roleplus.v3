class KumbiaPHP {
    constructor() {
        this.selector = document.querySelectorAll('.selector');
        this.form = document.querySelectorAll('.form');
    }

    sendForm(event) {
        event.preventDefault();
        let form = event.target;
        let formData = new FormData(form);
        let xhr = new XMLHttpRequest();
        xhr.open(form.method, form.action);
        xhr.onload = () => {
            if (xhr.status === 200) {
                console.log('Formulario enviado');
            } else {
                console.error('Error al enviar el formulario');
            }
        }
        xhr.send(formData);
    }

    addListeners() {
        this.form.forEach(form => {
            form.addEventListener('submit', this.sendForm);
        });
    }

    /* Funciones reescritas en JavaScript puro */

    // Función que muestra un mensaje de alerta
    alert(selector, message, type) {
        let alert = document.createElement('div');
        alert.classList.add('alert', `alert-${type}`);
        alert.innerHTML = message;
        document.querySelector(selector).appendChild(alert);
    }

    // Función que muestra un mensaje de confirmación y ejecuta una acción
    confirm(selector, message, callback) {
        let confirm = document.createElement('div');
        confirm.classList.add('confirm');
        confirm.innerHTML = `
      <p>${message}</p>
      <button class="btn-primary btn-confirm">Aceptar</button>
      <button class="btn-secondary btn-cancel">Cancelar</button>
    `;
        document.querySelector(selector).appendChild(confirm);

        let btnConfirm = confirm.querySelector('.btn-confirm');
        let btnCancel = confirm.querySelector('.btn-cancel');

        btnConfirm.addEventListener('click', () => {
            callback();
            confirm.remove();
        });

        btnCancel.addEventListener('click', () => {
            confirm.remove();
        });
    }

    // Función que muestra un modal
    modal(id) {
        let modal = document.getElementById(id);
        let btnClose = modal.querySelector('.btn-close');
        modal.classList.add('show');
        btnClose.addEventListener('click', () => {
            modal.classList.remove('show');
        });
    }

    // Función que muestra un mensaje de carga
    loading(selector, message) {
        let loading = document.createElement('div');
        loading.classList.add('loading');
        loading.innerHTML = `
        <div class="spinner"></div>
        <p>${message}</p>
      `;
        document.querySelector(selector).appendChild(loading);
    }

    // Función que oculta el mensaje de carga
    loaded(selector) {
        document.querySelector(selector).innerHTML = '';
    }

    // Función que muestra una notificación
    notify(message, type) {
        let notify = document.createElement('div');
        notify.classList.add('notify', `notify-${type}`);
        notify.innerHTML = message;
        document.body.appendChild(notify);
        setTimeout(() => {
            notify.remove();
        }, 3000);
    }

    // Función que muestra una barra de progreso
    progress(selector, value) {
        let progress = document.querySelector(selector);
        progress.style.width = value + '%';
    }
}

const kumbiaPHP = new KumbiaPHP();
kumbiaPHP.addListeners();