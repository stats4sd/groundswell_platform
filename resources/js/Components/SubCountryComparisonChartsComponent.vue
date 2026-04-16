<template class="flex flex-col">
    <div class="flex justify-between">
        <h4 class="mb-4">Agroecology Scores
            <br/> {{ props.selectedCountry.label }}
        </h4>
        <div class="mr-4">
            <input type="radio" id="bar" value="bar" v-model="chartType" class="mr-1">
            <label for="bar" class="mr-4">Bar Chart</label>
            <input type="radio" id="violin" value="violin" v-model="chartType" class="mr-1">
            <label for="violin">Violin Plot</label>
            <input type="radio" id="boxplot" value="boxplot" v-model="chartType" class="mr-1 ml-4">
            <label for="boxplot">Box Plot</label>
        </div>
    </div>

    <VueSelect
        v-model="selectedPrinciple"
        :options="principles"
        placeholder="Select principle to show"
        class="mb-4 w-full"
        :clearable="false"
    ></VueSelect>


    <div class="grid grid-cols-12">
        <div class="col-span-12 md:col-span-12 lg:col-span-12">

            <div v-for="principle in principles" :key="principle.value" v-if="chartType=='bar'">
                <div v-if="barChartData[principle.value]" v-show="selectedPrinciple === principle.value" class="chart-wrapper h-72">
                    <Bar
                        :key="'sub_bar_'+principle.value"
                        :data="barChartData[principle.value]"
                        :options="barChartOptions"
                    />

                </div>
            </div>
            <div class="violinChart h-72" v-show="chartType==='violin'">
                <canvas :id="'violin_principle_sub'" :ref="'violin_principle_sub'"></canvas>
            </div>

            <div class="boxPlot h-72" v-show="chartType==='boxplot'">
                <canvas :id="'boxplot_principle_sub'" :ref="'boxplot_principle_sub'"></canvas>
            </div>
        </div>
    </div>
</template>

<script setup>

import {computed, onMounted, ref, useTemplateRef, watch} from "vue";
import {Bar, Radar} from 'vue-chartjs'
import {RadialLinearScale, PointElement, LineElement, Filler, BarController, BarElement, CategoryScale, Chart as ChartJS, Colors, Legend, LinearScale, Title, Tooltip} from 'chart.js';
import VueSelect from "vue3-select-component";
import * as ss from 'simple-statistics'
import {BoxPlotChart, ViolinChart} from "@sgratzl/chartjs-chart-boxplot";

ChartJS.register(Colors);


ChartJS.register(RadialLinearScale, PointElement, LineElement, Filler, CategoryScale, LinearScale, BarElement, BarController, Title, Tooltip, Legend, ViolinChart);


const props = defineProps({
    selectedCountry: {
        type: Object,
        required: true
    },
    selectedGender: {
        type: String,
        required: false
    },
    filteredResults: {
        type: Array,
        required: true
    },
})

const emit = defineEmits([
    'loadComplete'
])

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

const selectedPrinciple = ref('ae')
const chartType = ref('bar');


const genders = ref([
    {
        label: 'Male',
        value: 'male'
    },
    {
        label: 'Female',
        value: 'female'
    },
]);

// returns the filtered dataset in a format ready for ChartJS charts
const prepareChartData = function (principle_value) {


    const principle_overall_variable = 'overall_' + principle_value.toLowerCase().replace(/ /g, '_') + '_score';


    return genders.value
        .filter(gender => {
            // if a gender is selected, only include that gender
            if (props.selectedGender) {
                return gender.value === props.selectedGender;
            }
            return true;
        })
        .map(gender => {
            let filteredData = props.filteredResults.filter(result => result.gender === gender.value);

            if (filteredData.length === 0) {
                return [];
            }

            return filteredData
                .map(result => {
                    return result[principle_overall_variable]
                })
        });
}

const prepareBarChartData = function (principle_value) {

    const dataValues = prepareChartData(principle_value)

    console.log(dataValues)
    return dataValues.map(data => {
        if (!data || data.length === 0) {
            return 0;
        }

        // filter out NAs
        data = data.filter(value => value !== null && value !== undefined && !isNaN(value));

        return ss.mean(data)
    });
}


watch(() => props.filteredResults, () => {


        if (props.filteredResults.length === 0) {
            return;
        }

        // if there is cached data, use it
        // const cachedViolinData = localStorage.getItem('violinChartData');
        // const cachedBoxPlotData = localStorage.getItem('boxPlotData');
        // const cachedBarChartData = localStorage.getItem('barChartData');

        // if (cachedViolinData && cachedBoxPlotData && cachedBarChartData) {
        //     violinChartData.value = JSON.parse(cachedViolinData);
        //     boxPlotData.value = JSON.parse(cachedBoxPlotData);
        //     barChartData.value = JSON.parse(cachedBarChartData);
        //
        //     reRenderViolins();
        //     reRenderBoxPlots();
        //
        //     emit('loadComplete')
        //     return;
        // } else {
        updateCharts();
        // }

    }, {deep: true}
)

