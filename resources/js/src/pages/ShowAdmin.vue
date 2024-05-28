<script setup>
import {computed, onMounted, ref} from 'vue';
import {actionTypes, getterTypes} from '@/store/modules/admins.js';
import {useStore} from "vuex";
import {useRoute} from "vue-router";
import router from "@/router/router.js";
import {admin_Rows} from "@/ComponentConfigs/FormConfigs.js";
import BotsForm from "@/Components/BotsForm.vue";

const store = useStore();
const route = useRoute();
const adminData = computed(() => store.getters[getterTypes.admin]);
const localAdminData = ref({});

function handleEvent(payload) {
    if (payload.key && payload.value !== undefined) {
        localAdminData.value[payload.key] = payload.value;
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
    store.dispatch(actionTypes.updateAdmin, localAdminData.value).then(() => {
        localAdminData.value = {...adminData.value};
    });
}

function deleteBot() {
    store.dispatch(actionTypes.deleteAdmin, {id: adminData.value.id}).then(() => {
        router.push({name: 'showAdmins'});
    });
}

onMounted(() => {
    const adminId = route.params.id;
    store.dispatch(actionTypes.getAdmin, adminId).then(() => {
        localAdminData.value = {...adminData.value};
    });
});
</script>

<template>
    <div class="row">
        <div class="col-md-12">
            <div class="card card-primary">
                <div class="card-header">
                    Админ
                </div>
                <bots-form
                    :data="localAdminData"
                    :rows="admin_Rows"
                    @handle="handleEvent"/>
            </div>
        </div>
    </div>
</template>

<style scoped lang="scss">

</style>
