<script setup>
import BotsForm from "@/Components/BotsForm.vue";
import { computed, onMounted, ref } from "vue";
import { actionTypes, getterTypes } from "@/store/modules/bots.js";
import store from "@/store/store.js";
import router from "@/router/router.js";
import Loader from "@/Components/UI/Loader.vue";
import { useHandleEvent } from "@/Composables/useHandleEvent.js";
import { create_rows_default } from "@/ComponentConfigs/Form/Bot/create_rows_default.js";
import { create_rows_approval } from "@/ComponentConfigs/Form/Bot/create_rows_approval.js";
import { create_rows_request } from "@/ComponentConfigs/Form/Bot/create_rows_request.js";
import { create_rows_request2 } from "@/ComponentConfigs/Form/Bot/create_rows_request2.js";

const formConfig = computed(() => {
    if (
        Object.keys(localBotData.value).length > 0 &&
        localBotData.value.type_id
    ) {
        const typeId = localBotData.value.type_id.type_id;
        switch (typeId) {
            case 2:
                return create_rows_approval;
            case 3:
                return create_rows_request;
            case 4:
                return create_rows_request2;
            default:
                return create_rows_default;
        }
    }
    return [];
});
const localBotData = ref({});
const isSubmitting = computed(() => store.getters[getterTypes.isSubmitting]);

function createBot() {
    store
        .dispatch(actionTypes.createBot, localBotData.value)
        .then((data) => {
            router.push(`/showBots/${data.id}`);
        })
        .catch((error) => {
            console.error("Не удалось создать бота:", error);
        });
}

const { handleEvent } = useHandleEvent({
    localData: localBotData,
    actions: { submit: createBot },
});

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
    <loader v-if="isSubmitting" />

    <div :class="{ loading: isSubmitting }" class="row">
        <div class="col-md-12">
            <div class="card card-primary">
                <div class="card-header">Add a bot</div>
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
