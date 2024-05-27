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
    getAllUsersStart: '[users] getAllUsersStart',
    getAllUsersSuccess: '[users] getAllUsersSuccess',
    getAllUsersFailure: '[users] getAllUsersFailure',
    setCurrentPage: '[users] setCurrentPage',
    setPerPage: '[users] setPerPage',
    deleteUserStart: '[users] deleteUserStart',
    deleteUserSuccess: '[users] deleteUserSuccess',
    deleteUserFailure: '[users] deleteUserFailure',
};

const mutations = {
    [mutationTypes.getAllUsersStart](state) {
        state.isSubmitting = true;
        state.errors = null;
    },
    [mutationTypes.getAllUsersSuccess](state, payload) {
        state.users = payload.data;
        state.pagination.totalPages = payload.meta.last_page;
        state.isSubmitting = false;
    },
    [mutationTypes.getAllUsersFailure](state, payload) {
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
};

export const actionTypes = {
    getAllUsers: '[users] getAllUsers',
    changePage: '[users] changePage',
    setPageSize: '[users] setPageSize',
    deleteUser: '[users] deleteUser',
};

const actions = {
    async [actionTypes.getAllUsers]({commit, state}, {page, perPage} = {}) {
        commit(mutationTypes.getAllUsersStart);
        try {
            const currentPage = page || state.pagination.currentPage;
            const currentPerPage = perPage || state.pagination.perPage;
            const response = await usersApi.getAllUsers({page: currentPage, perPage: currentPerPage});
            commit(mutationTypes.getAllUsersSuccess, response.data);
            return response.data.data;
        } catch (error) {
            commit(mutationTypes.getAllUsersFailure, error.response ? error.response.data : error);
            throw error;
        }
    },
    async [actionTypes.changePage]({commit, dispatch}, {page}) {
        commit(mutationTypes.setCurrentPage, page);
        return await dispatch(actionTypes.getAllUsers, {page});
    },
    async [actionTypes.setPageSize]({commit, dispatch, state}, {size}) {
        commit(mutationTypes.setPerPage, size);
        return await dispatch(actionTypes.getAllUsers, {page: state.pagination.currentPage, perPage: size});
    },
    async [actionTypes.deleteUser]({commit, dispatch, state}, {id}) {
        commit(mutationTypes.deleteUserStart);
        try {
            await usersApi.deleteUser(id);
            commit(mutationTypes.deleteUserSuccess);
            return await dispatch(actionTypes.getAllUsers, {
                page: state.pagination.currentPage,
                perPage: state.pagination.perPage
            });
        } catch (error) {
            commit(mutationTypes.deleteUserFailure, error.response ? error.response.data : error);
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
