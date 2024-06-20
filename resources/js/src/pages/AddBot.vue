<script setup>
import BotsForm from "@/Components/BotsForm.vue";
import {computed, onMounted, ref} from "vue";
import {actionTypes, getterTypes} from "@/store/modules/bots.js";
import store from "@/store/store.js";
import {
    create_rows,
    create_rows_approval,
    create_rows_request,
    create_rows_request2
} from "@/ComponentConfigs/FormConfigs.js";
import router from "@/router/router.js";
import SwastikaLoader from "@/Components/UI/Swastika-loader.vue";

const formConfig = computed(() => {
    if (Object.keys(localBotData.value).length > 0 && localBotData.value.type_id) {
        const typeId = localBotData.value.type_id.type_id;
        switch (typeId) {
            case 2:
                return create_rows_approval;
            case 3:
                return create_rows_request;
            case 4:
                return create_rows_request2;
            default:
                return create_rows;
        }
    }
    return [];
});
const localBotData = ref({});
const isSubmitting = computed(() => store.getters[getterTypes.isSubmitting]);

function createBot() {
    store.dispatch(actionTypes.createBot, localBotData.value)
        .then(data => {
            router.push(`/showBots/${data.id}`);
        })
        .catch(error => {
            console.error('Не удалось создать бота:', error);
        });
}


function handleEvent(payload) {
    if (payload.key && payload.value !== undefined) {
        localBotData.value[payload.key] = payload.value;
    } else if (payload.action) {
        switch (payload.action) {
            case 'submit':
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
    store.dispatch(actionTypes.getBotManagers).then((data) => {
        localBotData.value.managers = data;
    });
});
</script>

<template>
    <swastika-loader v-if="isSubmitting"/>

    <div :class="{'loading': isSubmitting}" class="row">
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
.loading {
    opacity: 0.5;
    pointer-events: none;
}
</style>
