<template>
    <div style="height: 500px">
        <h4 class="mb-0">Agroecology Scores - {{ props.selectedCountry.label }}
        </h4>
    <Radar :data="radarChartData" :options="radarChartOptions"/>
    </div>
</template>

<script setup>


import {computed, ref} from "vue";
import { Radar} from 'vue-chartjs'
import {RadialLinearScale, PointElement, LineElement, Filler, BarController, BarElement, CategoryScale, Chart as ChartJS, Colors, Legend, LinearScale, Title, Tooltip} from 'chart.js';
import * as ss from 'simple-statistics'

ChartJS.register(Colors);


ChartJS.register(RadialLinearScale, PointElement, LineElement, Filler, CategoryScale, LinearScale, BarElement, BarController, Title, Tooltip, Legend);


const principles = ref([
    {
        label: 'Overall AE',
        value: 'ae',
    },
    {
        label: 'Recycling',
        value: 'recycling',
    },
    {
        label: 'Input Reduction',
        value: 'input_reduction',
    },
    {
        label: 'Soil Health',
        value: 'soil_health',
    },
    {
        label: 'Animal Health',
        value: 'animal_health',
    },
    {
        label: 'Biodiversity',
        value: 'biodiversity',
    },
    {
        label: 'Synergy',
        value: 'synergy',
    },
    {
        label: 'Economic Diversification',
        value: 'economic_diversification',
    },
    {
        label: 'Co-creation of Knowledge',
        value: 'co_creation_knowledge',
    },
    {
        label: 'Governance',
        value: 'governance',
    },
    {
        label: 'Social Values and Diet',
        value: 'social_values_diet',
    },
    {
        label: 'Fairness',
        value: 'fairness',
    },
    {
        label: 'Connectivity',
        value: 'connectivity',
    },
    {
        label: 'Participation',
        value: 'participation',
    },
])

const props = defineProps({
    filteredResults: {
        type: Array,
        required: true
    },
    selectedCountry: {
        type: Object,
        required: true
    }
})


const radarChartData = computed(() => {

    const countryLabel = props.selectedCountry?.value ? props.selectedCountry.label : "All Countries";

    const labels = principles.value
        .filter(principle => principle.value !== 'ae')
        .map(principle => principle.label);
    return {
        labels: labels,
        datasets: [
            {
                label: countryLabel + ' AE Scores',
                data: principles.value
                    .filter(principle => principle.value !== 'ae')
                    .map(principle => {
                        const principle_overall_variable = 'overall_' + principle.value.toLowerCase().replace(/ /g, '_') + '_score';
                        const filteredData = props.filteredResults
                            .map(result => result[principle_overall_variable]);

                        if (filteredData.length === 0) {
                            return 0;
                        }

                        // filter out NAs
                        const filteredDataNoNA = filteredData.filter(value => !isNaN(value) && value !== null);

                        return ss.mean(filteredDataNoNA);
                    }),
                fill: true,
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgb(54, 162, 235)',
                pointBackgroundColor: 'rgb(54, 162, 235)',
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: 'rgb(54, 162, 235)'
            }
        ]
    };
})

const radarChartOptions = ref({
    maintainAspectRatio: false,
    scales: {
        r: {
            beginAtZero: true,
            max: 5,
            ticks: {
                stepSize: 1
            },
            pointLabels: {
                font: {
                    size: 14,
                    weight: 'bold'
                }
            }
        }
    },
    plugins: {
        legend: {
            display: false
        }
    },
})

</script>

<style scoped>

</style>
