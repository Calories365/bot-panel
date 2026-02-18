<?php

namespace App\Console\Commands;

use App\Jobs\ProcessTelegramUpdate;
use App\Models\Bot;
use App\Models\BotUser;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class BenchmarkGenerateCommand extends Command
{
    protected $signature = 'app:benchmark-generate
        {--lang=en : Language code (ua/en)}
        {--runs=3 : Total number of requests (cycles through product list)}
        {--warmup=1 : Warmup runs (not recorded)}
        {--concurrency=1 : Number of parallel requests}
        {--output= : CSV output directory}
        {--label=default : Label for this benchmark run}
        {--model=gpt-4o : Model name for CSV}
        {--gpu=cloud : GPU name for CSV}
        {--bot=calories365KNU_bot : Bot name to use}
        {--timeout=60 : Max seconds to wait for each result}';

    protected $description = 'Run KBJU generation benchmark — measures AI product data generation latency';

    private string $csvPath;

    private int $benchmarkUserId = 999999;

    private int $benchmarkCaloriesId = 999999;

    public function handle(): int
    {
        if (! config('app.benchmark_mode')) {
            $this->error('BENCHMARK_MODE is not enabled. Set BENCHMARK_MODE=true in .env and restart containers.');

            return 1;
        }

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
        $this->csvPath = $outputDir.'/benchmark-generate_'.date('Y-m-d_His').'_'.$label.'.csv';

        // Load product list for the specified language.
        $productsFile = "{$lang}_generate_products.json";
        $productsPath = base_path('tests/Fixtures/Benchmarks/'.$productsFile);
        if (! file_exists($productsPath)) {
            $this->error("Products file not found: {$productsPath}");

            return 1;
        }

        $products = json_decode(file_get_contents($productsPath), true);
        if (empty($products)) {
            $this->error("No products found in {$productsFile}");

            return 1;
        }

        $this->info("Benchmark Generate: {$label}");
        $this->info("Model: {$model} | GPU: {$gpu} | Lang: {$lang} | Concurrency: {$concurrency}");
        $this->info('Products available: '.count($products));
        $this->info("Runs: {$runs} + {$warmup} warmup (1 product per run)");
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
                $productName = $products[$w % count($products)];
                $this->dispatchAndWait($bot, $productName, $lang, $timeout);
                $this->output->write('.');
            }
            $this->line(' done');
            $this->line('');
        }

        // Build task list: 1 product per run, cycling through the product list.
        $tasks = [];
        for ($run = 1; $run <= $runs; $run++) {
            $productName = $products[($run - 1) % count($products)];
            $tasks[] = ['product_name' => $productName, 'run' => $run];
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
                $result = $this->dispatchAndWait($bot, $task['product_name'], $lang, $timeout);
                $totalMs = (microtime(true) - $t0) * 1000;

                $this->writeResultRow($label, $model, $gpu, $lang, $concurrency, $task, $result, $totalMs);
                $completed++;
                $bar->advance();
            } else {
                // Parallel dispatch: prepare all requests, set shared cache, then dispatch.
                $pending = [];
                $startTimes = [];
                $mergedProducts = [];

                foreach ($batch as $i => $task) {
                    $req = $this->prepareRequest($bot, $task['product_name'], $lang);
                    $pending[$i] = $req;
                    $mergedProducts[$req['product_id']] = $req['cache_entry'];
                }

                // Single cache write with ALL products for the batch.
                Cache::put("user_products_{$this->benchmarkUserId}", $mergedProducts, now()->addMinutes(10));
                Cache::forget("command_block{$this->benchmarkUserId}");

                // Now dispatch all jobs.
                foreach ($pending as $i => $req) {
                    Cache::put("product_click_count_{$this->benchmarkUserId}_{$req['product_id']}", 1, now()->addMinutes(10));
                    ProcessTelegramUpdate::dispatch($bot->name, $req['payload'], $req['update_id'])
                        ->onQueue('telegram');
                    $startTimes[$i] = microtime(true);
                }

                // Poll all simultaneously.
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
                            $results[$i] = array_merge($data ?? [], $timing);
                        }
                    }
                    if (count($results) < count($pending)) {
                        usleep(100000);
                    }
                }

                // Write rows and clean up.
                Cache::forget("user_products_{$this->benchmarkUserId}");
                foreach ($pending as $i => $req) {
                    Redis::del('benchmark:callbackid_to_request:'.$req['callback_query_id']);
                    Cache::forget("product_click_count_{$this->benchmarkUserId}_{$req['product_id']}");

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

    private function dispatchAndWait(Bot $bot, string $productName, string $lang, int $timeout): ?array
    {
        $req = $this->prepareRequest($bot, $productName, $lang);

        // Serial mode: set cache with single product and dispatch.
        Cache::put("user_products_{$this->benchmarkUserId}", [$req['product_id'] => $req['cache_entry']], now()->addMinutes(10));
        Cache::put("product_click_count_{$this->benchmarkUserId}_{$req['product_id']}", 1, now()->addMinutes(10));
        Cache::forget("command_block{$this->benchmarkUserId}");

        ProcessTelegramUpdate::dispatch($bot->name, $req['payload'], $req['update_id'])
            ->onQueue('telegram');

        return $this->waitForResult($req, $timeout);
    }

    /**
     * Prepare request data without setting cache or dispatching.
     * Used by both serial (dispatchAndWait) and parallel paths.
     */
    private function prepareRequest(Bot $bot, string $productName, string $lang): array
    {
        $requestId = Str::uuid()->toString();
        $updateId = random_int(100000000, 999999999);
        $callbackQueryId = 'benchmark_cb_'.Str::random(20);
        $productId = abs(crc32($productName.'_'.$requestId));
        $messageId = random_int(1000, 99999);

        // Store callback_query.id → request_id mapping for BenchmarkTelegramHandler.
        Redis::setex('benchmark:callbackid_to_request:'.$callbackQueryId, 600, $requestId);

        $cacheEntry = [
            'product_translation' => [
                'id' => $productId,
                'product_id' => $productId,
                'locale' => $lang,
                'name' => $productName,
                'said_name' => $productName,
                'original_name' => '',
            ],
            'product' => [
                'id' => $productId,
                'user_id' => null,
                'calories' => 0,
                'proteins' => 0,
                'fats' => 0,
                'carbohydrates' => 0,
                'fibers' => 0,
                'quantity_grams' => 100,
                'edited' => 0,
                'verified' => 0,
            ],
            'message_id' => $messageId,
        ];

        $languageCode = $lang === 'ua' ? 'uk' : $lang;
        $payload = [
            'update_id' => $updateId,
            'callback_query' => [
                'id' => $callbackQueryId,
                'from' => [
                    'id' => $this->benchmarkUserId,
                    'is_bot' => false,
                    'first_name' => 'Benchmark',
                    'username' => 'benchmark_user',
                    'language_code' => $languageCode,
                ],
                'message' => [
                    'message_id' => $messageId,
                    'from' => [
                        'id' => $bot->id,
                        'is_bot' => true,
                        'first_name' => $bot->name,
                    ],
                    'chat' => [
                        'id' => $this->benchmarkUserId,
                        'first_name' => 'Benchmark',
                        'username' => 'benchmark_user',
                        'type' => 'private',
                    ],
                    'date' => time(),
                    'text' => 'Product card placeholder',
                ],
                'chat_instance' => (string) random_int(1000000000, 9999999999),
                'data' => 'search_'.$productId,
            ],
        ];

        return [
            'request_id' => $requestId,
            'update_id' => $updateId,
            'callback_query_id' => $callbackQueryId,
            'product_id' => $productId,
            'product_name' => $productName,
            'cache_entry' => $cacheEntry,
            'payload' => $payload,
        ];
    }

    private function waitForResult(array $req, int $timeout): ?array
    {
        $deadline = time() + $timeout;

        while (time() < $deadline) {
            $value = Redis::lpop('benchmark:results:'.$req['request_id']);
            if ($value) {
                $data = json_decode($value, true);
                $timing = Redis::hgetall('benchmark:timing:'.$req['request_id']);

                Redis::del([
                    'benchmark:timing:'.$req['request_id'],
                    'benchmark:callbackid_to_request:'.$req['callback_query_id'],
                ]);
                Cache::forget("user_products_{$this->benchmarkUserId}");
                Cache::forget("product_click_count_{$this->benchmarkUserId}_{$req['product_id']}");

                return array_merge($data ?? [], $timing);
            }
            usleep(250000);
        }

        Redis::del('benchmark:callbackid_to_request:'.$req['callback_query_id']);
        Cache::forget("user_products_{$this->benchmarkUserId}");
        Cache::forget("product_click_count_{$this->benchmarkUserId}_{$req['product_id']}");

        $this->warn("Timeout waiting for request {$req['request_id']}");

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
        $productName = $task['product_name'];
        $run = $task['run'];

        $llmGenerateMs = $result['llm_generate_ms'] ?? '';
        $llmGeneratePromptTokens = $result['llm_generate_prompt_tokens'] ?? '';
        $llmGenerateCompletionTokens = $result['llm_generate_completion_tokens'] ?? '';
        $generatedKbjuRaw = $result['llm_generate_raw'] ?? '';
        $botMessage = $this->extractEditedMessage($result);

        $row = [
            'label' => $label,
            'model' => $model,
            'gpu' => $gpu,
            'product_name' => $productName,
            'lang' => $lang,
            'run' => $run,
            'concurrency' => $concurrency,
            'total_latency_ms' => round($totalMs, 2),
            'llm_generate_ms' => $llmGenerateMs,
            'llm_generate_prompt_tokens' => $llmGeneratePromptTokens,
            'llm_generate_completion_tokens' => $llmGenerateCompletionTokens,
            'generated_kbju_raw' => $generatedKbjuRaw,
            'bot_message' => $botMessage,
            'timestamp' => now()->toIso8601String(),
        ];

        if (! $result) {
            $row['generated_kbju_raw'] = 'TIMEOUT_OR_ERROR';
            $row['bot_message'] = '';
        }

        $this->writeCsvRow($row);
    }

    /**
     * Extract the formatted product table from editMessageText call.
     */
    private function extractEditedMessage(?array $result): string
    {
        if (! isset($result['messages']) || ! is_array($result['messages'])) {
            return '';
        }

        foreach ($result['messages'] as $msg) {
            if (($msg['_method'] ?? '') === 'editMessageText') {
                return $msg['text'] ?? '';
            }
        }

        return '';
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
            'label', 'model', 'gpu', 'product_name', 'lang', 'run', 'concurrency',
            'total_latency_ms', 'llm_generate_ms',
            'llm_generate_prompt_tokens', 'llm_generate_completion_tokens',
            'generated_kbju_raw', 'bot_message', 'timestamp',
        ];

        file_put_contents($this->csvPath, implode(',', $headers)."\n");
    }

    private function writeCsvRow(array $row): void
    {
        $fields = [
            $row['label'],
            $row['model'],
            $row['gpu'],
            $this->csvEscape($row['product_name']),
            $row['lang'],
            $row['run'],
            $row['concurrency'],
            $row['total_latency_ms'],
            $row['llm_generate_ms'],
            $row['llm_generate_prompt_tokens'],
            $row['llm_generate_completion_tokens'],
            $this->csvEscape((string) $row['generated_kbju_raw']),
            $this->csvEscape((string) $row['bot_message']),
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
