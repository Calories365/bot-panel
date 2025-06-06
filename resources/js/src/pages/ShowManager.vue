<script setup>
import { computed, onMounted, ref } from "vue";
import { actionTypes, getterTypes } from "@/store/modules/managers.js";
import { useStore } from "vuex";
import { useRoute } from "vue-router";
import router from "@/router/router.js";
import BotsForm from "@/Components/BotsForm.vue";
import BotsConfirmatiomModal from "@/Components/UI/BotsConfirmatiomModal.vue";
import Loader from "@/Components/UI/Loader.vue";
import { useHandleEvent } from "@/Composables/useHandleEvent.js";
import { manager_rows } from "@/ComponentConfigs/Form/Manager/manager_rows.js";

const store = useStore();
const route = useRoute();
const managerData = computed(() => store.getters[getterTypes.manager]);
const isSubmitting = computed(() => store.getters[getterTypes.isSubmitting]);
const localManagerData = ref({});
const showModal = ref(false);

const { handleEvent } = useHandleEvent({
    localData: localManagerData,
    showModal: showModal,
    actions: {
        submit: saveManager,
        delete: showDeleteModal,
    },
});

function showDeleteModal() {
    showModal.value = true;
}
function saveManager() {
    store
        .dispatch(actionTypes.updateManager, localManagerData.value)
        .then(() => {
            localManagerData.value = { ...managerData.value };
        });
}

function deleteManager() {
    store
        .dispatch(actionTypes.deleteManager, { id: managerData.value.id })
        .then(() => {
            router.push({ name: "showManagers" });
        });
}

onMounted(() => {
    const adminId = route.params.id;
    store.dispatch(actionTypes.getManager, adminId).then(() => {
        localManagerData.value = { ...managerData.value };
    });
});
</script>

<template>
    <loader v-if="isSubmitting" />

    <div :class="{ loading: isSubmitting }" class="row">
        <div class="col-md-12">
            <div class="card card-primary">
                <div class="card-header">Manager</div>
                <bots-form
                    :data="localManagerData"
                    :rows="manager_rows"
                    @handle="handleEvent"
                />
            </div>
        </div>
    </div>

    <BotsConfirmatiomModal
        title="Confirmation of action"
        message="Are you sure you want to remove this admin?"
        :showModal="showModal"
        @update:showModal="showModal = $event"
        @confirm="deleteManager"
    />
</template>

<style scoped lang="scss">
.loading {
    opacity: 0.5;
    pointer-events: none;
}
</style>
