import managersApi from "@/api/managers.js";

const state = {
    managers: [],
    manager: {},
    pagination: {
        currentPage: 1,
        perPage: 10,
        totalPages: 100,
    },
    isSubmitting: false,
    errors: null,
};

export const getterTypes = {
    managers: '[managers] allManagers',
    pagination: '[managers] pagination',
    isSubmitting: '[managers] isSubmitting',
    manager: '[managers] manager',
};

const getters = {
    [getterTypes.managers]: state => state.managers,
    [getterTypes.manager]: state => state.manager,
    [getterTypes.pagination]: state => state.pagination,
    [getterTypes.isSubmitting]: state => state.isSubmitting,
};

export const mutationTypes = {
    getAllManagersStart: '[managers] getAllManagersStart',
    getAllManagersSuccess: '[managers] getAllManagersSuccess',
    getAllManagersFailure: '[managers] getAllManagersFailure',

    setCurrentPage: '[managers] setCurrentPage',
    setPerPage: '[managers] setPerPage',

    deleteManagerStart: '[managers] deleteManagerStart',
    deleteManagerSuccess: '[managers] deleteManagerSuccess',
    deleteManagerFailure: '[managers] deleteManagerFailure',

    getManagerStart: '[managers] getManagerStart',
    getManagerSuccess: '[managers] getManagerSuccess',
    getManagerFailure: '[managers] getManagerFailure',

    updateManagerStart: '[managers] updateManagerStart',
    updateManagerSuccess: '[managers] updateManagerSuccess',
    updateManagerFailure: '[managers] updateManagerFailure',

    createManagerStart: '[managers] createManagerStart',
    createManagerSuccess: '[managers] createManagerSuccess',
    createManagerFailure: '[managers] createManagerFailure',
};

const mutations = {
    [mutationTypes.getAllManagersStart](state) {
        state.isSubmitting = true;
        state.errors = null;
    },
    [mutationTypes.getAllManagersSuccess](state, payload) {
        state.managers = payload.data;
        state.pagination.totalPages = payload.meta.last_page;
        state.isSubmitting = false;
    },
    [mutationTypes.getAllManagersFailure](state, payload) {
        state.errors = payload;
        state.isSubmitting = false;
    },
    [mutationTypes.setCurrentPage](state, pageNumber) {
        state.pagination.currentPage = pageNumber;
    },
    [mutationTypes.setPerPage](state, perPage) {
        state.pagination.perPage = perPage;
    },
    [mutationTypes.deleteManagerStart](state) {
        state.isSubmitting = true;
        state.errors = null;
    },
    [mutationTypes.deleteManagerSuccess](state) {
        state.isSubmitting = false;
    },
    [mutationTypes.deleteManagerFailure](state, payload) {
        state.errors = payload;
        state.isSubmitting = false;
    },
    [mutationTypes.getManagerStart](state) {
        state.isSubmitting = true;
        state.errors = null;
    },
    [mutationTypes.getManagerSuccess](state, payload) {
        state.isSubmitting = false;
        state.manager = payload;
    },
    [mutationTypes.getManagerFailure](state, payload) {
        state.errors = payload;
        state.isSubmitting = false;
    },
    [mutationTypes.updateManagerStart](state) {
        state.isSubmitting = true;
        state.errors = null;
    },
    [mutationTypes.updateManagerSuccess](state, payload) {
        state.isSubmitting = false;
        state.manager = payload;
    },
    [mutationTypes.updateManagerFailure](state, payload) {
        state.errors = payload;
        state.isSubmitting = false;
    },
    [mutationTypes.createManagerStart](state) {
        state.isSubmitting = true;
        state.errors = null;
    },
    [mutationTypes.createManagerSuccess](state) {
        state.isSubmitting = false;
    },
    [mutationTypes.createManagerFailure](state, payload) {
        state.errors = payload;
        state.isSubmitting = false;
    },
};

export const actionTypes = {
    getAllManagers: '[managers] getAllManagers',
    changePage: '[managers] changePage',
    setPageSize: '[managers] setPageSize',
    deleteManager: '[managers] deleteManager',
    getManager: '[managers] getManager',
    updateManager: '[managers] updateManager',
    createManager: '[managers] createManager',
};

const actions = {
    async [actionTypes.getAllManagers]({commit, state}, {page, perPage} = {}) {
        commit(mutationTypes.getAllManagersStart);
        try {
            const currentPage = page || state.pagination.currentPage;
            const currentPerPage = perPage || state.pagination.perPage;
            const response = await managersApi.getAllManagers({page: currentPage, perPage: currentPerPage});
            commit(mutationTypes.getAllManagersSuccess, response.data);
            return response.data.data;
        } catch (error) {
            commit(mutationTypes.getAllManagersFailure, error.response ? error.response.data : error);
            throw error;
        }
    },
    async [actionTypes.changePage]({commit, dispatch}, {page}) {
        commit(mutationTypes.setCurrentPage, page);
        return await dispatch(actionTypes.getAllManagers, {page});
    },
    async [actionTypes.setPageSize]({commit, dispatch, state}, {size}) {
        commit(mutationTypes.setPerPage, size);
        return await dispatch(actionTypes.getAllManagers, {page: state.pagination.currentPage, perPage: size});
    },
    async [actionTypes.deleteManager]({commit, dispatch, state}, {id}) {
        commit(mutationTypes.deleteManagerStart);
        try {
            await managersApi.deleteManager(id);
            dispatch('addSuccess', 'Менеджер удален!', {root: true});
            commit(mutationTypes.deleteManagerSuccess);
            return await dispatch(actionTypes.getAllManagers, {
                page: state.pagination.currentPage,
                perPage: state.pagination.perPage
            });
        } catch (error) {
            dispatch('addError', 'Ошибка удаления!', {root: true});
            commit(mutationTypes.deleteManagerFailure, error.response ? error.response.data : error);
            throw error;
        }
    },
    async [actionTypes.getManager]({commit}, managerId) {
        commit(mutationTypes.getManagerStart);
        try {
            const response = await managersApi.getManagerById(managerId);
            commit(mutationTypes.getManagerSuccess, response.data);
            return response.data;
        } catch (error) {
            commit(mutationTypes.getManagerFailure, error.response ? error.response.data : error);
            throw error;
        }
    },
    async [actionTypes.updateManager]({commit, state, dispatch}, managerData) {
        commit(mutationTypes.updateManagerStart);
        try {
            const response = await managersApi.updateManager(state.manager.id, managerData);
            commit(mutationTypes.updateManagerSuccess, response.data);
            dispatch('addSuccess', 'Менеджер обновлен!', {root: true});
            return response.data;
        } catch (error) {
            commit(mutationTypes.updateManagerFailure, error.response ? error.response.data : error);
            dispatch('addError', 'Ошибка обновления!', {root: true});
            throw error;
        }
    },
    async [actionTypes.createManager]({commit, state, dispatch}, managerData) {
        commit(mutationTypes.createManagerStart);
        try {
            const response = await managersApi.createManager(managerData);
            commit(mutationTypes.createManagerSuccess, response.data);
            dispatch('addSuccess', 'Менеджер создан!', {root: true});
            return response.data.id;
        } catch (error) {
            commit(mutationTypes.createManagerFailure, error.response ? error.response.data : error);
            dispatch('addError', 'Ошибка создания!', {root: true});
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
