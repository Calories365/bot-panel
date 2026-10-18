<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Calories365 bot lang
    |--------------------------------------------------------------------------
    |
    |
    */

    'auth_required' => 'You must be authorized!',
    'error_retrieving_data' => 'An error occurred while retrieving data. Please try again later.',
    'no_entries_for_date' => 'You have no entries for the date *:date*.',
    'no_entries_for_part_of_day' => 'You have no entries :partOfDayText.',
    'your_data_for_date' => 'Your data for *:date*:',
    'breakfast' => 'Breakfast',
    'lunch' => 'Lunch',
    'dinner' => 'Dinner',
    'calories' => 'Calories',
    'proteins' => 'Proteins',
    'fats' => 'Fats',
    'carbohydrates' => 'Carbohydrates',
    'total_for_day' => 'Total for the day',
    'total_for_part_of_day' => 'Total for :partOfDayName',
    'delete' => 'Delete',
    'you_said' => 'You said: ',
    'parameter' => 'Parameter',
    '100g' => '100g',
    'for_100g' => 'for 100g',
    'g' => 'g',
    'editing_session_expired' => 'Editing session has expired or does not exist.',
    'product_not_found' => 'The product was not found or the session time has expired.',
    'editing_canceled' => 'Editing canceled.',
    'step_skipped' => 'Step skipped.',
    'please_enter_new_quantity_of_grams' => 'Please enter the new quantity of grams.',
    'please_enter_new_calories' => 'Please enter the new number of calories.',
    'please_enter_new_proteins' => 'Please enter the new number of proteins.',
    'please_enter_new_fats' => 'Please enter the new number of fats.',
    'please_enter_new_carbohydrates' => 'Please enter the new number of carbohydrates.',
    'error_editing_product' => 'An error occurred while editing the product.',
    'exit_edit_mode' => 'Exit edit mode (press save or cancel).',
    'action_canceled_product_list_cleared' => 'Action canceled. Your product list has been cleared.',
    'cancellation_completed' => 'Cancellation completed',
    'product_list_is_empty_or_was_cleared' => 'Your product list is empty or has already been cleared.',
    'list_is_already_empty' => 'The list is already empty',
    'product_removed_from_list' => 'Product removed from the list.',
    'error_deleting_product' => 'Помилка при видаленні продукту.',
    'product_deleted' => 'Продукт видалено.',
    'you_are_editing_product' => 'You are editing the product: *:productName*',
    'please_enter_new_product_name' => 'Please enter the new product name.',
    'save' => 'Save',
    'skip_step' => 'Skip step',
    'cancel' => 'Cancel',
    'invalid_request' => 'Invalid request.',
    'data_saved_you_consumed' => 'The data has been saved, you have consumed',
    'grams' => 'grams',
    'error_processing_data' => 'An error occurred while processing data.',
    'error_generating_data' => 'An error occurred while generating data.',
    'failed_to_get_product_data' => 'Failed to get product data.',
    'cannot_generate_product_data' => 'Sorry, I could not generate data for this product. Try editing it manually.',
    'product_data_updated' => 'Product data updated.',
    'value_too_long' => 'Value is too long',
    'enter_valid_numeric_value_for_grams' => 'Please enter a valid numeric value for grams.',
    'enter_valid_numeric_value_for_calories' => 'Please enter a valid numeric value for calories.',
    'enter_valid_numeric_value_for_proteins' => 'Please enter a valid numeric value for proteins.',
    'enter_valid_numeric_value_for_fats' => 'Please enter a valid numeric value for fats.',
    'enter_valid_numeric_value_for_carbohydrates' => 'Please enter a valid numeric value for carbohydrates.',
    'please_choose_your_language' => 'Please choose your language',
    'language_set_english' => 'Your language has been set to English.',
    'language_set_russian' => 'Your language has been set to Russian.',
    'language_set_ukrainian' => 'Your language has been set to Ukrainian.',
    'invalid_or_used_code' => 'Invalid or used code. Please register again.',
    'seems_you_are_new' => 'It looks like you are new here. To link your account, use the "Connect" link from your personal account (https://calculator.calories365.com?lang=en).',
    'error_occurred' => 'An error occurred: ',
    'incomplete_product_info' => 'Product information is incomplete.',
    'save_products_for' => 'Save products for:',
    'products_not_found' => 'No products found.',
    'failed_to_recognize_audio_message' => 'Failed to recognize audio message.',
    'not_an_audio_message_received' => 'Not an audio message received.',
    'changes_saved' => 'Changes saved.',
    'changes_canceled' => 'Changes canceled.',
    'message_not_modified' => 'Message not modified, no update required.',
    'search' => 'Search',
    'generate_with_ai' => 'Generate with AI',
    'edit' => 'Edit',
    'you_must_be_authorized' => 'You must be authorized!',
    'no_entries_remain' => "There's no products left",
    'subscription_required_message' => 'Request limit reached, to save meals without restrictions — purchase premium in the personal account (https://calculator.calories365.com?lang=en)',
    'voice_rate_limited' => 'Too many voice messages in a row. Please try again in :minutes min.',

    'data_not_extracted' => 'Failed to extract data.',
    'welcome_guide' => <<<'EOT'
