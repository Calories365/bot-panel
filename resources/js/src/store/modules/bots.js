import botsApi from "@/api/bots.js";
import { isSet } from "lodash";

const state = {
    bots: [],
    bot: {},
    pagination: {
        currentPage: 1,
        perPage: 10,
        totalPages: 100,
    },
    isSubmitting: false,
    errors: null,
    botUserData: {},
};

export const getterTypes = {
    errors: "[bots] errors",
    bots: "[bots] allBots",
    pagination: "[bots] pagination",
    isSubmitting: "[bots] isSubmitting",
    bot: "[bots] bot",
    bot_types: "[bots] bot_types",
    botUserData: "[bots] botUserData",
};

const getters = {
    [getterTypes.bots]: (state) => state.bots,
    [getterTypes.pagination]: (state) => state.pagination,
    [getterTypes.isSubmitting]: (state) => state.isSubmitting,
    [getterTypes.bot]: (state) => state.bot,
    [getterTypes.bot_types]: (state) => state.bot_types,
    [getterTypes.botUserData]: (state) => state.botUserData,
};

export const mutationTypes = {
    getAllBotsStart: "[bots] getAllBotsStart",
    getAllBotsSuccess: "[bots] getAllBotsSuccess",
    getAllBotsFailure: "[bots] getAllBotsFailure",

    setCurrentPage: "[bots] setCurrentPage",
    setPerPage: "[bots] setPerPage",

    deleteBotStart: "[bots] deleteBotStart",
    deleteBotSuccess: "[bots] deleteBotSuccess",
    deleteBotFailure: "[bots] deleteBotFailure",

    getBotStart: "[bots] getBotStart",
    getBotSuccess: "[bots] getBotSuccess",
    getBotFailure: "[bots] getBotFailure",

    getBotTypesStart: "[bots] getBotTypesStart",
    getBotTypesSuccess: "[bots] getBotTypesSuccess",
    getBotTypesFailure: "[bots] getBotTypesFailure",

    getBotManagersStart: "[bots] getBotManagersStart",
    getBotManagersSuccess: "[bots] getBotManagersSuccess",
    getBotManagersFailure: "[bots] getBotManagersFailure",

    updateBotStart: "[bots] updateBotStart",
    updateBotSuccess: "[bots] updateBotSuccess",
    updateBotFailure: "[bots] updateBotFailure",

    createBotStart: "[bots] createBotStart",
    createBotSuccess: "[bots] createBotSuccess",
    createBotFailure: "[bots] createBotFailure",

    upsertBotStart: "[bots] upsertBotStart",
    upsertBotSuccess: "[bots] upsertBotSuccess",
    upsertBotFailure: "[bots] upsertBotFailure",

    updateWebhookStart: "[bots] updateWebhookStart",
    updateWebhookSuccess: "[bots] updateWebhookSuccess",
    updateWebhookFailure: "[bots] updateWebhookFailure",

    getBotUserDataStart: "[bots] getBotUserDataStart",
    getBotUserDataSuccess: "[bots] getBotUserDataSuccess",
    getBotUserDataFailure: "[bots] getBotUserDataFailure",

    destroyBot: "[bots] destroyBot",
};

