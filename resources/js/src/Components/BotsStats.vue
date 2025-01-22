<script setup>
import {computed, defineProps, onMounted, ref} from 'vue';
import {Chart, registerables} from 'chart.js';
import BotsButton from "@/Components/UI/BotsButton.vue";
import {useRouter} from "vue-router";

Chart.register(...registerables);
const router = useRouter();

const props = defineProps({
    data: Object,
    botId: Number,
});

const chartRef = ref(null);

const chartData = computed(() => {
    if (props.data && props.data.new_users && props.data.banned_users && props.data.active_users) {
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
                    label: 'Активные пользователи за день',
                    data: Object.values(props.data.active_users),
                    backgroundColor: 'rgba(246,206,0, 0.5)',
                    borderColor: 'rgba(246,206,0, 1)',
                    borderWidth: 1
                }
            ]
        };
    }
    return {labels: [], datasets: []};
});

const navigateToUsers = () => {
    router.push({ name: 'showUsers', params: { id: props.botId } });
}
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
                        <th>⭐️ Активные</th>
                        <th>👶 Обычные</th>
                        <th>❌ Забанили Бота</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td>{{ data.total_new_users }}</td>
                        <td>{{ data.total_active_users }}</td>
                        <td>{{ data.total_default_users }}</td>
                        <td>{{ data.total_banned_users }}</td>
                    </tr>
                    </tbody>
                </table>
                <BotsButton @click="navigateToUsers">
                    Список пользователей
                </BotsButton>
            </div>
        </div>
    </div>
</template>

<style scoped lang="scss">
/* Ваши стили */
</style>
