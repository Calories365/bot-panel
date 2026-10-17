<?php

namespace Tests\Unit;

use App\Services\ChatGPTServices\SpeechToTextService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\TestFixtures;

/**
 * The service talks to OpenAI through the Http facade, so Http::fake stands in
 * for both calls. An earlier version of this test injected a Guzzle client the
 * service no longer uses — it would have reached the real API.
 */
class SpeechToTextServiceTest extends TestCase
{
    use TestFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        app()->setLocale('ru');
    }

    public function test_it_transcribes_audio_and_returns_the_product_list(): void
    {
        $transcript = $this->loadFixture('OpenAI/whisper_success.json')['text'];

        Http::fake([
            '*/audio/transcriptions' => Http::response(['text' => $transcript]),
            '*/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'Творог - 225 граммов;']]]]),
        ]);

        $result = (new SpeechToTextService)->convertSpeechToText(base_path('tests/Fixtures/Audio/file.mp3'));

        $this->assertSame('Творог - 225 граммов;', $result);

        // gpt-transcribe takes "languages[]"; gpt-4o and whisper-1 are switched off in 2027.
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/audio/transcriptions')
            && $this->part($request, 'model') === 'gpt-transcribe'
            && $this->part($request, 'languages[]') === 'ru'
            && $this->part($request, 'language') === null);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/chat/completions')
            && $request['model'] === 'gpt-6.1-sol'
            && $request['reasoning_effort'] === 'low'
            && ! isset($request['temperature'], $request['max_tokens'])
            && str_contains($request['messages'][0]['content'], $transcript));
    }

    public function test_nutrition_facts_of_a_product_use_the_efficient_model(): void
    {
        Http::fake(['*/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'Калории - 120;']]]])]);

        $this->assertSame('Калории - 120;', (new SpeechToTextService)->generateNewProductData('Творог'));

        Http::assertSent(fn (Request $request) => $request['model'] === 'gpt-6-luna');
    }

    private function part(Request $request, string $name): ?string
    {
        $part = collect($request->data())->firstWhere('name', $name);

        return $part['contents'] ?? null;
    }

    public function test_it_returns_the_error_when_the_analysis_call_fails(): void
    {
        Http::fake([
            '*/audio/transcriptions' => Http::response(['text' => 'Творог 200 грамм']),
            '*/chat/completions' => Http::response(['error' => 'down'], 500),
        ]);

        $result = (new SpeechToTextService)->convertSpeechToText(base_path('tests/Fixtures/Audio/file.mp3'));

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
    }
}
