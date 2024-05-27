import axios from "@/api/axios";

const getAllUsers = ({page, perPage}) => {
    return axios.get('/api/users', {
        params: {
            page: page,
            per_page: perPage
        }
    });
}

const deleteUser = (id) => {
    return axios.delete(`/api/users/${id}`);
}

export default {
    getAllUsers,
    deleteUser
}
