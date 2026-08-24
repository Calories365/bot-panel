<?php

namespace Tests\Unit;

use App\Services\TelegramServices\WebAppInitDataService;
use Tests\TestCase;

class WebAppInitDataServiceTest extends TestCase
{
    private const TOKEN = '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11';

    private WebAppInitDataService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WebAppInitDataService;
    }

    public function test_it_accepts_init_data_signed_with_the_bot_token(): void
    {
        $parsed = $this->service->parse($this->makeInitData());

        $this->assertTrue($this->service->signatureMatches($parsed, self::TOKEN));
    }

    public function test_it_rejects_init_data_signed_with_another_token(): void
    {
        $parsed = $this->service->parse($this->makeInitData());

        $this->assertFalse($this->service->signatureMatches($parsed, '999999:another-bot-token'));
    }

    public function test_it_rejects_tampered_payload(): void
    {
        $parsed = $this->service->parse($this->makeInitData());
        $parsed['user'] = json_encode(['id' => 999, 'first_name' => 'Mallory']);

        $this->assertFalse($this->service->signatureMatches($parsed, self::TOKEN));
    }

    public function test_it_extracts_the_telegram_user(): void
    {
        $parsed = $this->service->parse($this->makeInitData());

        $user = $this->service->extractUser($parsed);

        $this->assertSame(55555, $user['id']);
        $this->assertSame('Max', $user['first_name']);
    }

    public function test_it_returns_null_when_init_data_carries_no_user(): void
    {
        $parsed = $this->service->parse($this->makeInitData(withUser: false));

        $this->assertNull($this->service->extractUser($parsed));
    }

    private function makeInitData(bool $withUser = true): string
    {
        $fields = [
            'auth_date' => (string) time(),
            'query_id' => 'AAHdF6IQAAAAAN0XohDhrOrc',
        ];

        if ($withUser) {
            $fields['user'] = json_encode([
                'id' => 55555,
                'first_name' => 'Max',
                'username' => 'max',
                'language_code' => 'uk',
            ], JSON_UNESCAPED_UNICODE);
        }

        ksort($fields);

        $pairs = [];
        foreach ($fields as $key => $value) {
            $pairs[] = $key.'='.$value;
        }

        $secret = hash_hmac('sha256', self::TOKEN, 'WebAppData', true);
        $fields['hash'] = hash_hmac('sha256', implode("\n", $pairs), $secret);

        return http_build_query($fields);
    }
}
