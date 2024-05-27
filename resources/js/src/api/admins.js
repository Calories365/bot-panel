import axios from "@/api/axios";

const getAllAdmins = ({page, perPage}) => {
    return axios.get('/api/admins', {
        params: {
            page: page,
            per_page: perPage
        }
    });
}

const deleteAdmin = (id) => {
    return axios.delete(`/api/admins/${id}`);
}

export default {
    getAllAdmins,
    deleteAdmin
}
