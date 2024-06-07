import adminsApi from "@/api/admins.js";

const state = {
    admins: [],
    admin: {},
    pagination: {
        currentPage: 1,
        perPage: 10,
        totalPages: 100,
    },
    isSubmitting: false,
    errors: null,
};

export const getterTypes = {
    admins: '[admins] allAdmins',
    pagination: '[admins] pagination',
    isSubmitting: '[admins] isSubmitting',
    admin: '[admins] admin',
};

const getters = {
    [getterTypes.admins]: state => state.admins,
    [getterTypes.admin]: state => state.admin,
    [getterTypes.pagination]: state => state.pagination,
    [getterTypes.isSubmitting]: state => state.isSubmitting,
};

export const mutationTypes = {
    getAllAdminsStart: '[admins] getAllAdminsStart',
    getAllAdminsSuccess: '[admins] getAllAdminsSuccess',
    getAllAdminsFailure: '[admins] getAllAdminsFailure',

    setCurrentPage: '[admins] setCurrentPage',
    setPerPage: '[admins] setPerPage',

    deleteAdminStart: '[admins] deleteAdminStart',
    deleteAdminSuccess: '[admins] deleteAdminSuccess',
    deleteAdminFailure: '[admins] deleteAdminFailure',

    getAdminStart: '[admins] getAdminStart',
    getAdminSuccess: '[admins] getAdminSuccess',
    getAdminFailure: '[admins] getAdminFailure',

    updateAdminStart: '[admins] updateAdminStart',
    updateAdminSuccess: '[admins] updateAdminSuccess',
    updateAdminFailure: '[admins] updateAdminFailure',

    createAdminStart: '[admins] createAdminStart',
    createAdminSuccess: '[admins] createAdminSuccess',
    createAdminFailure: '[admins] createAdminFailure',
};

const mutations = {
    [mutationTypes.getAllAdminsStart](state) {
        state.isSubmitting = true;
        state.errors = null;
    },
    [mutationTypes.getAllAdminsSuccess](state, payload) {
        state.admins = payload.data;
        state.pagination.totalPages = payload.meta.last_page;
        state.isSubmitting = false;
    },
    [mutationTypes.getAllAdminsFailure](state, payload) {
        state.errors = payload;
        state.isSubmitting = false;
    },
    [mutationTypes.setCurrentPage](state, pageNumber) {
        state.pagination.currentPage = pageNumber;
    },
    [mutationTypes.setPerPage](state, perPage) {
        state.pagination.perPage = perPage;
    },
    [mutationTypes.deleteAdminStart](state) {
        state.isSubmitting = true;
        state.errors = null;
    },
    [mutationTypes.deleteAdminSuccess](state) {
        state.isSubmitting = false;
    },
    [mutationTypes.deleteAdminFailure](state, payload) {
        state.errors = payload;
        state.isSubmitting = false;
    },
    [mutationTypes.getAdminStart](state) {
        state.isSubmitting = true;
        state.errors = null;
    },
    [mutationTypes.getAdminSuccess](state, payload) {
        state.isSubmitting = false;
        state.admin = payload;
    },
    [mutationTypes.getAdminFailure](state, payload) {
        state.errors = payload;
        state.isSubmitting = false;
    },
    [mutationTypes.updateAdminStart](state) {
        state.isSubmitting = true;
        state.errors = null;
    },
    [mutationTypes.updateAdminSuccess](state, payload) {
        state.isSubmitting = false;
        state.admin = payload;
    },
    [mutationTypes.updateAdminFailure](state, payload) {
        state.errors = payload;
        state.isSubmitting = false;
    },
    [mutationTypes.createAdminStart](state) {
        state.isSubmitting = true;
        state.errors = null;
    },
    [mutationTypes.createAdminSuccess](state) {
        state.isSubmitting = false;
    },
    [mutationTypes.createAdminFailure](state, payload) {
        state.errors = payload;
        state.isSubmitting = false;
    },
};

export const actionTypes = {
    getAllAdmins: '[admins] getAllAdmins',
    changePage: '[admins] changePage',
    setPageSize: '[admins] setPageSize',
    deleteAdmin: '[admins] deleteAdmin',
    getAdmin: '[admins] getAdmin',
    updateAdmin: '[admins] updateAdmin',
    createAdmin: '[admins] createAdmin',
};

const actions = {
    async [actionTypes.getAllAdmins]({commit, state}, {page, perPage} = {}) {
        commit(mutationTypes.getAllAdminsStart);
        try {
            const currentPage = page || state.pagination.currentPage;
            const currentPerPage = perPage || state.pagination.perPage;
            const response = await adminsApi.getAllAdmins({page: currentPage, perPage: currentPerPage});
            commit(mutationTypes.getAllAdminsSuccess, response.data);
            return response.data.data;
        } catch (error) {
            commit(mutationTypes.getAllAdminsFailure, error.response ? error.response.data : error);
            throw error;
        }
    },
    async [actionTypes.changePage]({commit, dispatch}, {page}) {
        commit(mutationTypes.setCurrentPage, page);
        return await dispatch(actionTypes.getAllAdmins, {page});
    },
    async [actionTypes.setPageSize]({commit, dispatch, state}, {size}) {
        commit(mutationTypes.setPerPage, size);
        return await dispatch(actionTypes.getAllAdmins, {page: state.pagination.currentPage, perPage: size});
    },
    async [actionTypes.deleteAdmin]({commit, dispatch, state}, {id}) {
        commit(mutationTypes.deleteAdminStart);
        try {
            await adminsApi.deleteAdmin(id);
            dispatch('addSuccess', 'Адмиин удален!', {root: true});
            commit(mutationTypes.deleteAdminSuccess);
            return await dispatch(actionTypes.getAllAdmins, {
                page: state.pagination.currentPage,
                perPage: state.pagination.perPage
            });
        } catch (error) {
            dispatch('addError', 'Ошибка удаления!', {root: true});
            commit(mutationTypes.deleteAdminFailure, error.response ? error.response.data : error);
            throw error;
        }
    },
    async [actionTypes.getAdmin]({commit}, adminId) {
        commit(mutationTypes.getAdminStart);
        try {
            const response = await adminsApi.getAdminById(adminId);
            commit(mutationTypes.getAdminSuccess, response.data);
            return response.data;
        } catch (error) {
            commit(mutationTypes.getAdminFailure, error.response ? error.response.data : error);
            throw error;
        }
    },
    async [actionTypes.updateAdmin]({commit, state, dispatch}, adminData) {
        commit(mutationTypes.updateAdminStart);
        try {
            const response = await adminsApi.updateAdmin(state.admin.id, adminData);
            commit(mutationTypes.updateAdminSuccess, response.data);
            dispatch('addSuccess', 'Админ обновлен!', {root: true});
            return response.data;
        } catch (error) {
            commit(mutationTypes.updateAdminFailure, error.response ? error.response.data : error);
            dispatch('addError', 'Ошибка обновления!', {root: true});
            throw error;
        }
    },
    async [actionTypes.createAdmin]({commit, state, dispatch}, adminData) {
        commit(mutationTypes.createAdminStart);
        try {
            const response = await adminsApi.createAdmin(adminData);
            commit(mutationTypes.createAdminSuccess, response.data);
            dispatch('addSuccess', 'Админ создан!', {root: true});
            return response.data.id;
        } catch (error) {
            commit(mutationTypes.createAdminFailure, error.response ? error.response.data : error);
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
