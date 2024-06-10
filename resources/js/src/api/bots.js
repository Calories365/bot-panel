import axios from "@/api/axios";

const getAllBots = ({page, perPage}) => {
    return axios.get('/api/bots', {
        params: {
            page: page,
            per_page: perPage
        }
    });
}
const getBotById = (id) => {
    return axios.get(`/api/bots/${id}`);
}
const getBotTypes = () => {
    return axios.get(`/api/bot-types`);
}
const getBotManagers = () => {
    return axios.get(`/api/bot-managers`);
}

const deleteBot = (id) => {
    return axios.delete(`/api/bots/${id}`);
}
const updateBot = (botId, formData) => {
    return axios.post(`/api/bots/update/${botId}`, formData, {
        headers: {
            'Content-Type': 'multipart/form-data'
        }
    });
}
const createBot = (formData) => {
    return axios.post(`/api/bots/create`, formData, {
        headers: {
            'Content-Type': 'multipart/form-data'
        }
    });
}
const updateWebhook = (botId) => {
    return axios.get(`/api/update-webhook/${botId}`);
}
const getBotUserData = (botId) => {
    return axios.get(`/api/get-bot-user-data/${botId}`);
}

export default {
    getAllBots,
    deleteBot,
    getBotById,
    getBotTypes,
    updateBot,
    createBot,
    updateWebhook,
    getBotUserData,
    getBotManagers
}
