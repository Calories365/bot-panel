export const admin_rows = [
    {
        label: 'Name', key: 'name', type: 'default', emit_name: null, placeholder: 'enter name', action: null,
        required: true
    },
    {
        label: 'Telegram ID',
        key: 'telegram_id',
        type: 'default',
        placeholder: 'enter telegram_id',
        action: null,
        required: true
    },
    {
        label: 'Actions', key: 'actions', type: 'buttons', emit_name: null, placeholder: null,
        options: [
            {text: 'Save', button_type: 'default', action: 'submit'},
            {text: 'Delete', button_type: 'danger', action: 'delete'},
        ]
    }
];

