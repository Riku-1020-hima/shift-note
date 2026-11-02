<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_error_pages_are_in_japanese_with_how_to_fix(): void
    {
        $this->get('/s/this-url-does-not-exist')->assertNotFound()
            ->assertSee('ページが見つかりません')->assertSee('こうすると直ります');

        foreach ([419 => '画面の有効期限が切れました', 429 => '短い時間に操作が集中しました', 500 => 'うまく表示できませんでした', 503 => 'ただいまメンテナンス中です'] as $code => $title) {
            $html = view("errors.$code")->render();
            $this->assertStringContainsString($title, $html);
            $this->assertStringContainsString('こうすると直ります', $html);
        }
    }
}
