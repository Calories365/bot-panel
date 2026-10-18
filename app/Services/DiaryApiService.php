<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiaryApiService
{
    private Client $client;

    private string $apiUrl;

    private mixed $host;

    private string $diaryApiKey;

    public function __construct()
    {
        $this->diaryApiKey = config('services.diary_api.key');
        $this->apiUrl = config('services.diary_api.url');
        $this->client = new Client;
        $this->host = config('services.diary_api.host');
    }

    /**
     * @param  int|null  $caloriesId
     * @param  string|null  $locale
     */
    private function getHeaders($caloriesId = null, $locale = null, $admin = null, bool $multipart = false): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Host' => $this->host,
            'X-Api-Key' => $this->diaryApiKey,
        ];

        // A multipart body sets its own Content-Type with the boundary.
        if (! $multipart) {
            $headers['Content-Type'] = 'application/json';
        }

        if (! empty($caloriesId)) {
            $headers['X-Calories-Id'] = $caloriesId;
        }

        if (! empty($locale)) {
            $headers['X-Locale'] = $locale;
        }

        if (! empty($admin)) {
            $headers['X-admin'] = true;
        }

        return $headers;
    }

    public function showUserStats($date, $partOfDay, $calories_id, $locale)
    {
        try {
            $url = $this->apiUrl.'/caloriesEndPoint/showUserStats/'.$date;
            if ($partOfDay) {
                $url .= '/'.$partOfDay;
            }

            $response = $this->client->get($url, [
                'headers' => $this->getHeaders($calories_id, $locale),
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::error('Error retrieving user stats from diary service: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    public function deleteMeal($mealId, $calories_id, $locale)
    {
        try {
            $response = $this->client->delete($this->apiUrl.'/caloriesEndPoint/deleteMeal/'.$mealId, [
                'headers' => $this->getHeaders($calories_id, $locale),
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::error('Error deleting meal in diary service: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    public function checkTelegramCode(string $code, int $telegram_id, string $locale = 'en')
    {
        try {
            $response = $this->client->post($this->apiUrl.'/caloriesEndPoint/checkTelegramCode', [
                'headers' => $this->getHeaders($telegram_id, $locale),
                'json' => [
                    'code' => $code,
                    'telegram_id' => $telegram_id,
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::error('Error verifying telegram code: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    public function showUsersInfoForBot($calories_id, string $locale = 'en')
    {
        try {
            $response = $this->client->get($this->apiUrl.'/caloriesEndPoint/user-for-bot', [
                'headers' => $this->getHeaders($calories_id, $locale),
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::error('Error verifying telegram code: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    public function showUsersInfoForBotMultiple(array $caloriesIds, string $locale = 'en'): array
    {
        $idsParam = implode(',', $caloriesIds);

        $url = $this->apiUrl.'/caloriesEndPoint/users-for-bot-multiple?ids='.$idsParam;

        try {
            $response = $this->client->get($url, [
                'headers' => $this->getHeaders(null, null, true),
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::error('Error retrieving multiple users info: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    public function fetchAllCaloriesUsers(string $locale = 'en'): array
    {
        $url = $this->apiUrl.'/caloriesEndPoint/all-users';

        try {
            $response = $this->client->get($url, [
                'headers' => $this->getHeaders(null, null, true),
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::error('Error retrieving all users info: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Apply a text or a photo to the user's meal draft in the diary.
     *
     * The diary reads it with its meal model, which takes several seconds for a
     * photo. Answers {draft, understood, not_found}, or ['error' => …, 'status' => …]:
     * status 503 when the diary could not read the input.
     *
     * @param  array{contents: string, name: string}|null  $photo
     * @return array<string, mixed>
     */
    public function draftInput(?string $text, ?array $photo, $caloriesId, string $locale): array
    {
        try {
            $request = Http::timeout(90)->withHeaders($this->getHeaders($caloriesId, $locale, null, multipart: true));

            if ($photo !== null) {
                $request = $request->attach('photo', $photo['contents'], $photo['name']);
            }

            $response = $request->asMultipart()->post($this->apiUrl.'/caloriesEndPoint/draft/input', $text !== null && $text !== '' ? ['text' => $text] : []);
            $response->throw();

            return $response->json();
        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error('Error sending input to the meal draft: '.$e->getMessage());

            return ['error' => $e->getMessage(), 'status' => $e->response->status()];
        } catch (\Throwable $e) {
            Log::error('Error sending input to the meal draft: '.$e->getMessage());

            return ['error' => $e->getMessage(), 'status' => 0];
        }
    }

    /**
     * Keep a voice message (its words, kind voice), a text or a photo with its
     * caption for the calculation of the user's meal draft; a short edit changes
     * the draft at once. Answers {draft, inputs, calculation, understood,
     * not_found}, or ['error' => …, 'status' => …, 'message' => …]: message
     * too_many_inputs when the calculation takes no more.
     *
     * @param  array{contents: string, name: string}|null  $photo
     * @return array<string, mixed>
     */
    public function draftCollect(string $kind, ?string $text, ?array $photo, $caloriesId, string $locale): array
    {
        try {
            $request = Http::timeout(45)->withHeaders($this->getHeaders($caloriesId, $locale, null, multipart: true));

            if ($photo !== null) {
                $request = $request->attach('photo', $photo['contents'], $photo['name']);
            }

            $fields = array_filter(['kind' => $photo === null ? $kind : null, 'text' => $text], static fn ($value) => $value !== null && $value !== '');
            $response = $request->asMultipart()->post($this->apiUrl.'/caloriesEndPoint/draft/inputs', $fields);
            $response->throw();

            return $response->json();
        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error('Error keeping an input for the meal draft: '.$e->getMessage());

            return ['error' => $e->getMessage(), 'status' => $e->response->status(), 'message' => $e->response->json('message')];
        } catch (\Throwable $e) {
            Log::error('Error keeping an input for the meal draft: '.$e->getMessage());

            return ['error' => $e->getMessage(), 'status' => 0, 'message' => null];
        }
    }

    /**
     * Start the calculation of the inputs waiting; the diary runs it in its
     * queue and answers at once with {draft, inputs, calculation}, or
     * ['error' => …, 'status' => …, 'message' => …]: message
     * nothing_to_calculate when no input waits.
     *
     * @return array<string, mixed>
     */
    public function draftCalculate($caloriesId, string $locale): array
    {
        try {
            $response = Http::timeout(45)
                ->withHeaders($this->getHeaders($caloriesId, $locale))
                ->post($this->apiUrl.'/caloriesEndPoint/draft/calculate');

            $response->throw();

            return $response->json();
        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error('Error starting the meal draft calculation: '.$e->getMessage());

            return ['error' => $e->getMessage(), 'status' => $e->response->status(), 'message' => $e->response->json('message')];
        } catch (\Throwable $e) {
            Log::error('Error starting the meal draft calculation: '.$e->getMessage());

            return ['error' => $e->getMessage(), 'status' => 0, 'message' => null];
        }
    }

    /**
     * The user's meal draft, the inputs waiting and the state of the
     * calculation: {draft, inputs, calculation}, or ['error' => …].
     *
     * @return array<string, mixed>
     */
    public function draftShow($caloriesId, string $locale): array
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders($caloriesId, $locale))
                ->get($this->apiUrl.'/caloriesEndPoint/draft');

            $response->throw();

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('Error reading the meal draft: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Save the user's meal draft as a meal of the day; answers {total}, or
     * ['error' => …, 'status' => …]: status 404 when there is no draft any more.
     *
     * @return array<string, mixed>
     */
    public function draftSave(string $partOfDay, string $date, $caloriesId, string $locale): array
    {
        try {
            $response = Http::timeout(45)
                ->withHeaders($this->getHeaders($caloriesId, $locale))
                ->post($this->apiUrl.'/caloriesEndPoint/draft/save', [
                    'part_of_day' => $partOfDay,
                    'consumed_at' => $date,
                ]);

            $response->throw();

            return $response->json();
        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error('Error saving the meal draft: '.$e->getMessage());

            return ['error' => $e->getMessage(), 'status' => $e->response->status()];
        } catch (\Throwable $e) {
            Log::error('Error saving the meal draft: '.$e->getMessage());

            return ['error' => $e->getMessage(), 'status' => 0];
        }
    }

    /**
     * Drop the user's meal draft.
     *
     * @return array<string, mixed>
     */
    public function draftCancel($caloriesId, string $locale): array
    {
        try {
            $response = Http::timeout(45)
                ->withHeaders($this->getHeaders($caloriesId, $locale))
                ->delete($this->apiUrl.'/caloriesEndPoint/draft');

            $response->throw();

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('Error cancelling the meal draft: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Toggle Russian language setting
     */
    public function toggleRussianLanguage(bool $enabled): array
    {
        try {
            $response = $this->client->post($this->apiUrl.'/caloriesEndPoint/toggleRussianLanguage', [
                'headers' => $this->getHeaders(null, null, true),
                'json' => [
                    'enabled' => $enabled,
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::error('Error toggling Russian language: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }
}
