<script setup>
import {computed, onMounted, ref} from 'vue';
import BotsForm from "@/Components/BotsForm.vue";
import {actionTypes, getterTypes} from '@/store/modules/bots';
import {useStore} from "vuex";
import {useRoute} from "vue-router";
import router from "@/router/router.js";
import {rows, rows_approval} from "@/ComponentConfigs/FormConfigs.js";

const store = useStore();
const route = useRoute();
const botData = computed(() => store.getters[getterTypes.bot]);
const localBotData = ref({});

const formConfig = computed(() => {
    return localBotData.value.type_id === 1 ? rows : localBotData.value.type_id === 2 ? rows_approval : [];
});

function handleEvent(payload) {
    if (payload.key && payload.value !== undefined) {
        if (payload.key === 'message_image' && payload.value instanceof File) {
            localBotData.value.image = payload.value;
        } else {
            localBotData.value[payload.key] = payload.value;
        }

        if (payload.key === 'type_id' && Array.isArray(localBotData.value.bot_types)) {
            localBotData.value.bot_types = localBotData.value.bot_types.map(botType => ({
                ...botType,
                active: botType.id === payload.value
            }));
        }
    } else if (payload.action) {
        switch (payload.action) {
            case 'save':
                saveBot();
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

function saveBot() {
    console.log('saveBot')
    store.dispatch(actionTypes.updateBot, localBotData.value).then(() => {
        localBotData.value = {...botData.value};
    });
}

function deleteBot() {
    store.dispatch(actionTypes.deleteBot, {id: botData.value.id}).then(() => {
        router.push({name: 'showBots'});
    });
}

function updateWebhook() {
    console.log('updateWebhook');
    // store.dispatch(actionTypes.updateBotWebhook, botData.value.id);
}

onMounted(() => {
    const botId = route.params.id;
    store.dispatch(actionTypes.getBot, botId).then(() => {
        localBotData.value = {...botData.value};
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
                    @handle="handleEvent"/>
            </div>
        </div>
    </div>
</template>

<style scoped lang="scss">

</style>
