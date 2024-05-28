<script setup>

import BotsForm from "@/Components/BotsForm.vue";
import {computed, onMounted, ref} from "vue";
import {actionTypes} from "@/store/modules/bots.js";
import store from "@/store/store.js";
import {create_rows, create_rows_approval} from "@/ComponentConfigs/FormConfigs.js";
import router from "@/router/router.js";

const formConfig = computed(() => {
    if (Object.keys(localBotData.value).length > 0 && localBotData.value.type_id) {
        const typeId = localBotData.value.type_id.type_id;
        switch (typeId) {
            case 1:
                return create_rows;
            case 2:
                return create_rows_approval;
            default:
                return [];
        }
    }
    return [];
});
const localBotData = ref({});

function createBot() {
    store.dispatch(actionTypes.createBot, localBotData.value).then((data) => {
        router.push(`/showBots/${data.id}`);
    });
}

function handleEvent(payload) {
    if (payload.key && payload.value !== undefined) {
        localBotData.value[payload.key] = payload.value;
    } else if (payload.action) {
        switch (payload.action) {
            case 'create':
                createBot();
                break;
            default:
                console.log("Неизвестное действие");
        }
    }
}


onMounted(() => {
    store.dispatch(actionTypes.getBotTypes).then((data) => {
        localBotData.value.type_id = data;
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
