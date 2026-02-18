<?php

namespace App\Console\Commands;

use App\Jobs\ProcessTelegramUpdate;
use App\Models\Bot;
use App\Models\BotUser;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class BenchmarkCommand extends Command
{
    protected $signature = 'app:benchmark
        {--audio-dir= : Directory with .ogg audio files}
        {--lang=ua : Language code (ua/en)}
        {--runs=3 : Number of runs per audio file}
        {--warmup=1 : Warmup runs (not recorded)}
        {--concurrency=1 : Number of parallel requests}
        {--output= : CSV output directory}
        {--label=default : Label for this benchmark run}
        {--model=gpt-4o : Model name for CSV}
        {--gpu=cloud : GPU name for CSV}
        {--bot=calories365KNU_bot : Bot name to use}
        {--timeout=180 : Max seconds to wait for each result}';

    protected $description = 'Run end-to-end voice pipeline benchmark — collects raw data for post-processing';

    private string $csvPath;

    private int $benchmarkUserId = 999999;

    private int $benchmarkCaloriesId = 999999;

    private array $reference = [];

    public function handle(): int
    {
        if (! config('app.benchmark_mode')) {
            $this->error('BENCHMARK_MODE is not enabled. Set BENCHMARK_MODE=true in .env and restart containers.');

            return 1;
        }

        $audioDir = $this->option('audio-dir') ?: base_path('tests/Fixtures/Benchmarks/Audio');
        $lang = $this->option('lang');
        $runs = (int) $this->option('runs');
        $warmup = (int) $this->option('warmup');
        $concurrency = (int) $this->option('concurrency');
        $label = $this->option('label');
        $model = $this->option('model');
        $gpu = $this->option('gpu');
        $botName = $this->option('bot');
        $timeout = (int) $this->option('timeout');

        $outputDir = $this->option('output') ?: storage_path('benchmarks');
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        $this->csvPath = $outputDir.'/benchmark_'.date('Y-m-d_His').'_'.$label.'.csv';

        // Load reference data (simple format: {"filename": "spoken text", ...}).
        $refPath = base_path('tests/Fixtures/Benchmarks/reference.json');
        if (file_exists($refPath)) {
            $this->reference = json_decode(file_get_contents($refPath), true) ?? [];
            $this->info('Reference data loaded: '.count($this->reference).' entries');
        } else {
            $this->warn('No reference.json found — spoken_text_expected will be empty');
        }

        // Find audio files.
        $audioFiles = glob($audioDir.'/'.$lang.'_*.ogg');
        if (empty($audioFiles)) {
            $audioFiles = glob($audioDir.'/*.ogg');
        }

        if (empty($audioFiles)) {
            $this->error("No .ogg files found in {$audioDir}");

            return 1;
        }

        // Limit audio files to concurrency count — concurrency defines how many
        // files are sent simultaneously in a single batch.
        if ($concurrency < count($audioFiles)) {
            $audioFiles = array_slice($audioFiles, 0, $concurrency);
        }

        $this->info("Benchmark: {$label}");
        $this->info("Model: {$model} | GPU: {$gpu} | Lang: {$lang} | Concurrency: {$concurrency}");
        $this->info('Audio files: '.count($audioFiles));
        $this->info("Runs: {$runs} + {$warmup} warmup");
        $this->info("Output: {$this->csvPath}");
        $this->line('');

        $bot = Bot::where('name', $botName)->first();
        if (! $bot) {
            $this->error("Bot '{$botName}' not found in database.");

            return 1;
        }

        $this->ensureBenchmarkUser($bot, $lang);
        $this->writeCsvHeader();

        // Warmup.
        if ($warmup > 0) {
            $this->info("Running {$warmup} warmup request(s)...");
            for ($w = 0; $w < $warmup; $w++) {
                $this->dispatchAndWait($bot, $audioFiles[$w % count($audioFiles)], $lang, $timeout);
                $this->output->write('.');
            }
            $this->line(' done');
            $this->line('');
        }

        // Build task list: all selected files dispatched simultaneously per run.
        $tasks = [];
        for ($run = 1; $run <= $runs; $run++) {
            foreach ($audioFiles as $audioFile) {
                $tasks[] = ['file' => $audioFile, 'run' => $run];
            }
        }

        $totalRequests = count($tasks);
        $completed = 0;

        $this->info("Starting {$totalRequests} benchmark requests (concurrency={$concurrency})...");
        $bar = $this->output->createProgressBar($totalRequests);
        $bar->start();

        $batches = array_chunk($tasks, $concurrency);

        foreach ($batches as $batch) {
            if ($concurrency === 1) {
                $task = $batch[0];
                $t0 = microtime(true);
                $result = $this->dispatchAndWait($bot, $task['file'], $lang, $timeout);
                $totalMs = (microtime(true) - $t0) * 1000;

                $this->writeResultRow($label, $model, $gpu, $lang, $concurrency, $task, $result, $totalMs);
                $completed++;
                $bar->advance();
            } else {
                // Parallel dispatch.
                $pending = [];
                $startTimes = [];

                foreach ($batch as $i => $task) {
                    $req = $this->dispatchRequest($bot, $task['file'], $lang);
                    $pending[$i] = $req;
                    $startTimes[$i] = microtime(true);
                }

                // Poll all simultaneously — record time when each result arrives.
                $results = [];
                $endTimes = [];
                $deadline = time() + $timeout;

                while (count($results) < count($pending) && time() < $deadline) {
                    foreach ($pending as $i => $req) {
                        if (isset($results[$i])) {
                            continue;
                        }
                        $value = Redis::lpop('benchmark:results:'.$req['request_id']);
                        if ($value) {
                            $endTimes[$i] = microtime(true);
                            $data = json_decode($value, true);
                            $timing = Redis::hgetall('benchmark:timing:'.$req['request_id']);
                            Redis::del('benchmark:timing:'.$req['request_id']);
                            $results[$i] = array_merge($data ?? [], $timing ?? []);
                        }
                    }
                    if (count($results) < count($pending)) {
                        usleep(100000);
                    }
                }

                // Write rows and clean up.
                foreach ($pending as $i => $req) {
                    Redis::del([
                        'benchmark:audio:'.$req['request_id'],
                        'benchmark:fileid_to_request:'.$req['file_id'],
                    ]);

                    $result = $results[$i] ?? null;
                    $totalMs = isset($endTimes[$i])
                        ? ($endTimes[$i] - $startTimes[$i]) * 1000
                        : (time() - $startTimes[$i]) * 1000;

                    if (! $result) {
                        $this->warn("Timeout waiting for request {$req['request_id']}");
                    }

                    $this->writeResultRow($label, $model, $gpu, $lang, $concurrency, $batch[$i], $result, $totalMs);
                    $completed++;
                    $bar->advance();
                }
            }
        }

        $bar->finish();
        $this->line('');
        $this->line('');
        $this->info("Benchmark complete. {$completed}/{$totalRequests} requests processed.");
        $this->info("Results saved to: {$this->csvPath}");

        return 0;
    }

    private function dispatchAndWait(Bot $bot, string $audioFilePath, string $lang, int $timeout): ?array
    {
        $req = $this->dispatchRequest($bot, $audioFilePath, $lang);

        return $this->waitForResult($req['request_id'], $req['file_id'], $timeout);
    }

    private function dispatchRequest(Bot $bot, string $audioFilePath, string $lang): array
    {
        $requestId = Str::uuid()->toString();
        $updateId = random_int(100000000, 999999999);
        $fileId = 'benchmark_file_'.Str::random(20);

        Redis::setex('benchmark:audio:'.$requestId, 600, $audioFilePath);
        Redis::setex('benchmark:fileid_to_request:'.$fileId, 600, $requestId);

        $languageCode = $lang === 'ua' ? 'uk' : $lang;
        $payload = [
            'update_id' => $updateId,
            'message' => [
                'message_id' => random_int(1000, 99999),
                'from' => [
                    'id' => $this->benchmarkUserId,
                    'is_bot' => false,
                    'first_name' => 'Benchmark',
                    'username' => 'benchmark_user',
                    'language_code' => $languageCode,
                ],
                'chat' => [
                    'id' => $this->benchmarkUserId,
                    'first_name' => 'Benchmark',
                    'username' => 'benchmark_user',
                    'type' => 'private',
                ],
                'date' => time(),
                'voice' => [
                    'duration' => 3,
                    'mime_type' => 'audio/ogg',
                    'file_id' => $fileId,
                    'file_unique_id' => 'bench_'.Str::random(10),
                    'file_size' => filesize($audioFilePath) ?: 10000,
                ],
            ],
        ];

        ProcessTelegramUpdate::dispatch($bot->name, $payload, $updateId)
            ->onQueue('telegram');

        return [
            'request_id' => $requestId,
            'file_id' => $fileId,
        ];
    }

    private function waitForResult(string $requestId, string $fileId, int $timeout): ?array
    {
        $deadline = time() + $timeout;

        while (time() < $deadline) {
            $value = Redis::lpop('benchmark:results:'.$requestId);
            if ($value) {
                $data = json_decode($value, true);
                $timing = Redis::hgetall('benchmark:timing:'.$requestId);

                Redis::del([
                    'benchmark:audio:'.$requestId,
                    'benchmark:fileid_to_request:'.$fileId,
                    'benchmark:timing:'.$requestId,
                ]);

                return array_merge($data ?? [], $timing ?? []);
            }
            usleep(250000);
        }

        Redis::del([
            'benchmark:audio:'.$requestId,
            'benchmark:fileid_to_request:'.$fileId,
        ]);

        $this->warn("Timeout waiting for request {$requestId}");

        return null;
    }

    private function writeResultRow(
        string $label,
        string $model,
        string $gpu,
        string $lang,
        int $concurrency,
        array $task,
        ?array $result,
        float $totalMs
    ): void {
        $audioName = basename($task['file']);
        $run = $task['run'];

        // Strip language prefix to find reference key: en_01_cottage_cheese.ogg → 01_cottage_cheese.ogg
        $refKey = preg_replace('/^[a-z]{2}_/', '', $audioName);
        $spokenTextExpected = '';

        // Support both formats: simple string or object with spoken_text.
        if (isset($this->reference[$refKey])) {
            $ref = $this->reference[$refKey];
            $spokenTextExpected = is_string($ref) ? $ref : ($ref['spoken_text'] ?? '');
        }

        $whisperText = $result['whisper_text'] ?? '';
        $llmResponse = $result['llm_analyze_response'] ?? '';
        $whisperMs = $result['whisper_ms'] ?? '';
        $llmAnalyzeMs = $result['llm_analyze_ms'] ?? '';
        $llmGenerateMs = $result['llm_generate_ms'] ?? '';
        $llmAnalyzePromptTokens = $result['llm_analyze_prompt_tokens'] ?? '';
        $llmAnalyzeCompletionTokens = $result['llm_analyze_completion_tokens'] ?? '';
        $llmGeneratePromptTokens = $result['llm_generate_prompt_tokens'] ?? '';
        $llmGenerateCompletionTokens = $result['llm_generate_completion_tokens'] ?? '';

        // Extract product names from bot messages ("You said: X" lines).
        $detectedProducts = $this->extractDetectedProducts($result);
        $botMessages = $this->extractBotTexts($result);

        $row = [
            'label' => $label,
            'model' => $model,
            'gpu' => $gpu,
            'audio_file' => $audioName,
            'lang' => $lang,
            'run' => $run,
            'concurrency' => $concurrency,
            'total_latency_ms' => round($totalMs, 2),
            'whisper_latency_ms' => $whisperMs,
            'llm_analyze_ms' => $llmAnalyzeMs,
            'llm_generate_ms' => $llmGenerateMs,
            'llm_analyze_prompt_tokens' => $llmAnalyzePromptTokens,
            'llm_analyze_completion_tokens' => $llmAnalyzeCompletionTokens,
            'llm_generate_prompt_tokens' => $llmGeneratePromptTokens,
            'llm_generate_completion_tokens' => $llmGenerateCompletionTokens,
            'spoken_text_expected' => $spokenTextExpected,
            'whisper_text_actual' => $whisperText,
            'detected_products' => $detectedProducts,
            'llm_detection_response' => $llmResponse,
            'bot_messages' => $botMessages,
            'timestamp' => now()->toIso8601String(),
        ];

        if (! $result) {
            $row['whisper_text_actual'] = '';
            $row['llm_detection_response'] = 'TIMEOUT_OR_ERROR';
            $row['bot_messages'] = '';
            $row['detected_products'] = '';
        }

        $this->writeCsvRow($row);
    }

    /**
     * Extract product names from "You said: X" / "Ви сказали: X" lines in bot messages.
     */
    private function extractDetectedProducts(?array $result): string
    {
        if (! isset($result['messages']) || ! is_array($result['messages'])) {
            return '';
        }

        $products = [];
        foreach ($result['messages'] as $msg) {
            $text = $msg['text'] ?? '';
            if (preg_match('/(?:Вы сказали|Ви сказали|You said)[:\s]*(.+)/ui', $text, $m)) {
                $products[] = trim($m[1]);
            }
        }

        return implode('; ', $products);
    }

    private function extractBotTexts(?array $result): string
    {
        if (! isset($result['messages']) || ! is_array($result['messages'])) {
            return '';
        }

        $texts = array_map(fn ($m) => $m['text'] ?? '', $result['messages']);

        return implode(' ||| ', array_filter($texts));
    }

    // ─── User / subscription setup ──────────────────────────────────────

    private function ensureBenchmarkUser(Bot $bot, string $lang): void
    {
        $botUser = BotUser::where('telegram_id', $this->benchmarkUserId)->first();

        if (! $botUser) {
            $botUser = BotUser::create([
                'telegram_id' => $this->benchmarkUserId,
                'bot_id' => $bot->id,
                'calories_id' => $this->benchmarkCaloriesId,
                'locale' => $lang,
                'name' => 'Benchmark User',
                'username' => 'benchmark_user',
            ]);

            $this->info("Created benchmark user (telegram_id={$this->benchmarkUserId})");
        } else {
            $botUser->update(['locale' => $lang, 'bot_id' => $bot->id]);
        }

        Subscription::updateOrCreate(
            ['user_id' => $this->benchmarkCaloriesId],
            ['premium' => true, 'transcribe_counter' => 0]
        );
    }

    // ─── CSV output ─────────────────────────────────────────────────────

    private function writeCsvHeader(): void
    {
        $headers = [
            'label', 'model', 'gpu', 'audio_file', 'lang', 'run', 'concurrency',
            'total_latency_ms', 'whisper_latency_ms', 'llm_analyze_ms', 'llm_generate_ms',
            'llm_analyze_prompt_tokens', 'llm_analyze_completion_tokens',
            'llm_generate_prompt_tokens', 'llm_generate_completion_tokens',
            'spoken_text_expected', 'whisper_text_actual',
            'detected_products', 'llm_detection_response', 'bot_messages', 'timestamp',
        ];

        file_put_contents($this->csvPath, implode(',', $headers)."\n");
    }

    private function writeCsvRow(array $row): void
    {
        $fields = [
            $row['label'],
            $row['model'],
            $row['gpu'],
            $row['audio_file'],
            $row['lang'],
            $row['run'],
            $row['concurrency'],
            $row['total_latency_ms'],
            $row['whisper_latency_ms'],
            $row['llm_analyze_ms'],
            $row['llm_generate_ms'],
            $row['llm_analyze_prompt_tokens'],
            $row['llm_analyze_completion_tokens'],
            $row['llm_generate_prompt_tokens'],
            $row['llm_generate_completion_tokens'],
            $this->csvEscape((string) $row['spoken_text_expected']),
            $this->csvEscape((string) $row['whisper_text_actual']),
            $this->csvEscape((string) $row['detected_products']),
            $this->csvEscape((string) $row['llm_detection_response']),
            $this->csvEscape((string) $row['bot_messages']),
            $row['timestamp'],
        ];

        file_put_contents($this->csvPath, implode(',', $fields)."\n", FILE_APPEND);
    }

    private function csvEscape(string $value): string
    {
        $value = str_replace(["\n", "\r"], [' ', ''], $value);

        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, ' ')) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }
}
