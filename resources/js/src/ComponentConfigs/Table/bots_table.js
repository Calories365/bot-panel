export const bots_table = [
    {label: 'ID', key: 'id', type: 'default', action: null, limit: 40},
    {label: 'Username', key: 'name', type: 'link', action: 'show', limit: 40},
    {label: 'Token', key: 'token', type: 'default', action: null, limit: 100},
    {label: 'Message', key: 'message', type: 'default', action: null, limit: 40},
    {label: 'Active', key: 'active', type: 'checkbox', action: null, limit: 40},
    {label: 'Delete', key: 'delete', type: 'button', action: 'delete', limit: 40},
];
