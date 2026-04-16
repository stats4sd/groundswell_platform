<template>

    <!-- A temporary overlay that covers the whole page content, greying out the content behind it and explaining that the dashboard will be available shortly -->
    <div v-if="showOverlay" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center" style="z-index:50000">
        <div class="bg-white p-8 rounded-lg shadow-lg max-w-md text-center">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Dashboard Coming Soon</h3>
            <p class="text-gray-600 mb-4">We are currently validating the agroecology scores results. The dashboard will be available here when that is complete. Please check back in a few days.</p>

            <p class="mb-8">
                <a href="/" class="text-blue-500 underline">Return to Home Page</a>
            </p>

            <p class="text-gray-600 text-xs">Last updated 2025-12-19</p>
        </div>
    </div>

    <div class="w-full max-w-7xl mx-auto space-y-4">

        <!-- SUMMARY -->
        <div class="rounded-lg p-0 grid grid-cols-12 space-x-4">

            <div class="border border-green col-span-12 md:col-span-5 p-4">
                <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center space-x-8">
                    <span>Showing</span>
                    <VueSelect
                        v-model="selectedCountryId"
                        :is-multi="false"
                        :options="allCountries"
                        placeholder="All Countries"
                        class="menu"
                        :clearable="false"
                    />
                    <!-- Temporarily disabled, until we get more countries -->
                </h3>

                <!-- FILTERS -->
                <div class="p-0 mb-8 w-full">
                    <div class="space-y-2">
                        <VueSelect
                            v-model="selectedGender"
                            :is-multi="false"
                            :options="[
                        { label: 'Female-headed farm households', value: 'female' },
                        { label: 'Male-headed farm households', value: 'male' },
                    ]"
                            placeholder="Filter by gender"
                        />
                    </div>

                </div>
            </div>

            <div class="col-span-12 md:col-span-5 md:col-start-7 lg:col-span-3">
                <div class="h-full space-y-4 flex md:flex-col w-full">
                    <div class="border border-green p-4">
                        <h2 class="font-bold text-nowrap mb-2">{{ filteredResults.length }}</h2>
                        <h5 class="mt-0">Farms Surveyed</h5>
                    </div>
                    <div class="border border-green p-4 w-full">
                        <h2 class="font-bold text-nowrap mb-2">{{ totalProductionArea }} ha</h2>
                        <h5 class="mt-0">Total Production Area
                            <br/>Assessed
                        </h5>
                    </div>
                </div>
            </div>
            <div class="border border-green pt-4 pb-0 px-4 h-full col-span-12 lg:col-span-4">
                <SummaryChart :current-value="averageScore"/>
                <h5 class="text-center mt-2">Avg. Agroecology Score</h5>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-8">

            <div class="col-span-1 p-0 border border-green">
                <MapComponent
                    :allCountries="allCountries"
                    :selectedCountry="selectedCountry"
                    :filteredResults="deferredFilteredResults"
                />
            </div>

            <div class="col-span-1 p-4 border border-green">
                <CountryComparisonChartsComponent
                    v-if="!selectedCountryId"
                    :allCountries="allCountries"
                    :filteredResults="deferredFilteredResults"
                />
                <SubCountryComparisonChartsComponent
                    v-if="selectedCountryId"
                    :selectedCountry="selectedCountry"
                    :filteredResults="deferredFilteredResults"
                    :selected-gender="selectedGender"

                />
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-8">
            <div class="col-span-1 p-4 border border-green">
                <RadarChartComponent
                    :filteredResults="deferredFilteredResults"
                    :selected-country="selectedCountry"
                />
            </div>
        </div>

        <div class="absolute w-full h-full top-32 left-0 bg-gray-200 opacity-80" v-show="loadingState" style="z-index:99999;"></div>
        <div class="mx-auto" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index:100000;">
            <pulse-loader :loading="loadingState" :size="'100px'"/>
        </div>
    </div>
