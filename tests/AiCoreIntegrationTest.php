<?php

namespace LaravelCore\Tests;

use Duxbo\AiCore\AiManager;
use Duxbo\AiCore\Data\AiRequest;

/**
 * Proves the actual point of requiring duxbo/laravel-ai-core in
 * composer.json: a kit that only depends on laravel-core gets AI for
 * free — no separate `composer require`, no wiring of its own — because
 * laravel-ai-core is installed transitively and auto-discovers itself.
 */
class AiCoreIntegrationTest extends TestCase
{
    public function test_ai_manager_is_available_once_laravel_core_alone_is_required(): void
    {
        $this->assertInstanceOf(AiManager::class, $this->app->make(AiManager::class));
    }

    public function test_the_null_driver_is_the_default_so_installing_never_bills_anyone(): void
    {
        // ai-core logs every call to its own `ai_core_log` table by default;
        // this package doesn't publish/run ai-core's migrations for it, so
        // logging is switched off here — that table is ai-core's own concern
        // to test, not laravel-core's.
        config(['ai-core.log' => false]);

        $response = $this->app->make(AiManager::class)->complete(new AiRequest(
            prompt: 'hello',
            schema: ['type' => 'object', 'properties' => ['reply' => ['type' => 'string']]],
        ));

        $this->assertSame('null', $response->driver);
    }
}
