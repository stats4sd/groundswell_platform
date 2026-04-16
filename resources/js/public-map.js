console.log('public map js loaded');

// create Vue app and mount to #public-map-app

import { createApp } from 'vue';
import PublicMap from './Components/PublicMap.vue';

document.documentElement.style.setProperty("--vs-menu-z-index", 2000);

createApp({})
    .component('publicMap', PublicMap)
    .mount('#public-map-app');
