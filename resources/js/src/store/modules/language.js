import languageApi from "@/api/language.js";

const state = {
    isSubmitting: false,
    errors: null,
    russianLanguageEnabled: false,
};

export const getterTypes = {
    isSubmitting: "[language] isSubmitting",
    errors: "[language] errors",
    russianLanguageEnabled: "[language] russianLanguageEnabled",
};

const getters = {
    [getterTypes.isSubmitting]: (state) => state.isSubmitting,
    [getterTypes.errors]: (state) => state.errors,
    [getterTypes.russianLanguageEnabled]: (state) =>
        state.russianLanguageEnabled,
};

export const mutationTypes = {
    toggleRussianLanguageStart: "[language] toggleRussianLanguageStart",
    toggleRussianLanguageSuccess: "[language] toggleRussianLanguageSuccess",
    toggleRussianLanguageFailure: "[language] toggleRussianLanguageFailure",
};

const mutations = {
    [mutationTypes.toggleRussianLanguageStart](state) {
        state.isSubmitting = true;
        state.errors = null;
    },
    [mutationTypes.toggleRussianLanguageSuccess](state, payload) {
        state.isSubmitting = false;
        state.russianLanguageEnabled = payload.data.russian_language_enabled;
    },
    [mutationTypes.toggleRussianLanguageFailure](state, payload) {
        state.isSubmitting = false;
        state.errors = payload;
    },
};

export const actionTypes = {
    toggleRussianLanguage: "toggleRussianLanguage",
};

const actions = {
    async [actionTypes.toggleRussianLanguage](
        { commit, dispatch },
        { enabled }
    ) {
        commit(mutationTypes.toggleRussianLanguageStart);
        try {
            const response = await languageApi.toggleRussianLanguage(enabled);
            commit(mutationTypes.toggleRussianLanguageSuccess, response.data);
            dispatch("addSuccess", response.data.message, { root: true });
            return response.data;
        } catch (error) {
            const errorMessage =
                error.response?.data?.message ||
                "Failed to toggle Russian language";
            dispatch("addError", errorMessage, { root: true });
            commit(
                mutationTypes.toggleRussianLanguageFailure,
                error.response?.data || error
            );
            throw error;
        }
    },
};

export default {
    namespaced: true,
    state,
    getters,
    mutations,
    actions,
};
