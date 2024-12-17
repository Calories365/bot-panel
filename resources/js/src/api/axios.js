import axios from "axios";
import getCookie from "@/helpers/getCookie.js";

axios.defaults.baseURL = window.location.origin;

axios.defaults.withCredentials = true;

axios.interceptors.request.use(config => {
    const token = getCookie('X-XSRF-TOKEN');
    if (token) {
        config.headers['X-XSRF-TOKEN'] = decodeURIComponent(token);
    } else {
        config.headers['X-XSRF-TOKEN'] = '';
    }
    return config;
});

axios.interceptors.request.use(config => {

    config.headers['Accept-Language'] = localStorage.getItem('locale') || 'ru';

    return config;
}, error => {
    return Promise.reject(error);
});

export default axios
