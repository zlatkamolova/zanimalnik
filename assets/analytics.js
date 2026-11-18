// GoatCounter counts the first page by itself when its script loads. With Turbo the next pages
// are swapped in without a full reload, so each later "turbo:load" is counted here.
let firstLoad = true;

document.addEventListener('turbo:load', () => {
    if (firstLoad) {
        firstLoad = false;
        return;
    }
    window.goatcounter?.count?.({ path: location.pathname + location.search });
});
