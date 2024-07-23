<script setup>
import BotsTable from "@/Components/BotsTable.vue";
import {computed, defineEmits, onMounted, ref} from 'vue';
import {useStore} from "vuex";
import {actionTypes, getterTypes} from "@/store/modules/admins.js";
import router from "@/router/router.js";
import BotsConfirmatiomModal from "@/Components/UI/BotsConfirmatiomModal.vue";
import Loader from "@/Components/UI/Loader.vue";
import {admins_table} from "@/ComponentConfigs/Table/admins_table.js";
import usePagination from "@/Composables/usePagination.js";

const store = useStore();
const bots = computed(() => store.getters[getterTypes.admins]);
const isSubmitting = computed(() => store.getters[getterTypes.isSubmitting]);
const pagination = computed(() => store.getters[getterTypes.pagination]);

const sizeOptions = [10, 20, 30, 40, 50];
const prePageText = 'Количество админов на странице';
const emit = defineEmits(['handle']);
const showModal = ref(false);
const selectedAdminId = ref(null);
const {currentPage, pageSize, handlePageChange, handlePageSizeChange} = usePagination(store.dispatch);

onMounted(() => {
    store.dispatch(actionTypes.getAllAdmins).then(allAdmins => {
    }).catch(error => {
        console.error('Failed to load admins:', error);
    });
});

function handleEvent(event) {
    if (event.action === 'delete') {
        selectedAdminId.value = event.id;
        showModal.value = true;
    }
    if (event.action === 'show') {
        router.push(`/showAdmins/${event.id}`);
    }
}

const confirmDelete = () => {
    store.dispatch(actionTypes.deleteAdmin, {id: selectedAdminId.value});
    selectedAdminId.value = null;
};
</script>

<template>
    <loader v-if="isSubmitting"/>

    <div :class="{'loading': isSubmitting}" class="col-12">
        <div class="card">
            <BotsTable
                :per-page-text="prePageText"
                :columns="admins_table"
                :data="bots"
                :total-pages="pagination.totalPages"
                :current-page="pagination.currentPage"
                :page-size-options="sizeOptions"
                :per-page="pagination.perPage"
                @update:page-change="handlePageChange"
                @update:page-size-change="handlePageSizeChange"
                @handle="handleEvent"/>
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
.table-wrapper {
    overflow-x: auto;
}
</style>
