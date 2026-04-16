import sortable from "./sortable.js";

document.addEventListener('alpine:init', () => {
    window.Alpine.plugin(sortable);
})
