<script setup>
import BotsTable from "@/Components/BotsTable.vue";
import {computed, defineEmits, onMounted, ref} from 'vue';
import {useStore} from "vuex";
import {actionTypes, getterTypes} from "@/store/modules/admins.js";
import {adminsTableConfig} from "@/ComponentConfigs/TableConfigs.js";
import router from "@/router/router.js";
import BotsConfirmatiomModal from "@/Components/UI/BotsConfirmatiomModal.vue";
import SwastikaLoader from "@/Components/UI/Swastika-loader.vue"; // Импортируем компонент загрузки

const store = useStore();
const bots = computed(() => store.getters[getterTypes.admins]);
const isSubmitting = computed(() => store.getters[getterTypes.isSubmitting]); // Добавлено для отслеживания состояния загрузки
const pagination = computed(() => store.getters[getterTypes.pagination]);

const sizeOptions = [10, 20, 30, 40, 50];

const prePageText = 'Количество админов на странице';
const currentPage = ref(1);
const pageSize = ref(10);
const emit = defineEmits(['handle']);
const showModal = ref(false);
const selectedAdminId = ref(null);

const handlePageChange = (page) => {
    currentPage.value = page;
    store.dispatch(actionTypes.changePage, {page});
    window.scrollTo({
        top: 0,
        left: 0,
        behavior: 'smooth'
    });
};

const handlePageSizeChange = (size) => {
    pageSize.value = size;
    store.dispatch(actionTypes.setPageSize, {size});
    window.scrollTo({
        top: 0,
        left: 0,
        behavior: 'smooth'
    });
};

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
    <swastika-loader v-if="isSubmitting"/>

    <div :class="{'loading': isSubmitting}" class="col-12">
        <div class="card">
            <BotsTable
                :per-page-text="prePageText"
                :columns="adminsTableConfig"
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