const mutations = {
    [mutationTypes.getAllBotsStart](state) {
        state.isSubmitting = true;
        state.errors = null;
    },
    [mutationTypes.getAllBotsSuccess](state, payload) {
        state.bots = payload.data;
        state.pagination.totalPages = payload.meta.last_page;
        state.isSubmitting = false;
    },
    [mutationTypes.getAllBotsFailure](state, payload) {
        state.errors = payload;
        state.isSubmitting = false;
    },
    [mutationTypes.setCurrentPage](state, pageNumber) {
        state.pagination.currentPage = pageNumber;
    },
    [mutationTypes.setPerPage](state, perPage) {
        state.pagination.perPage = perPage;
    },
    [mutationTypes.deleteBotStart](state) {
        state.isSubmitting = true;
        state.errors = null;
    },
    [mutationTypes.deleteBotSuccess](state) {
        state.isSubmitting = false;
    },
    [mutationTypes.deleteBotFailure](state, payload) {
        state.errors = payload;
        state.isSubmitting = false;
    },
    [mutationTypes.getBotStart](state) {
        state.isSubmitting = true;
        state.errors = null;
    },
    [mutationTypes.getBotSuccess](state, payload) {
        state.isSubmitting = false;
        state.bot = payload;
    },
    [mutationTypes.getBotFailure](state, payload) {
        state.errors = payload;
        state.isSubmitting = false;
    },
    [mutationTypes.getBotTypesStart](state) {
        state.isSubmitting = true;
        state.errors = null;
    },
    [mutationTypes.getBotTypesSuccess](state, payload) {
        state.isSubmitting = false;
        state.bot.bot_types = payload;
    },
    [mutationTypes.getBotTypesFailure](state, payload) {
        state.errors = payload;
        state.isSubmitting = false;
    },
    [mutationTypes.getBotManagersStart](state) {
        state.isSubmitting = true;
        state.errors = null;
    },
    [mutationTypes.getBotManagersSuccess](state, payload) {
        state.isSubmitting = false;
        state.bot.bot_types = payload;
    },
    [mutationTypes.getBotManagersFailure](state, payload) {
        state.errors = payload;
        state.isSubmitting = false;
    },

    [mutationTypes.updateBotStart](state) {
        state.isSubmitting = true;
        state.errors = null;
    },
    [mutationTypes.updateBotSuccess](state, payload) {
        state.isSubmitting = false;
        state.bot = payload;
    },
    [mutationTypes.updateBotFailure](state, payload) {
        state.errors = payload;
        state.isSubmitting = false;
    },
    [mutationTypes.upsertBotStart](state) {
        state.isSubmitting = true;
        state.errors = null;
    },
    [mutationTypes.upsertBotSuccess](state, payload) {
        state.isSubmitting = false;
        state.bot = payload;
    },
    [mutationTypes.upsertBotFailure](state, payload) {
        state.errors = payload;
        state.isSubmitting = false;
    },

    [mutationTypes.createBotStart](state) {
        state.isSubmitting = true;
        state.errors = null;
    },
    [mutationTypes.createBotSuccess](state, payload) {
        state.isSubmitting = false;
        state.bot = payload;
    },
    [mutationTypes.createBotFailure](state, payload) {
        state.errors = payload;
        state.isSubmitting = false;
    },

    [mutationTypes.updateWebhookStart](state) {
        state.isSubmitting = true;
        state.errors = null;
    },
    [mutationTypes.updateWebhookSuccess](state, payload) {
        state.isSubmitting = false;
        state.bot = payload;
    },
    [mutationTypes.updateWebhookFailure](state, payload) {
        state.errors = payload;
        state.isSubmitting = false;
    },

    [mutationTypes.getBotUserDataStart](state) {
        state.isSubmitting = true;
        state.errors = null;
    },
    [mutationTypes.getBotUserDataSuccess](state, payload) {
        state.isSubmitting = false;
        state.botUserData = payload;
    },
    [mutationTypes.getBotUserDataFailure](state, payload) {
        state.errors = payload;
        state.isSubmitting = false;
    },

    [mutationTypes.destroyBot](state) {
        state.bot = {};
        state.botUserData = {};
    },
};

export const actionTypes = {
    getAllBots: "[bots] getAllBots",
    changePage: "[bots] changePage",
    setPageSize: "[bots] setPageSize",
    deleteBot: "[bots] deleteBot",
    getBot: "[bots] getBot",
    getBotTypes: "[bots] getBotTypes",
    getBotManagers: "[bots] getBotManagers",
    updateBot: "[bots] updateBot",
    createBot: "[bots] createBot",
    updateWebhook: "[bots] updateWebhook",
    getBotUserData: "[bots] getBotUserData",
    destroyBot: "[bots] destroyBot",
};

