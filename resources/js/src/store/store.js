import {createStore} from 'vuex';

import auth from '@/store/modules/auth';
import bots from "@/store/modules/bots.js";
import users from "@/store/modules/users.js";
import admins from "@/store/modules/admins.js";
import managers from "@/store/modules/managers.js";

export default createStore({
    state: {
        successMessages: [],
        errorMessages: [],
    },
    getters: {
        successMessages: (state) => state.successMessages,
        errorMessages: (state) => state.errorMessages,
    },
    mutations: {
        ADD_SUCCESS(state, message) {
            state.successMessages.push(message);
        },
        ADD_ERROR(state, message) {
            state.errorMessages.push(message);
        },
        RESET_SUCCESS(state) {
            state.successMessages = [];
        },
        RESET_ERROR(state) {
            state.errorMessages = [];
        },
    },
    actions: {
        addSuccess({ commit }, message) {
            commit('ADD_SUCCESS', message);
            setTimeout(() => {
                commit('RESET_SUCCESS');
            }, 3000);
        },
        addError({ commit }, message) {
            commit('ADD_ERROR', message);
            setTimeout(() => {
                commit('RESET_ERROR');
            }, 3000);
        },
    },
    modules: {
        auth, bots, users, admins, managers
    }
});
