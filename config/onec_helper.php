<?php

/*
| The 1C helper bot: the owners forward questions about the 1C base to it, the
| bot gathers each into one question, and a worker on the 1C server takes the
| questions through /api/onec-helper and sends back what it found.
*/

return [

    // Telegram ids, comma separated, of those the bot answers; it ignores everyone else.
    'owner_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('ONEC_HELPER_OWNER_IDS', ''))
    ))),

    // The bearer token of the worker on the 1C server.
    'worker_token' => env('ONEC_HELPER_WORKER_TOKEN'),

    // A question is closed after this many seconds without a new message.
    'batch_seconds' => (int) env('ONEC_HELPER_BATCH_SECONDS', 60),

    // The files of a question older than this are deleted from the disk.
    'retention_days' => (int) env('ONEC_HELPER_RETENTION_DAYS', 30),

    // The model that gives a closed question its title, summary and entities,
    // through the OpenAI settings of services.openai. The cheap text model the
    // diary reads short texts with.
    'model' => 'gpt-6-luna',
    'reasoning_effort' => 'low',

    // Not the public disk: the files are screenshots of the accounting base.
    'disk' => 'local',
    'directory' => 'onec-helper',

    // The service message of a question being gathered is edited at most this often.
    'edit_interval_seconds' => 3,

    // A note of the worker's heartbeat is shown at most this often.
    'note_interval_seconds' => 30,

    // Supervision: a question waiting this long is reported to the owner once; a
    // job without a heartbeat this long goes back to the queue, up to max_attempts.
    'queued_alert_minutes' => 5,
    'heartbeat_timeout_minutes' => 15,
    'max_attempts' => 3,

];
