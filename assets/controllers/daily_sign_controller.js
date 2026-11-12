import { Controller } from '@hotwired/stimulus';

/*
 * Sign of the day, browser side:
 *  - keeps the visitor's choice in a 1-year cookie (or deletes it after "Смени");
 *  - counts the number up when a sign has just been revealed.
 * The server reads the cookie on the next visit; it never stores the choice itself.
 */
export default class extends Controller {
    static values = { cookieName: String, cookieValue: String, number: Number, animate: Boolean };
    static targets = ['number'];

    connect() {
        this.cookieValueValue ? this.writeCookie(this.cookieValueValue) : this.deleteCookie();

        if (this.animateValue && this.hasNumberTarget && !this.reducedMotion()) {
            this.countUp();
        }
    }

    disconnect() {
        cancelAnimationFrame(this.frame);
        clearTimeout(this.safety);
    }

    writeCookie(value) {
        const secure = location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = `${this.cookieNameValue}=${value}; Max-Age=${60 * 60 * 24 * 365}; Path=/; SameSite=Lax${secure}`;
    }

    deleteCookie() {
        if (document.cookie.split('; ').some((c) => c.startsWith(`${this.cookieNameValue}=`))) {
            document.cookie = `${this.cookieNameValue}=; Max-Age=0; Path=/; SameSite=Lax`;
        }
    }

    countUp() {
        const target = this.numberValue;
        const duration = 900;
        const start = performance.now();
        const step = (now) => {
            const t = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - t, 3);
            this.numberTarget.textContent = String(Math.max(1, Math.round(eased * target)));
            if (t < 1) {
                this.frame = requestAnimationFrame(step);
            }
        };
        this.numberTarget.textContent = '1';
        this.frame = requestAnimationFrame(step);
        // Animation frames pause in background tabs: never leave a wrong number on screen.
        this.safety = setTimeout(() => {
            cancelAnimationFrame(this.frame);
            this.numberTarget.textContent = String(target);
        }, duration + 150);
    }

    reducedMotion() {
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }
}