const actions = {
    async [actionTypes.getAllBots]({ commit, state }, { page, perPage } = {}) {
        commit(mutationTypes.getAllBotsStart);
        try {
            const currentPage = page || state.pagination.currentPage;
            const currentPerPage = perPage || state.pagination.perPage;
            const response = await botsApi.getAllBots({
                page: currentPage,
                perPage: currentPerPage,
            });
            commit(mutationTypes.getAllBotsSuccess, response.data);
            return response.data.data;
        } catch (error) {
            commit(
                mutationTypes.getAllBotsFailure,
                error.response ? error.response.data : error,
            );
            throw error;
        }
    },
    async [actionTypes.changePage]({ commit, dispatch }, { page }) {
        commit(mutationTypes.setCurrentPage, page);
        return await dispatch(actionTypes.getAllBots, { page });
    },
    async [actionTypes.setPageSize]({ commit, dispatch, state }, { size }) {
        commit(mutationTypes.setPerPage, size);
        return await dispatch(actionTypes.getAllBots, {
            page: state.pagination.currentPage,
            perPage: size,
        });
    },
    async [actionTypes.deleteBot]({ commit, dispatch, state }, { id }) {
        commit(mutationTypes.deleteBotStart);
        try {
            await botsApi.deleteBot(id);
            commit(mutationTypes.deleteBotSuccess);
            dispatch("addSuccess", "Бот Удален!", { root: true });
            return await dispatch(actionTypes.getAllBots, {
                page: state.pagination.currentPage,
                perPage: state.pagination.perPage,
            });
        } catch (error) {
            dispatch("addError", "Ошибка удаления!", { root: true });
            commit(
                mutationTypes.deleteBotFailure,
                error.response ? error.response.data : error,
            );
            throw error;
        }
    },
    async [actionTypes.getBot]({ commit }, botId) {
        commit(mutationTypes.getBotStart);
        try {
            const response = await botsApi.getBotById(botId);
            commit(mutationTypes.getBotSuccess, response.data);
            return response.data;
        } catch (error) {
            commit(
                mutationTypes.getBotFailure,
                error.response ? error.response.data : error,
            );
            throw error;
        }
    },
    async [actionTypes.getBotTypes]({ commit }) {
        commit(mutationTypes.getBotTypesStart);
        try {
            const response = await botsApi.getBotTypes();
            commit(mutationTypes.getBotTypesSuccess, response.data);
            return response.data;
        } catch (error) {
            commit(
                mutationTypes.getBotTypesFailure,
                error.response ? error.response.data : error,
            );
            throw error;
        }
    },
    async [actionTypes.getBotManagers]({ commit }) {
        commit(mutationTypes.getBotManagersStart);
        try {
            const response = await botsApi.getBotManagers();
            commit(mutationTypes.getBotManagersSuccess, response.data);
            return response.data;
        } catch (error) {
            commit(
                mutationTypes.getBotManagersFailure,
                error.response ? error.response.data : error,
            );
            throw error;
        }
    },
    async [actionTypes.updateBot]({ commit, dispatch, state }, data) {
        try {
            const response = handleBotData(
                commit,
                dispatch,
                botsApi.updateBot,
                data,
                state.bot.id,
            );
            dispatch("addSuccess", "Бот обновлен!", { root: true });
            return response;
        } catch (errors) {
            dispatch("addError", "Ошибка обновления!", { root: true });
        }
    },
    async [actionTypes.createBot]({ commit, dispatch }, data) {
        try {
            const response = await handleBotData(
                commit,
                dispatch,
                botsApi.createBot,
                data,
            );
            dispatch("addSuccess", "Бот создан!", { root: true });
            return response;
        } catch (errors) {
            dispatch("addError", "Ошибка создания!", { root: true });
        }
    },
    async [actionTypes.updateWebhook]({ commit, state }) {
        commit(mutationTypes.updateWebhookStart);

        try {
            const response = await botsApi.updateWebhook(state.bot.id);
            commit(mutationTypes.updateWebhookSuccess, response.data);
            return response.data.id;
        } catch (error) {
            commit(
                mutationTypes.updateWebhookFailure,
                error.response ? error.response.data : error,
            );
            throw error;
        }
    },
    async [actionTypes.getBotUserData]({ commit, state }) {
        commit(mutationTypes.getBotUserDataStart);

        try {
            const response = await botsApi.getBotUserData(state.bot.id);
            commit(mutationTypes.getBotUserDataSuccess, response.data);
            return response.data;
        } catch (error) {
            commit(
                mutationTypes.getBotUserDataFailure,
                error.response ? error.response.data : error,
            );
            throw error;
        }
    },
    async [actionTypes.destroyBot]({ commit }) {
        commit(mutationTypes.destroyBot);
    },
};

async function handleBotData(
    commit,
    dispatch,
    botApiFunction,
    botData,
    botId = null,
) {
    commit(mutationTypes.upsertBotStart);

    try {
        normalizeFiles(botData);

        botData.type_id = botData.type_id.type_id;
        botData.managers = botData.managers.managers;

        const formData = new FormData();
        for (const key in botData) {
            if (botData.hasOwnProperty(key)) {
                if (
                    key === "managers" &&
                    botData.managers &&
                    botData.managers.length > 0
                ) {
                    formData.append(
                        "managers",
                        JSON.stringify(botData.managers),
                    );
                } else if (botData[key] instanceof File) {
                    formData.append(key, botData[key], botData[key].name);
                } else {
                    formData.append(key, botData[key]);
                }
            }
        }

        const response = await botApiFunction(
            botId ? botId : formData,
            formData,
        );
        commit(mutationTypes.upsertBotSuccess, response.data);
        return response.data;
    } catch (error) {
        commit(mutationTypes.upsertBotFailure, error);
        const errorMessage = error.response ? error.response.data : error;
        commit(mutationTypes.upsertBotFailure, errorMessage);
        throw error;
    }
}
function normalizeFiles(botData) {
    for (const key in botData) {
        if (botData.hasOwnProperty(key)) {
            const value = botData[key];
            if (value && typeof value === "object" && "image_file" in value) {
                if (value.image_file instanceof File) {
                    botData[key] = value.image_file;
                } else if (value.file instanceof File) {
                    botData[key] = value.file;
                } else {
                    delete botData[key];
                }
            }
        }
    }
}

export default {
    state,
    getters,
    mutations,
    actions,
};
