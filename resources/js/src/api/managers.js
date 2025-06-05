import axios from "@/api/axios";

const getAllManagers = ({ page, perPage }) => {
    return axios.get("/api/managers", {
        params: {
            page: page,
            per_page: perPage,
        },
    });
};
const getManagerById = (id) => {
    return axios.get(`/api/managers/${id}`);
};
const deleteManager = (id) => {
    return axios.delete(`/api/managers/${id}`);
};

const createManager = (managerData) => {
    return axios.post("/api/managers", managerData);
};

const updateManager = (id, managerData) => {
    return axios.put(`/api/managers/${id}`, managerData);
};

export default {
    getAllManagers,
    getManagerById,
    deleteManager,
    createManager,
    updateManager,
};
