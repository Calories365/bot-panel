<script setup>

import BotsForm from "@/Components/BotsForm.vue";
import {computed, onMounted, ref} from "vue";
import {actionTypes} from "@/store/modules/bots.js";
import store from "@/store/store.js";
import {rows, rows_approval} from "@/ComponentConfigs/FormConfigs.js";

const formConfig = computed(() => {
    return localBotData.value.type_id === 1 ? rows : localBotData.value.type_id === 2 ? rows_approval : [];
});
const localBotData = ref({});

function createBot() {
    store.dispatch(actionTypes.createBot, localBotData.value);
}

function handleEvent(payload) {
    if (payload.key && payload.value !== undefined) {
        localBotData.value[payload.key] = payload.value;
        if (Array.isArray(localBotData.value.bot_types)) {
            localBotData.value.bot_types = localBotData.value.bot_types.map((botType) => ({
                ...botType,
                active: botType.id === payload.value
            }));
        }
    } else if (payload.action) {
        switch (payload.action) {
            case 'save':
                createBot();
                break;
            case 'delete':
                deleteBot();
                break;
            case 'updateWebhook':
                updateWebhook();
                break;
            default:
                console.log("Неизвестное действие");
        }
    }
}

onMounted(() => {
    store.dispatch(actionTypes.getBotTypes).then((bot_types) => {
        localBotData.value.bot_types = bot_types;
        localBotData.value.type_id = 1;
    });
});


</script>

<template>
    <div class="row">
        <div class="col-md-12">
            <div class="card card-primary">
                <div class="card-header">
                    Добавить бота
                </div>
                <bots-form
                    :data="localBotData"
                    :rows="formConfig"
                    @handle="handleEvent"
                />
            </div>
        </div>
    </div>
</template>

<style scoped lang="scss">

</style>