// BarChartData will contain 14 sets of data, one per principle + one for 'all'
const barChartData = ref({})
const violinChartData = ref({})
const boxPlotData = ref({})

const barChartOptions = ref({
    maintainAspectRatio: false,
    indexAxis: 'y',
    scales: {
        x: {
            beginAtZero: true,
            max: 5,
        }
    }
})
const radarChartOptions = ref({
    maintainAspectRatio: false,
    scales: {
        r: {
            beginAtZero: true,
            max: 5,
        }
    }
})

const violinCanvas = useTemplateRef('violin_principle_sub');
const violinPlot = ref(null);

const boxPlotCanvas = useTemplateRef('boxplot_principle_sub');
const boxPlot = ref(null);

watch([selectedPrinciple, chartType], () => {

    reRenderViolins();
    reRenderBoxPlots()

})

onMounted(() => {

})


const updateCharts = function () {

    // scores per principle per country;

    principles.value.forEach((value, key) => {

        const principle = value.value


        // chart data per country
        principles.value.forEach(principle => {
            barChartData.value[principle.value] = {
                labels: genders.value
                    .filter(gender => {
                        // if a gender is selected, only include that gender
                        if (props.selectedGender) {
                            return gender.value === props.selectedGender;
                        }
                        return true;
                    }).map(gender => gender.label),
                datasets: [{
                    label: principle.value + ' Score',
                    data: prepareBarChartData(principle.value)
                }]
            }

            violinChartData.value[principle.value] = {
                labels: genders.value
                    .filter(gender => {
                        // if a gender is selected, only include that gender
                        if (props.selectedGender) {
                            return gender.value === props.selectedGender;
                        }
                        return true;
                    }).map(gender => gender.label),
                datasets: [{
                    label: principle.value + ' Score',
                    data: prepareChartData(principle.value)
                }],
            }
            boxPlotData.value[principle.value] = {
                labels: genders.value
                    .filter(gender => {
                        // if a gender is selected, only include that gender
                        if (props.selectedGender) {
                            return gender.value === props.selectedGender;
                        }
                        return true;
                    }).map(gender => gender.label),
                datasets: [{
                    label: principle.value + ' Score',
                    data: prepareChartData(principle.value)
                }],
            }


            // localStorage.setItem('violinChartData', JSON.stringify(violinChartData.value));
            // localStorage.setItem('boxPlotData', JSON.stringify(boxPlotData.value));
            // localStorage.setItem('barChartData', JSON.stringify(barChartData.value));

            console.log('data for principle ' + principle.value, {
                bar: barChartData.value[principle.value],
                violin: violinChartData.value[principle.value],
                boxplot: boxPlotData.value[principle.value],
            });

        })

    })


    reRenderViolins();
    reRenderBoxPlots();


    emit('loadComplete')


}

const reRenderBoxPlots = function () {
    // if a box plot exists, destroy it
    if (boxPlot.value) {
        boxPlot.value.destroy();
    }


    boxPlot.value = new BoxPlotChart(boxPlotCanvas.value, {
        type: 'boxplot',
        data: boxPlotData.value[selectedPrinciple.value],
        options: {
            responsive: true,
            aspectRatio: 2.5,
            indexAxis: 'y',
            animation: {
                animateScale: true,
                animateRotate: false,
                easing: 'easeOutQuart',
                duration: 500
            },
            plugins: {
                title: {
                    display: true,
                    text: 'Box Plot for ' + selectedPrinciple.value
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    max: 5,
                }
            }
        }
    });
}

const reRenderViolins = function () {
    // if a violin plot exists, destroy it
    if (violinPlot.value) {
        violinPlot.value.destroy();
    }


    violinPlot.value = new ViolinChart(violinCanvas.value, {
        type: 'violin',
        data: violinChartData.value[selectedPrinciple.value],
        options: {
            responsive: true,
            aspectRatio: 2.5,
            indexAxis: 'y',
            animation: {
                animateScale: true,
                animateRotate: false,
                easing: 'easeOutQuart',
                duration: 500
            },
            plugins: {
                title: {
                    display: true,
                    text: 'Violin Plot for ' + selectedPrinciple.value
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    max: 5,
                }
            }
        }
    });
}

</script>
