import adminsApi from "@/api/admins.js";

const state = {
    admins: [],
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
};

const getters = {
    [getterTypes.admins]: state => state.admins,
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
};

export const actionTypes = {
    getAllAdmins: '[admins] getAllAdmins',
    changePage: '[admins] changePage',
    setPageSize: '[admins] setPageSize',
    deleteAdmin: '[admins] deleteAdmin',
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
            commit(mutationTypes.deleteAdminSuccess);
            return await dispatch(actionTypes.getAllAdmins, {
                page: state.pagination.currentPage,
                perPage: state.pagination.perPage
            });
        } catch (error) {
            commit(mutationTypes.deleteAdminFailure, error.response ? error.response.data : error);
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
