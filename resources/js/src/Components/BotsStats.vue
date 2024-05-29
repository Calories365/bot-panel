<script setup>
import {computed, defineProps, onMounted, ref} from 'vue';
import {Chart, registerables} from 'chart.js';

Chart.register(...registerables);

const props = defineProps({
    data: Object,
});

const chartRef = ref(null);

const chartData = computed(() => {
    if (props.data && props.data.new_users && props.data.banned_users && props.data.premium_users) {
        return {
            labels: Object.keys(props.data.new_users),
            datasets: [
                {
                    label: 'Новые пользователи за день',
                    data: Object.values(props.data.new_users),
                    backgroundColor: 'rgba(0, 123, 255, 0.5)',
                    borderColor: 'rgba(0, 123, 255, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Забаненые пользователи за день',
                    data: Object.values(props.data.banned_users),
                    backgroundColor: 'rgba(255, 99, 132, 0.5)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Премиум пользователи за день',
                    data: Object.values(props.data.premium_users),
                    backgroundColor: 'rgba(246,206,0, 0.5)',
                    borderColor: 'rgba(246,206,0, 1)',
                    borderWidth: 1
                }
            ]
        };
    }
    return {labels: [], datasets: []};
});

onMounted(() => {
    const ctx = chartRef.value.getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: chartData.value,
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
});
</script>


<template>
    <div>
        <canvas ref="chartRef" style="display: block; box-sizing: border-box; height: 197px; width: 789px;"></canvas>
        <div class="col-sm-12">
            <div class="table-wrapper">
                <table class="table table-hover text-nowrap">
                    <thead>
                    <tr>
                        <th>Всего пользователей</th>
                        <th>⭐️ Премиум</th>
                        <th>👶 Обычные</th>
                        <th>❌ Забанили Бота</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td>{{ data.total_new_users }}</td>
                        <td>{{ data.total_premium_users }}</td>
                        <td>{{ data.total_default_users }}</td>
                        <td>{{ data.total_banned_users }}</td>
                    </tr>
                    </tbody>
                </table>
                <a href="http://app.test/showBotUsers/36" class="btn btn-primary">Список пользователей</a>
            </div>
        </div>
    </div>
</template>

<style scoped lang="scss">
/* Ваши стили */
</style>
