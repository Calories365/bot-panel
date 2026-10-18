<?php

namespace Tests\Unit;

use App\Services\ChatGPTServices\SpeechToTextService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\TestFixtures;

/**
 * The service talks to OpenAI through the Http facade, so Http::fake stands in
 * for the call. The panel only transcribes speech; the diary reads what it says
 * in the meal draft.
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

    public function test_it_transcribes_audio_with_gpt_transcribe(): void
    {
        $transcript = $this->loadFixture('OpenAI/whisper_success.json')['text'];

        Http::fake(['*/audio/transcriptions' => Http::response(['text' => $transcript])]);

        $this->assertSame(trim($transcript), (new SpeechToTextService)->transcribe(base_path('tests/Fixtures/Audio/file.mp3')));

        // gpt-transcribe takes "languages[]"; whisper-1 is switched off in 2027.
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/audio/transcriptions')
            && $this->part($request, 'model') === 'gpt-transcribe'
            && $this->part($request, 'languages[]') === 'ru'
            && $this->part($request, 'language') === null);
        Http::assertSentCount(1);
    }

    public function test_a_recording_without_words_gives_false(): void
    {
        Http::fake(['*/audio/transcriptions' => Http::response(['text' => '  '])]);

        $this->assertFalse((new SpeechToTextService)->transcribe(base_path('tests/Fixtures/Audio/file.mp3')));
    }

    public function test_a_failed_transcription_is_thrown(): void
    {
        Http::fake(['*/audio/transcriptions' => Http::response(['error' => 'down'], 500)]);

        $this->expectException(RequestException::class);

        (new SpeechToTextService)->transcribe(base_path('tests/Fixtures/Audio/file.mp3'));
    }

    private function part(Request $request, string $name): ?string
    {
        $part = collect($request->data())->firstWhere('name', $name);

        return $part['contents'] ?? null;
    }
}
