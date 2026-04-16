<template>

    <div v-if="props.currentValue">
        <Doughnut
            :data="data"
            :options="options"
            :plugins="[centerTextPlugin]"
        />
    </div>
</template>

<script setup>
import {Doughnut} from 'vue-chartjs'
import {Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, BarController, ArcElement} from 'chart.js';
import {computed, onMounted, ref, watch} from "vue";
import {Colors} from 'chart.js';

ChartJS.register(Colors);


ChartJS.register(CategoryScale, LinearScale, BarElement, BarController, Title, Tooltip, Legend, ArcElement);

const props = defineProps({
    currentValue: {},
})

const data = ref({})

const inverseData = computed(() => {
    return 5 - props.currentValue;
});

onMounted(() => {
    updateChartData();
});

watch(() => props.currentValue, () => {
    updateChartData();
});

const updateChartData = () => {

    console.log('current Value changed:', props.currentValue);
    data.value = {
        labels: ['Progress', 'Remaining'],
        datasets: [
            // Background track (gray semicircle)
            {
                data: [props.currentValue, inverseData.value], // Full semicircle (100%)
                backgroundColor: ['#3e63dd', '#e0e0e0'], // Light gray
                borderWidth: 0, // No border
                hoverOffset: 0 // Disable hover effects
            },
        ],
    };
};

const options = ref({
    aspectRatio: 2,
    cutout: '60%', // Size of the inner hole (80% = thin arc)
    circumference: 180, // Half circle (180 degrees)
    rotation: -90, // Start at top (rotates 90 degrees counterclockwise)
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false // Hide legend
        },
        tooltip: {
            enabled: false // Hide tooltip
        }
    }
})


const centerTextPlugin = {
  id: 'centerText',
  beforeDraw: (chart) => {
    const { width, height, ctx } = chart;
    const centerX = width / 2;
    const centerY = height * 2 / 3;

    ctx.restore();
    const fontSize = Math.min(width, height) / 6;
    ctx.font = `bold ${fontSize}px Arial`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillStyle = '#333';

    // Display the current value
    ctx.fillText(props.currentValue, centerX, centerY);
    ctx.save();
  }
};



</script>
