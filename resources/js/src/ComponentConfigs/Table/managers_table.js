export const managers_table = [
    { label: "ID", key: "id", type: "default", action: null, limit: 40 },
    { label: "Username", key: "name", type: "link", action: "show", limit: 40 },
    {
        label: "Delete",
        key: "delete",
        type: "button",
        action: "delete",
        limit: 40,
    },
];
