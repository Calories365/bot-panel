<script setup>
import {computed, onMounted, ref} from 'vue';
import {actionTypes, getterTypes} from '@/store/modules/admins.js';
import {useStore} from "vuex";
import {useRoute} from "vue-router";
import router from "@/router/router.js";
import {admin_Rows} from "@/ComponentConfigs/FormConfigs.js";
import BotsForm from "@/Components/BotsForm.vue";
import BotsConfirmatiomModal from "@/Components/UI/BotsConfirmatiomModal.vue";
import SwastikaLoader from "@/Components/UI/Swastika-loader.vue";

const store = useStore();
const route = useRoute();
const adminData = computed(() => store.getters[getterTypes.admin]);
const isSubmitting = computed(() => store.getters[getterTypes.isSubmitting]);
const localAdminData = ref({});
const showModal = ref(false);

function handleEvent(payload) {
    if (payload.key && payload.value !== undefined) {
        localAdminData.value[payload.key] = payload.value;
    } else if (payload.action) {
        switch (payload.action) {
            case 'submit':
                saveAdmin();
                break;
            case 'delete':
                showModal.value = true;
                break;
            default:
                console.log("Неизвестное действие");
        }
    }
}

function saveAdmin() {
    store.dispatch(actionTypes.updateAdmin, localAdminData.value).then(() => {
        localAdminData.value = {...adminData.value};
    });
}

function deleteAdmin() {
    store.dispatch(actionTypes.deleteAdmin, {id: adminData.value.id}).then(() => {
        router.push({name: 'showAdmins'});
    });
}

function confirmDelete() {
    deleteAdmin();
    showModal.value = false;
}

onMounted(() => {
    const adminId = route.params.id;
    store.dispatch(actionTypes.getAdmin, adminId).then(() => {
        localAdminData.value = {...adminData.value};
    });
});
</script>

<template>
    <swastika-loader v-if="isSubmitting"/>

    <div :class="{'loading': isSubmitting}" class="row">
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

    <BotsConfirmatiomModal
        title="Подтверждение действия"
        message="Вы уверены, что хотите удалить этого админа?"
        :showModal="showModal"
        @update:showModal="showModal = $event"
        @confirm="confirmDelete"
    />
</template>

<style scoped lang="scss">
.loading {
    opacity: 0.5;
    pointer-events: none;
}
</style>
