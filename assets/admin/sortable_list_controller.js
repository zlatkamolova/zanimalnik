import { Controller } from '@hotwired/stimulus';
import Sortable from 'sortablejs';

/*
 * Drag & drop list that saves its order right after every drop.
 *
 *   <ul data-controller="sortable-list"
 *       data-sortable-list-url-value="…" data-sortable-list-token-value="…">
 *     <li data-id="3">…</li>
 *   </ul>
 *   <p data-sortable-list-target="status"></p>   (optional, inside the controller element)
 */
export default class extends Controller {
    static values = { url: String, token: String };
    static targets = ['list', 'status'];

    connect() {
        this.sortable = Sortable.create(this.listTarget, {
            animation: 150,
            handle: '[data-handle]',
            ghostClass: 'is-ghost',
            onEnd: () => this.save(),
        });
    }

    disconnect() {
        this.sortable?.destroy();
    }

    // Keyboard alternative: buttons inside each item
    up(event) {
        const item = event.currentTarget.closest('[data-id]');
        if (item.previousElementSibling) {
            item.parentNode.insertBefore(item, item.previousElementSibling);
            event.currentTarget.focus();
            this.save();
        }
    }

    down(event) {
        const item = event.currentTarget.closest('[data-id]');
        if (item.nextElementSibling) {
            item.parentNode.insertBefore(item.nextElementSibling, item);
            event.currentTarget.focus();
            this.save();
        }
    }

    async save() {
        const ids = [...this.listTarget.querySelectorAll('[data-id]')].map((el) => el.dataset.id);
        this.status('Запазване…');
        try {
            const response = await fetch(this.urlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.tokenValue },
                body: JSON.stringify({ ids }),
            });
            const data = await response.json();
            this.status(response.ok ? 'Подредбата е запазена.' : (data.error || 'Грешка при запазване.'), !response.ok);
        } catch {
            this.status('Няма връзка със сървъра. Опитайте отново.', true);
        }
    }

    status(text, isError = false) {
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = text;
            this.statusTarget.classList.toggle('is-error', isError);
        }
    }
}
