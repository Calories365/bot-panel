import axios from "@/api/axios";

const toggleRussianLanguage = (enabled) => {
    return axios.post("/api/language/toggle-russian", { enabled });
};

export default {
    toggleRussianLanguage,
};
