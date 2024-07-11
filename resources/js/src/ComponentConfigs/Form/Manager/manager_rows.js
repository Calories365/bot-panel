export const manager_rows = [
    {
        label: 'Имя', key: 'name', type: 'default', emit_name: null, placeholder: 'введите имя', action: null,
        required: true
    },
    {
        label: 'Telegram id',
        key: 'telegram_id',
        type: 'default',
        placeholder: 'введите telegram_id',
        action: null,
        required: true
    },
    {
        label: 'Действия', key: 'actions', type: 'buttons', emit_name: null, placeholder: null,
        options: [
            {text: 'Сохранить', button_type: 'default', action: 'submit'},
            {text: 'Удалить', button_type: 'danger', action: 'delete'},
        ]
    }
];
