<script setup>
import {computed, onMounted, ref} from 'vue';
import {actionTypes, getterTypes} from '@/store/modules/admins.js';
import {useStore} from "vuex";
import {useRoute} from "vue-router";
import router from "@/router/router.js";
import BotsForm from "@/Components/BotsForm.vue";
import BotsConfirmatiomModal from "@/Components/UI/BotsConfirmatiomModal.vue";
import Loader from "@/Components/UI/Loader.vue";
import {useHandleEvent} from '@/Composables/useHandleEvent.js';
import {admin_rows} from "@/ComponentConfigs/Form/Admin/admin_rows.js";

const store = useStore();
const route = useRoute();
const adminData = computed(() => store.getters[getterTypes.admin]);
const isSubmitting = computed(() => store.getters[getterTypes.isSubmitting]);
const localAdminData = ref({});
const showModal = ref(false);

const { handleEvent } = useHandleEvent({
    localData: localAdminData,
    showModal: showModal,
    actions: {
        submit: saveAdmin,
        delete: showDeleteModal
    }
});

function saveAdmin() {
    store.dispatch(actionTypes.updateAdmin, localAdminData.value).then(() => {
        localAdminData.value = {...adminData.value};
    });
}

function deleteAdmin() {
    showModal.value = false;
    store.dispatch(actionTypes.deleteAdmin, {id: adminData.value.id}).then(() => {
        router.push({name: 'showAdmins'});
    });
}

function showDeleteModal() {
    showModal.value = true;
}

onMounted(() => {
    const adminId = route.params.id;
    store.dispatch(actionTypes.getAdmin, adminId).then(() => {
        localAdminData.value = {...adminData.value};
    });
});
</script>

<template>
    <loader v-if="isSubmitting"/>

    <div :class="{'loading': isSubmitting}" class="row">
        <div class="col-md-12">
            <div class="card card-primary">
                <div class="card-header">
                    Админ
                </div>
                <bots-form
                    :data="localAdminData"
                    :rows="admin_rows"
                    @handle="handleEvent"/>
            </div>
        </div>
    </div>

    <BotsConfirmatiomModal
        title="Подтверждение действия"
        message="Вы уверены, что хотите удалить этого админа?"
        :showModal="showModal"
        @update:showModal="showModal = $event"
        @confirm="deleteAdmin"
    />
</template>

<style scoped lang="scss">
.loading {
    opacity: 0.5;
    pointer-events: none;
}
</style>