</template>

<script setup>
import {computed, nextTick, onMounted, ref, watch} from 'vue';
import axios from 'axios';
import PulseLoader from 'vue-spinner/src/PulseLoader.vue'
import VueSelect from "vue3-select-component";
import "vue3-select-component/styles";
import MapComponent from "./MapComponent.vue";
import CountryComparisonChartsComponent from "./CountryComparisonChartsComponent.vue";
import SummaryChart from "./SummaryChart.vue";
import SubCountryComparisonChartsComponent from "./SubCountryComparisonChartsComponent.vue";
import RadarChartComponent from "./RadarChartComponent.vue";

const showOverlay = ref(false); // Set to false to hide the overlay

const allCountries = ref([
    {
        label: 'Burkina Faso',
        value: '854'
    },
    // {
    //     label: 'India',
    //     value: '356'
    // },
    // {
    //     label: 'Kenya',
    //     value: '404'
    // },
    // {
    //     label: 'Laos',
    //     value: '418'
    // },
    // {
    //     label: 'Peru',
    //     value: '604'
    // },
    // {
    //     label: 'Senegal',
    //     value: '686'
    // },
    // {
    //     label: 'Tunisia',
    //     value: '788'
    // },
    // {
    //     label: 'Zimbabwe',
    //     value: '716'
    // },
]);

// As of Jan 02 2026, we have only Burkina Faso data available
const selectedCountryId = ref("854");

const selectedCountry = computed(() => {
    return allCountries.value.find(country => country.value === selectedCountryId.value) || {};
});

const loadingState = ref(false);

// const checkLoadingComplete = function () {
//     if (!mapLoading.value && !chartLoading.value) {
//         loadingState.value = false;
//     }
// }
// const mapLoadComplete = function () {
//     mapLoading.value = false;
//     checkLoadingComplete()
// }
//
// const chartsLoadComplete = function () {
//     chartLoading.value = false;
//     checkLoadingComplete()
// }


const allResults = ref([]);

const selectedGender = ref(null);

const filteredResults = computed(() => {


    let newResults = allResults.value;

    if (selectedCountry.value.hasOwnProperty('value')) {
        newResults = newResults.filter(result =>
            selectedCountry.value.value === result.country_id.toString()
        );
    }

    if (selectedGender.value) {
        newResults = newResults.filter(result => result.gender === selectedGender.value);
    }

    // TODO: add farm size
    return newResults;
}, {deep: true});


const averageScore = computed(() => {
    if (filteredResults.value.length === 0) {
        return 0;
    }
    const totalScore = filteredResults.value.reduce((sum, result) => sum + parseFloat(result.overall_ae_score), 0);
    return (totalScore / filteredResults.value.length).toFixed(2);
});

const deferredFilteredResults = ref([]);

// Watch the filters and set loading state before the computed updates
watch([selectedCountryId, selectedGender], async () => {

    console.log('Filters changed, updating deferred results...');
    loadingState.value = true;

    await nextTick();
    setTimeout(() => {

        deferredFilteredResults.value = filteredResults.value;
        loadingState.value = false;
    }, 250);

}, {deep: true});

// You can add any necessary imports or setup logic here
onMounted(() => {

    loadingState.value = true;
    // get TempResults data
    axios.get('/temp-results')
        .then(response => {
            allResults.value = response.data;

            if (deferredFilteredResults.value.length === 0) {
                deferredFilteredResults.value = allResults.value;
            }

            setTimeout(() => {

                deferredFilteredResults.value = filteredResults.value;
                loadingState.value = false;
            }, 250);
        })
        .catch(error => {
            console.error('Error fetching TempResults:', error);
        })


});


const totalProductionArea = computed(() => {
    return filteredResults.value.reduce((sum, result) => sum + parseFloat(result.farm_size), 0).toFixed(2);
});

</script>

<style>
.menu {
    z-index: 2000;
}
</style>
