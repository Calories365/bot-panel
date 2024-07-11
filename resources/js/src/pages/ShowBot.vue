<script setup>
import {computed, onMounted, onUnmounted, ref} from 'vue';
import BotsForm from "@/Components/BotsForm.vue";
import {actionTypes, getterTypes} from '@/store/modules/bots';
import {useStore} from "vuex";
import {useRoute} from "vue-router";
import router from "@/router/router.js";
import {rows, rows_approval, rows_request, rows_request2} from "@/ComponentConfigs/FormConfigs.js";
import BotsStats from "@/Components/BotsStats.vue";
import BotsConfirmatiomModal from "@/Components/UI/BotsConfirmatiomModal.vue";
import SwastikaLoader from "@/Components/UI/Swastika-loader.vue";
import {useHandleEvent} from "@/Composables/useHandleEvent.js";

const store = useStore();
const route = useRoute();

const botData = computed(() => store.getters[getterTypes.bot]);
const botUserData = computed(() => store.getters[getterTypes.botUserData]);
const isSubmitting = computed(() => store.getters[getterTypes.isSubmitting]);
const localBotData = ref({});
const isBotUserDataNotEmpty = computed(() => {
    return Object.keys(botUserData.value).length > 0;
});

const formConfig = computed(() => {
    if (Object.keys(localBotData.value).length > 0 && localBotData.value.type_id) {
        const typeId = localBotData.value.type_id.type_id;
        switch (typeId) {
            case 2:
                return rows_approval;
            case 3:
                return rows_request;
            case 4:
                return rows_request2;
            default:
                return rows;
        }
    }
    return [];
});

const showModal = ref(false);

const { handleEvent } = useHandleEvent({
    localData: localBotData,
    showModal: showModal,
    actions: {
        submit: saveBot,
        delete: showDeleteModal,
        updateWebhook: updateWebhook
    }
});

function saveBot() {
    store.dispatch(actionTypes.updateBot, localBotData.value).then(() => {
        localBotData.value = {...botData.value};
    });
}

function deleteBot() {
    store.dispatch(actionTypes.deleteBot, {id: botData.value.id}).then(() => {
        router.push({name: 'showBots'});
    });
}

function showDeleteModal() {
    showModal.value = true;
}

function updateWebhook() {
    store.dispatch(actionTypes.updateWebhook);
}

onMounted(() => {
    const botId = route.params.id;
    store.dispatch(actionTypes.getBot, botId).then(() => {
        localBotData.value = {...botData.value};
        store.dispatch(actionTypes.getBotUserData);
    });
});

onUnmounted(() => {
    store.dispatch(actionTypes.destroyBot)
});
</script>

<template>
    <swastika-loader v-if="isSubmitting"/>

    <div :class="{'loading': isSubmitting}" class="row">
        <div class="col-md-12">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">
                        Статистика Бота
                    </h3>
                </div>
                <div v-if="isBotUserDataNotEmpty"
                     class="card-body">
                    <BotsStats
                        :botId="botData.id"
                        :data="botUserData"
                    />
                </div>
            </div>
            <div class="card card-primary">
                <div class="card-header">
                    Бот
                </div>

                <bots-form v-if="localBotData"
                    :data="localBotData"
                    :rows="formConfig"
                    @handle="handleEvent"/>
            </div>
        </div>
    </div>

    <BotsConfirmatiomModal
        title="Подтверждение действия"
        message="Вы уверены, что хотите удалить этого бота?"
        :showModal="showModal"
        @update:showModal="showModal = $event"
        @confirm="deleteBot"
    />
</template>

<style scoped lang="scss">
.loading {
    opacity: 0.5;
    pointer-events: none;
}
</style>
