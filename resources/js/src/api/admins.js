import axios from "@/api/axios";

const getAllAdmins = ({page, perPage}) => {
    return axios.get('/api/admins', {
        params: {
            page: page,
            per_page: perPage
        }
    });
}
const getAdminById = (id) => {
    return axios.get(`/api/admins/${id}`);
}
const deleteAdmin = (id) => {
    return axios.delete(`/api/admins/${id}`);
}

const createAdmin = (adminData) => {
    return axios.post('/api/admins', adminData);
}

const updateAdmin = (id, adminData) => {
    return axios.put(`/api/admins/${id}`, adminData);
}

export default {
    getAllAdmins,
    deleteAdmin,
    createAdmin,
    updateAdmin,
    getAdminById
}
