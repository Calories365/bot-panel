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
    if (Object.keys(localBotData.value).length > 0 && localBotData.value.type_id) {
        const typeId = localBotData.value.type_id.type_id;
        switch (typeId) {
            case 1:
                return rows;
            case 2:
                return rows_approval;
            default:
                return [];
        }
    }
    return [];
});


function handleEvent(payload) {
    if (payload.key && payload.value !== undefined) {
        localBotData.value[payload.key] = payload.value;
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
    store.dispatch(actionTypes.updateWebhook);
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
