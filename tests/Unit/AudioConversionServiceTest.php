<?php

namespace Tests\Unit;

use App\Services\AudioConversionService;
use App\Services\ChatGPTServices\SpeechToTextService;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class AudioConversionServiceTest extends TestCase
{
    private AudioConversionService $service;

    private string $ogaPath;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        /* ----------------------------------------------------------------
         |  alias-мок FFMpeg: create → open → save
         |  save() не только «возвращает true», но и создаёт mp3-файл
         *----------------------------------------------------------------*/
        Mockery::mock('alias:\FFMpeg\FFMpeg', function ($m) {
            $m->shouldReceive('create')->andReturnSelf();
            $m->shouldReceive('open')->andReturnSelf();
            $m->shouldReceive('save')
                ->andReturnUsing(function ($format, $fullPath) {
                    $relative = str_replace(
                        Storage::disk('public')->path(''),
                        '',
                        $fullPath
                    );
                    Storage::disk('public')->put($relative, 'dummy-mp3');

                    return true;
                });
            $m->shouldIgnoreMissing();
        });

        $this->service = new AudioConversionService(
            $this->createMock(SpeechToTextService::class)
        );

        $this->ogaPath = 'audios/voice.oga';
        Storage::disk('public')->put($this->ogaPath, 'dummy-oga');
    }

    /** @test */
    public function converts_oga_to_mp3(): void
    {
        [$rel, $abs] = $this->service->convertToMp3(
            $this->ogaPath,
            Storage::disk('public')->path($this->ogaPath)
        );

        $this->assertNotNull($rel);
        $this->assertStringEndsWith('.mp3', $rel);
        Storage::disk('public')->assertExists($rel);

        /* cleanup */
        Storage::disk('public')->delete($rel);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
