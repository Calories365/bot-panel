<script setup>
import BotsTable from "@/Components/BotsTable.vue";
import {computed, defineEmits, onMounted, ref} from 'vue';
import {useStore} from "vuex";
import {actionTypes, getterTypes} from "@/store/modules/admins.js";
import {adminsTableConfig} from "@/ComponentConfigs/TableConfigs.js";

const store = useStore();
const bots = computed(() => store.getters[getterTypes.admins]);
const isSubmitting = computed(() => store.getters[getterTypes.isSubmitting]);
const pagination = computed(() => store.getters[getterTypes.pagination])

const sizeOptions = [10, 20, 30, 40, 50];

const prePageText = 'Количество админов на странице'
const currentPage = ref(1);
const pageSize = ref(10);
const emit = defineEmits(['handle']);

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
        console.error('Failed to load bots:', error);
    });
});

function handleEvent(event) {
    if (event.action === 'delete') {
        store.dispatch(actionTypes.deleteAdmin, {id: event.id});
    }
}
</script>

<template>
    <div class="col-12">
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
</template>

<style scoped lang="scss">
.table-wrapper {
    overflow-x: auto;
}
</style>
