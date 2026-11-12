import { Controller } from '@hotwired/stimulus';

/* Mobile menu: toggles the nav, closes on Escape and after navigating. */
export default class extends Controller {
    static targets = ['button', 'nav'];

    connect() {
        this.onKey = (event) => {
            if (event.key === 'Escape' && this.isOpen) {
                this.close();
                this.buttonTarget.focus();
            }
        };
        document.addEventListener('keydown', this.onKey);
    }

    disconnect() {
        document.removeEventListener('keydown', this.onKey);
    }

    get isOpen() {
        return this.buttonTarget.getAttribute('aria-expanded') === 'true';
    }

    toggle() {
        this.isOpen ? this.close() : this.open();
    }

    open() {
        this.buttonTarget.setAttribute('aria-expanded', 'true');
        this.navTarget.classList.add('is-open');
    }

    close() {
        this.buttonTarget.setAttribute('aria-expanded', 'false');
        this.navTarget.classList.remove('is-open');
    }
}