How to keep the diary in the bot:

1. Tell what you ate: a voice message, text or a photo of the plate. For example: "buckwheat 200 grams, a cutlet and tea with two spoons of sugar".
2. A photo of a label with its nutrition table gives the exact values from the package.
3. The bot shows the meal in one message. Correct it in words: "buckwheat was 250", "remove the tea", "2 150" sets the grams of item 2. Or press "Edit" and correct it in the app.
4. Press "Breakfast", "Lunch" or "Dinner" to save the meal to the diary.

The diary, statistics and the calorie calculator are in the app: the ":open" button below or https://calculator.calories365.com?lang=en

The guide is always under the "How to use" button.
EOT,

    'welcome_guide_KNU' => <<<'EOT'
How to keep the diary in the bot:

1. Tell what you ate: a voice message, text or a photo of the plate. For example: "buckwheat 200 grams, a cutlet and tea with two spoons of sugar".
2. A photo of a label with its nutrition table gives the exact values from the package.
3. The bot shows the meal in one message. Correct it in words: "buckwheat was 250", "remove the tea", "2 150" sets the grams of item 2. Or press "Edit" and correct it in the app.
4. Press "Breakfast", "Lunch" or "Dinner" to save the meal to the diary.

The diary, statistics and the calorie calculator are in the app: the ":open" button below or https://calculator.calories365.xyz?lang=en

The guide is always under the "How to use" button.
EOT,
    'open_mini_app' => '🥗 Open the app',
    'menu' => 'Menu',
    'statistics' => 'Statistics',
    'choose_language' => 'Choose language',
    'feedback' => 'Feedback',
    'whole_day' => 'Whole day',
    'send_feedback_email' => 'You can send your feedback to the administrator:  @calories365_admin',
    'generate_with_ai' => 'Generate with AI',
    // Font settings
    'font' => 'Font',
    'big_font_question' => 'Do you prefer large font?',
    'yes' => 'Yes',
    'no' => 'No',
    'big_font_enabled' => 'Large font enabled',
    'regular_font_selected' => 'Regular font selected',
    'you_consumed' => 'You consumed',
    'found' => 'Found',

    // Meal draft
    'draft_title' => 'Meal',
    'draft_item' => ':number. :name — :grams g, :calories kcal',
    'draft_total' => 'Total: :calories kcal',
    'draft_macros' => 'P :proteins · F :fats · C :carbohydrates',
    'draft_edited' => 'changed',
    'draft_label' => 'from the label',
    'draft_not_found' => 'Not found: :names',
    'draft_hint' => 'Correct it by voice or text: "buckwheat was 250", "remove 1", "and an apple". Or press "Edit".',
    'draft_not_understood' => 'No food found in the message. Tell by voice or text what you ate, or send a photo of the dish or of the label.',
    'draft_not_read' => 'Could not read the message, please try again.',
    'draft_failed' => 'The diary does not answer right now, please try later.',
    'draft_cancelled' => 'The meal is cancelled.',
    'draft_gone' => 'This meal is already saved or cancelled.',
    'draft_rate_limited' => 'Too many messages in a row. Please try again in :minutes min.',
    'photo_not_downloaded' => 'Could not load the photo, please send it again.',
    'outdated_message' => 'This message is out of date. Tell about the meal again.',
    'how_to_use' => 'How to use',
    'draft_edit' => 'Edit',
];
