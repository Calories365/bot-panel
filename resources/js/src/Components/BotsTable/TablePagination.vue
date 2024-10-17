<script setup>
import {defineEmits, defineProps} from 'vue';

const props = defineProps({
    currentPage: {
        type: Number,
        required: true
    },
    totalPages: {
        type: Number,
        required: true
    },
});

const emits = defineEmits(['changePage']);

function changePage(page) {
    if (page > 0 && page <= props.totalPages && page !== props.currentPage) {
        emits('update:page-change', page);
    }
}

function generatePagination() {
    const pagination = [];
    const currentPage = props.currentPage;
    const totalPages = props.totalPages;
    const range = 1;

    // Добавить начало
    if (currentPage > 2) {
        pagination.push({page: 1, text: '1', active: false});
    }

    // Добавление точек, если требуется
    if (currentPage > range + 2) {
        pagination.push({text: '...', active: false});
    }

    // Показ страниц вокруг текущей
    for (let i = Math.max(1, currentPage - range); i <= Math.min(totalPages, currentPage + range); i++) {
        pagination.push({page: i, text: `${i}`, active: currentPage === i});
    }

    // Добавление точек, если требуется
    if (currentPage < totalPages - (range + 1)) {
        pagination.push({text: '...', active: false});
    }

    // Добавить конец
    if (currentPage + 1 < totalPages && totalPages > 2) {
        pagination.push({page: totalPages, text: `${totalPages}`, active: false});
    }

    return pagination;
}
</script>

<template>
    <div class="col-sm-12 col-md-7">
        <div class="dataTables_paginate paging_simple_numbers">
            <nav>
                <ul class="pagination">
                    <li class="page-item" :class="{ active: item.active }" v-for="item in generatePagination()"
                        :key="item.text">
                        <a class="page-link" href="#" @click.prevent="changePage(item.page)">
                            {{ item.text }}
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</template>

<style scoped lang="scss">
</style>
