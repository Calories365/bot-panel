import axios from "@/api/axios";

const getAllUsers = ({ page, perPage, botId }) => {
    return axios.get("/api/users", {
        params: {
            page: page,
            per_page: perPage,
            botId: botId,
        },
    });
};

const deleteUser = (id) => {
    return axios.delete(`/api/users/${id}`);
};
const exportUsers = ({ botId }) => {
    return axios.get("/api/users/export", {
        params: {
            botId: botId,
        },
    });
};

export default {
    getAllUsers,
    deleteUser,
    exportUsers,
};
