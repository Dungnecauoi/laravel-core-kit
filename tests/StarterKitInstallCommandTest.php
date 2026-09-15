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
            ->expectsChoice('Chọn frontend', 'blade', [
                'blade' => 'Blade (duxbo/laravel-blade-kit)',
                'react' => 'React (duxbo/laravel-react-kit)',
            ])
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
        Process::assertRan(fn ($process) => str_contains($process->command[2] ?? '', 'spatie/laravel-backup'));
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
        $this->assertArrayNotHasKey('mode', $descriptor);
        $this->assertSame(['seo', 'backup'], $descriptor['features']);
    }

    public function test_it_refuses_to_run_again_without_force(): void
    {
        file_put_contents(config_path('starter-kit.php'), "<?php\n\nreturn ['frontend' => 'blade'];\n");

        $this->artisan('starter-kit:install')->assertFailed();
    }

    public function test_choosing_react_also_asks_ui_library_and_passes_it_to_the_kit(): void
    {
        Process::fake();

        @unlink(config_path('starter-kit.php'));
        $composerJsonPath = base_path('composer.json');
        file_put_contents($composerJsonPath, json_encode(['name' => 'acme/app'], JSON_PRETTY_PRINT));

        $this->artisan('starter-kit:install')
            ->expectsChoice('Chọn frontend', 'react', [
                'blade' => 'Blade (duxbo/laravel-blade-kit)',
                'react' => 'React (duxbo/laravel-react-kit)',
            ])
            ->expectsChoice('Chọn UI library', 'antd', [
                'antd' => 'Ant Design (antd)',
                'shadcn' => 'shadcn',
            ])
            ->expectsChoice('Cài thêm package nào? (bỏ trống nếu không cần)', [], [
                'auth' => 'Xác thực & phân quyền (duxbo/laravel-auth)',
                'seo' => 'SEO (duxbo/laravel-seo)',
                'media' => 'Media (duxbo/laravel-media)',
            ])
            ->assertSuccessful();

        Process::assertRan(function ($process) {
            return str_contains($process->command[2] ?? '', 'duxbo/laravel-react-kit');
        });
        Process::assertRan(function ($process) {
            return ($process->command[2] ?? null) === 'react-kit:install'
                && in_array('--ui=antd', $process->command, true)
                && ! in_array(true, array_map(fn ($arg) => str_starts_with((string) $arg, '--mode='), $process->command), true);
        });

        $composer = json_decode(file_get_contents($composerJsonPath), true);
        $this->assertContains('https://github.com/Dungnecauoi/laravel-react-kit.git', array_column($composer['repositories'], 'url'));

        $descriptor = require config_path('starter-kit.php');
        $this->assertSame('react', $descriptor['frontend']);
        $this->assertSame('antd', $descriptor['ui_library']);
        $this->assertArrayNotHasKey('mode', $descriptor);
    }

    public function test_backup_is_always_installed_without_being_offered_as_a_choice(): void
    {
        Process::fake();

        @unlink(config_path('starter-kit.php'));
        $composerJsonPath = base_path('composer.json');
        file_put_contents($composerJsonPath, json_encode(['name' => 'acme/app'], JSON_PRETTY_PRINT));

        $this->artisan('starter-kit:install')
            ->expectsChoice('Chọn frontend', 'blade', [
                'blade' => 'Blade (duxbo/laravel-blade-kit)',
                'react' => 'React (duxbo/laravel-react-kit)',
            ])
            ->expectsChoice(
                'Cài thêm package nào? (bỏ trống nếu không cần)',
                [],
                [
                    'auth' => 'Xác thực & phân quyền (duxbo/laravel-auth)',
                    'seo' => 'SEO (duxbo/laravel-seo)',
                    'media' => 'Media (duxbo/laravel-media)',
                ],
            )
            ->assertSuccessful();

        Process::assertRan(fn ($process) => str_contains($process->command[2] ?? '', 'spatie/laravel-backup'));

        // spatie/laravel-backup is on Packagist -- no vcs repository should
        // ever be added for it, unlike every duxbo/* feature. Only the two
        // CORE_REPOSITORIES plus blade-kit's own get added for this run.
        $composer = json_decode(file_get_contents($composerJsonPath), true);
        $urls = array_column($composer['repositories'] ?? [], 'url');
        $this->assertNotContains(null, $urls);
        $this->assertCount(3, $urls);

        $descriptor = require config_path('starter-kit.php');
        $this->assertSame(['backup'], $descriptor['features']);
    }
}
