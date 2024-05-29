<script setup>
import {computed, ref} from 'vue';
import {actionTypes, getterTypes} from '@/store/modules/admins.js';
import {useStore} from "vuex";
import {useRoute} from "vue-router";
import {admin_Rows} from "@/ComponentConfigs/FormConfigs.js";
import BotsForm from "@/Components/BotsForm.vue";
import router from "@/router/router.js";

const store = useStore();
const route = useRoute();
const localAdminData = ref({});

function handleEvent(payload) {
    if (payload.key && payload.value !== undefined) {
        localAdminData.value[payload.key] = payload.value;
    } else if (payload.action) {
        switch (payload.action) {
            case 'save':
                createAdmin();
                break;
            default:
                console.log("Неизвестное действие");
        }
    }
}

function createAdmin() {
    store.dispatch(actionTypes.createAdmin, localAdminData.value).then((id) => {
        router.push(`/showAdmins/${id}`);
    });
}

</script>

<template>
    <div class="row">
        <div class="col-md-12">
            <div class="card card-primary">
                <div class="card-header">
                    Добавить админа
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
