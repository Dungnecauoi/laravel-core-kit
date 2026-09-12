<?php

namespace LaravelCore\Tests;

use Illuminate\Support\Facades\Process;

class StarterKitInstallCommandTest extends TestCase
{
    public function test_it_wires_the_chosen_kit_and_features_and_writes_the_descriptor(): void
    {
        Process::fake();

        @unlink(config_path('starter-kit.php'));
        $composerJsonPath = base_path('composer.json');
        file_put_contents($composerJsonPath, json_encode(['name' => 'acme/app'], JSON_PRETTY_PRINT));

        $this->artisan('starter-kit:install')
            ->expectsChoice('Chọn frontend', 'blade', ['blade' => 'Blade (duxbo/laravel-blade-kit)'])
            ->expectsChoice(
                'Cài thêm package nào? (bỏ trống nếu không cần)',
                ['seo'],
                [
                    'auth' => 'Xác thực & phân quyền (duxbo/laravel-auth)',
                    'seo' => 'SEO (duxbo/laravel-seo)',
                    'media' => 'Media (duxbo/laravel-media)',
                ],
            )
            ->assertSuccessful();

        Process::assertRan(fn ($process) => str_contains($process->command[2] ?? '', 'duxbo/laravel-blade-kit'));
        Process::assertRan(fn ($process) => str_contains($process->command[2] ?? '', 'duxbo/laravel-seo'));
        Process::assertRan(fn ($process) => ($process->command[2] ?? null) === 'blade-kit:install');
        Process::assertNotRan(fn ($process) => str_contains($process->command[2] ?? '', 'duxbo/laravel-media'));

        $composer = json_decode(file_get_contents($composerJsonPath), true);
        $urls = array_column($composer['repositories'], 'url');
        $this->assertContains('https://github.com/Dungnecauoi/laravel-core-kit.git', $urls);
        $this->assertContains('https://github.com/Dungnecauoi/laravel-ai-core.git', $urls);
        $this->assertContains('https://github.com/Dungnecauoi/laravel-blade-kit.git', $urls);
        $this->assertContains('https://github.com/Dungnecauoi/laravel-seo.git', $urls);
        $this->assertNotContains('https://github.com/Dungnecauoi/laravel-auth.git', $urls);
        $this->assertNotContains('https://github.com/Dungnecauoi/laravel-media.git', $urls);

        $descriptor = require config_path('starter-kit.php');
        $this->assertSame('blade', $descriptor['frontend']);
        $this->assertNull($descriptor['ui_library']);
        $this->assertNull($descriptor['mode']);
        $this->assertSame(['seo'], $descriptor['features']);
    }

    public function test_it_refuses_to_run_again_without_force(): void
    {
        file_put_contents(config_path('starter-kit.php'), "<?php\n\nreturn ['frontend' => 'blade'];\n");

        $this->artisan('starter-kit:install')->assertFailed();
    }
}
