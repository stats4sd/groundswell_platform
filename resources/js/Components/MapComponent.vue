<template>
    <div id="map" class="public-map h-full">
    </div>
</template>

<script setup>
import "leaflet/dist/leaflet.css"
import * as L from 'leaflet';
import 'leaflet.markercluster/dist/MarkerCluster.css';
import 'leaflet.markercluster/dist/MarkerCluster.Default.css';
import 'leaflet.markercluster';

import {onMounted, ref, watch} from "vue";
import 'leaflet.markercluster.freezable';

const initialMap = ref(null)

const props = defineProps({
    allCountries: {
        type: Array,
        required: true
    },
    selectedCountry: {
        type: Object,
        required: true
    },
    filteredResults: {
        type: Array,
        required: true
    },
})

const emit = defineEmits([
    'loadComplete'
])

watch(() => props.filteredResults, () => {
    updateMapMarkers();
})


const updateMapMarkers = function () {

    props.allCountries.forEach(country => country.markerClusterGroup.clearLayers());


    let uniqueCountryIds = [];

    if (props.selectedCountry.hasOwnProperty('value')) {
        uniqueCountryIds.push(props.selectedCountry.value);
    }

    // if no countries are selected, show all countries
    if (uniqueCountryIds.length === 0) {
        uniqueCountryIds = props.allCountries.map(country => country.value);
    }



    uniqueCountryIds.forEach(countryId => {

        const results = props.filteredResults.filter(result => result.country_id === countryId.toString());

        results.forEach(result => {
            if (result.latitude && result.longitude) {
                const marker = L.marker([result.latitude, result.longitude]);
                props.allCountries.find(country => country.value === countryId.toString())
                    .markerClusterGroup
                    .addLayer(marker);
            }
        });
    })

    // emit('loadComplete');

}


onMounted(() => {

    // Initialize the map when the component is mounted
    initialMap.value = L.map('map').setView([12.0, -1.8], 6);

    const temp = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'

    L.tileLayer(temp, {
        maxZoom: 18,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(initialMap.value);

    // set min zoom to 2
    initialMap.value.setMinZoom(2);
    initialMap.value.setMaxZoom(8);

    // // DEBUGGING TOOLS
    // initialMap.value.on('zoomend', function () {
    //
    //     const currentZoom = initialMap.value.getZoom();
    //
    //     // get the current zoom level
    //     console.log(currentZoom)
    //
    // });
    //
    // initialMap.value.on('moveend', function () {
    //     console.log('Map panned');
    //     console.log(initialMap.value.getCenter());
    // });
    //
    // initialMap.value.on('click', function (e) {
    //     console.log('Map clicked at ' + e.latlng);
    // });

    // set up country layers
    props.allCountries.forEach((country) => {
        country.markerClusterGroup = L.markerClusterGroup().addTo(initialMap.value);
    })

     updateMapMarkers();

})

</script>
