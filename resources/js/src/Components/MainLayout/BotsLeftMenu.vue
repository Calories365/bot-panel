<script setup>
import LeftMenuItem from "@/Components/MainLayout/LeftMenuItem.vue";
import {actionTypes, getterTypes} from "@/store/modules/auth.js";
import router from "@/router/router.js";
import store from "@/store/store.js";
import {computed} from "vue";

const menuItems = [
  {text: "Боты", icon: ['fas', 'robot'], path: '/showBots'},
  {text: "Добавить бота", icon: ['fas', 'plus-circle'], path: '/addBot'},
  {text: "Пользователи", icon: ['fas', 'users'], path: '/showUsers'},
    {text: "Админы", icon: ['fas', 'user-shield'], path: '/showAdmins'},
    {text: "Добавить админа", icon: ['fas', 'user-plus'], path: '/addAdmin'},
    {text: "Менеджеры", icon: ['fas', 'phone'], path: '/showManagers'},
    {text: "Добавить менеджера", icon: ['fas', 'user-plus'], path: '/addManager'}
];
const currentUser = computed(() => store.getters[getterTypes.currentUser]);

const logout = () => {
  store.dispatch(actionTypes.logout).then((errors) => {
    router.push({name: 'login'});
  });
};
</script>

<template>
  <aside class="main-sidebar sidebar-dark-primary elevation-4">

    <router-link to="/" class="brand-link">
      <span class="brand-text font-weight-light">BOT ADMIN👨🏻‍🔧</span>
    </router-link>

    <div class="sidebar">
      <div class="user-panel mt-3 pb-3 mb-3 d-flex">
        <div class="info">
          <router-link to="/" class="d-block">
            {{ currentUser.name }}
          </router-link>
        </div>
        <div class="info">
          <a @click="logout" class="d-block" style="cursor: pointer">
            <font-awesome-icon :icon="['fas', 'right-from-bracket']"/>
          </a>
        </div>
      </div>


      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu"
            data-accordion="false">
          <left-menu-item
              v-for="(item, index) in menuItems"
              :key="index"
              :text="item.text"
              :icon="item.icon"
              :path="item.path"
          />
        </ul>
      </nav>
    </div>
  </aside>
</template>

<style scoped lang="scss">
</style>
