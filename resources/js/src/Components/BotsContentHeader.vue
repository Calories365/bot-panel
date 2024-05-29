<script setup>
import { computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';

const router = useRouter();
const route = useRoute();

// Функция для сбора маршрутов для хлебных крошек
const breadcrumbs = computed(() => {
    let matched = route.matched.filter(record => record.meta && record.meta.breadcrumb);
    let breadcrumbs = matched.map(record => ({
        text: record.meta.breadcrumb,
        path: record.path
    }));

    // Добавляем "Home" как стартовую точку
    breadcrumbs.unshift({ text: 'Home', path: '/' });

    return breadcrumbs;
});
</script>

<template>
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Bot Panel</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li v-for="(crumb, index) in breadcrumbs" :key="index" class="breadcrumb-item">
                            <router-link :to="crumb.path">{{ crumb.text }}</router-link>
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    </section>
</template>
