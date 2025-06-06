<script setup>
import BotsTable from "@/Components/BotsTable.vue";
import { computed, defineEmits, onMounted, ref } from "vue";
import { useStore } from "vuex";
import { actionTypes, getterTypes } from "@/store/modules/managers.js";
import router from "@/router/router.js";
import BotsConfirmatiomModal from "@/Components/UI/BotsConfirmatiomModal.vue";
import Loader from "@/Components/UI/Loader.vue";
import { managers_table } from "@/ComponentConfigs/Table/managers_table.js";
import usePagination from "@/Composables/usePagination.js";

const store = useStore();
const managers = computed(() => store.getters[getterTypes.managers]);
const isSubmitting = computed(() => store.getters[getterTypes.isSubmitting]);
const pagination = computed(() => store.getters[getterTypes.pagination]);

const sizeOptions = [10, 20, 30, 40, 50];
const prePageText = "Number of managers on the page";
const emit = defineEmits(["handle"]);
const showModal = ref(false);
const selectedManagerId = ref(null);
const { currentPage, pageSize, handlePageChange, handlePageSizeChange } =
    usePagination(store.dispatch);

onMounted(() => {
    store
        .dispatch(actionTypes.getAllManagers)
        .then((allManagers) => {})
        .catch((error) => {
            console.error("Failed to load admins:", error);
        });
});

function handleEvent(event) {
    if (event.action === "delete") {
        selectedManagerId.value = event.id;
        showModal.value = true;
    }
    if (event.action === "show") {
        router.push(`/showManagers/${event.id}`);
    }
}

const confirmDelete = () => {
    store.dispatch(actionTypes.deleteManager, { id: selectedManagerId.value });
    selectedManagerId.value = null;
};
</script>

<template>
    <loader v-if="isSubmitting" />

    <div :class="{ loading: isSubmitting }" class="col-12">
        <div class="card">
            <BotsTable
                :per-page-text="prePageText"
                :columns="managers_table"
                :data="managers"
                :total-pages="pagination.totalPages"
                :current-page="pagination.currentPage"
                :page-size-options="sizeOptions"
                :per-page="pagination.perPage"
                @update:page-change="handlePageChange"
                @update:page-size-change="handlePageSizeChange"
                @handle="handleEvent"
            />
        </div>
    </div>

    <BotsConfirmatiomModal
        title="Confirmation of action"
        message="Are you sure you want to remove this manager?"
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

.table-wrapper {
    overflow-x: auto;
}
</style>
