import usersApi from "@/api/users.js";

const state = {
    users: [],
    pagination: {
        currentPage: 1,
        perPage: 10,
        totalPages: 100,
    },
    isSubmitting: false,
    errors: null,
};

export const getterTypes = {
    users: '[users] allUsers',
    pagination: '[users] pagination',
    isSubmitting: '[users] isSubmitting',
};

const getters = {
    [getterTypes.users]: state => state.users,
    [getterTypes.pagination]: state => state.pagination,
    [getterTypes.isSubmitting]: state => state.isSubmitting,
};

export const mutationTypes = {
    getUsersStart: '[users] getUsersStart',
    getUsersSuccess: '[users] getAsersSuccess',
    getUsersFailure: '[users] getUsersFailure',
    setCurrentPage: '[users] setCurrentPage',
    setPerPage: '[users] setPerPage',
    deleteUserStart: '[users] deleteUserStart',
    deleteUserSuccess: '[users] deleteUserSuccess',
    deleteUserFailure: '[users] deleteUserFailure',

    destroyUsers: '[users] destroyUsers',

    exportUsersStart: '[users] exportUsersStart',
    exportUsersSuccess: '[users] exportUsersSuccess',
    exportUsersFailure: '[users] exportUsersFailure',
};

const mutations = {
    [mutationTypes.getUsersStart](state) {
        state.isSubmitting = true;
        state.errors = null;
    },
    [mutationTypes.getUsersSuccess](state, payload) {
        state.users = payload.data;
        state.pagination.totalPages = payload.meta.last_page;
        state.isSubmitting = false;
    },
    [mutationTypes.getUsersFailure](state, payload) {
        state.errors = payload;
        state.isSubmitting = false;
    },
    [mutationTypes.setCurrentPage](state, pageNumber) {
        state.pagination.currentPage = pageNumber;
    },
    [mutationTypes.setPerPage](state, perPage) {
        state.pagination.perPage = perPage;
    },

    [mutationTypes.deleteUserStart](state) {
        state.isSubmitting = true;
        state.errors = null;
    },
    [mutationTypes.deleteUserSuccess](state) {
        state.isSubmitting = false;
    },
    [mutationTypes.deleteUserFailure](state, payload) {
        state.errors = payload;
        state.isSubmitting = false;
    },

    [mutationTypes.exportUsersStart](state) {
        state.isSubmitting = true;
        state.errors = null;
    },
    [mutationTypes.exportUsersSuccess](state) {
        state.isSubmitting = false;
    },
    [mutationTypes.exportUsersFailure](state, payload) {
        state.errors = payload;
        state.isSubmitting = false;
    },

    [mutationTypes.destroyUsers](state) {
        state.users = [];
        state.pagination = {
            currentPage: 1,
            perPage: 10,
            totalPages: 100,
        };
    },
};

export const actionTypes = {
    getUsers: '[users] getUsers',
    changePage: '[users] changePage',
    setPageSize: '[users] setPageSize',
    deleteUser: '[users] deleteUser',
    destroyUsers: '[users] destroyUsers',
    exportUsers: '[users] exportUsers',
};

const actions = {
    async [actionTypes.getUsers]({commit, state}, {botId, page, perPage} = {}) {
        commit(mutationTypes.getUsersStart);
        try {
            const currentPage = page || state.pagination.currentPage;
            const currentPerPage = perPage || state.pagination.perPage;
            const params = {page: currentPage, perPage: currentPerPage};
            if (botId) {
                params.botId = botId;
            }
            const response = await usersApi.getAllUsers(params);
            commit(mutationTypes.getUsersSuccess, response.data);
            return response.data.data;
        } catch (error) {
            commit(mutationTypes.getUsersFailure, error.response ? error.response.data : error);
            throw error;
        }
    },
    async [actionTypes.changePage]({commit, dispatch}, {page}) {
        commit(mutationTypes.setCurrentPage, page);
        return await dispatch(actionTypes.getUsers, {page});
    },
    async [actionTypes.setPageSize]({commit, dispatch, state}, {size}) {
        commit(mutationTypes.setPerPage, size);
        return await dispatch(actionTypes.getUsers, {page: state.pagination.currentPage, perPage: size});
    },
    async [actionTypes.deleteUser]({commit, dispatch, state}, {id}) {
        commit(mutationTypes.deleteUserStart);
        try {
            await usersApi.deleteUser(id);
            commit(mutationTypes.deleteUserSuccess);
            return await dispatch(actionTypes.getUsers, {
                page: state.pagination.currentPage,
                perPage: state.pagination.perPage
            });
        } catch (error) {
            commit(mutationTypes.deleteUserFailure, error.response ? error.response.data : error);
            throw error;
        }
    },
    async [actionTypes.destroyUsers]({commit}) {
        commit(mutationTypes.destroyUsers);
    },
    async [actionTypes.exportUsers]({commit}, {botId}) {
        commit(mutationTypes.exportUsersStart);
        try {
            const params = {};
            if (botId) {
                params.botId = botId;
            }
            const response = await usersApi.exportUsers(params);
            commit(mutationTypes.exportUsersSuccess, response.data);
            return response.data;
        } catch (error) {
            commit(mutationTypes.exportUsersFailure, error.response ? error.response.data : error);
            throw error;
        }
    },

};

export default {
    state,
    getters,
    mutations,
    actions,
};
