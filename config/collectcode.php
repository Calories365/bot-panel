<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Collectcode
    |--------------------------------------------------------------------------
    |
    |
    */

    'paths' => [
        'app/Services/TelegramServices/BaseService.php',
        'app/Services/TelegramServices/CaloriesService.php',
        'app/Services/TelegramServices/TelegramHandler.php',
        'app/Services/DiaryApiService.php',
        'app/Services/ChatGPTService.php',
        'app/Services/TelegramServices/CaloriesHandlers',
        'app/Services/TelegramServices/ApprovalHandlers',

    ],
    'output_path' => storage_path('collected_code.txt'),
    'extensions' => [
        'php',
        'js',
        'txt',
    ],

];
