<?php

namespace LaravelCore\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

/**
 * The one command a new project runs: pick a frontend kit and optional
 * feature packages. This command only orchestrates —
 *
 *   - it never decides *how* a kit installs itself (that's each kit's own
 *     `{kit}:install` command's job, called here as a fresh process so it
 *     sees packages composer just required);
 *   - it never wires AI (duxbo/laravel-ai-core is already a dependency of
 *     this same package, so it's always present — nothing to ask here);
 *   - it never asks about UI library or Inertia/API when the chosen kit
 *     doesn't have that choice (Blade doesn't; a future Vue/React kit
 *     would, and self::KITS is where that gets added).
 *
 * The chosen stack is written to config/starter-kit.php so a feature
 * package installed later (`composer require duxbo/laravel-media`) can
 * read it back instead of asking the same questions again.
 */
class StarterKitInstallCommand extends Command
{
    protected $signature = 'starter-kit:install {--force : Re-run even if config/starter-kit.php already exists}';

    protected $description = 'Interactively pick a frontend kit and optional feature packages, then install them';

    /**
     * None of these packages are on Packagist yet, so composer can't find
     * them from a package name alone — each needs its `vcs` repository
     * declared directly in the *root* application's composer.json.
     * Repositories declared inside a dependency's own composer.json (e.g.
     * laravel-blade-kit declaring laravel-core's, or laravel-core
     * declaring laravel-ai-core's) are **not** inherited by the app that
     * requires it — composer only ever reads the root project's
     * `repositories`. Every kit requires duxbo/laravel-core, which in turn
     * requires duxbo/laravel-ai-core, so both are added unconditionally
     * before requiring any kit — confirmed by requiring blade-kit into a
     * clean scratch app: it fails to resolve without this.
     *
     * @var list<string>
     */
    private const CORE_REPOSITORIES = [
        'https://github.com/Dungnecauoi/laravel-core-kit.git',
        'https://github.com/Dungnecauoi/laravel-ai-core.git',
    ];

    /**
     * @var array<string, array{label: string, package: string, repository: string, constraint: string, install_command: string}>
     */
    private const KITS = [
        'blade' => [
            'label' => 'Blade (duxbo/laravel-blade-kit)',
            'package' => 'duxbo/laravel-blade-kit',
            'repository' => 'https://github.com/Dungnecauoi/laravel-blade-kit.git',
            'constraint' => 'dev-main@dev',
            'install_command' => 'blade-kit:install',
        ],
    ];

    /**
     * @var array<string, array{label: string, package: string, repository: string, constraint: string}>
     */
    private const FEATURES = [
        'auth' => [
            'label' => 'Xác thực & phân quyền (duxbo/laravel-auth)',
            'package' => 'duxbo/laravel-auth',
            'repository' => 'https://github.com/Dungnecauoi/laravel-auth.git',
            'constraint' => 'dev-main@dev',
        ],
        'seo' => [
            'label' => 'SEO (duxbo/laravel-seo)',
            'package' => 'duxbo/laravel-seo',
            'repository' => 'https://github.com/Dungnecauoi/laravel-seo.git',
            'constraint' => '^0.11',
        ],
        'media' => [
            'label' => 'Media (duxbo/laravel-media)',
            'package' => 'duxbo/laravel-media',
            'repository' => 'https://github.com/Dungnecauoi/laravel-media.git',
            'constraint' => 'dev-main@dev',
        ],
    ];

    public function handle(Filesystem $files): int
    {
        $descriptorPath = config_path('starter-kit.php');

        if ($files->exists($descriptorPath) && ! $this->option('force')) {
            $this->components->error('Đã cài trước đó — config/starter-kit.php đã tồn tại. Dùng --force để chạy lại.');

            return self::FAILURE;
        }

        $frontendKey = select(
            label: 'Chọn frontend',
            options: array_map(fn (array $kit) => $kit['label'], self::KITS),
            default: 'blade',
        );

        $kit = self::KITS[$frontendKey];

        // A future Vue/React entry in self::KITS would ask these two next;
        // Blade has no UI-library or Inertia/API choice, so both stay null.
        $uiLibrary = null;
        $mode = null;

        $featureKeys = multiselect(
            label: 'Cài thêm package nào? (bỏ trống nếu không cần)',
            options: array_map(fn (array $feature) => $feature['label'], self::FEATURES),
        );

        foreach (self::CORE_REPOSITORIES as $repository) {
            $this->addRepository($files, $repository);
        }

        $this->addRepository($files, $kit['repository']);
        $this->requirePackage($kit['package'], $kit['constraint']);
        $this->runArtisan($kit['install_command']);

        foreach ($featureKeys as $key) {
            $feature = self::FEATURES[$key];
            $this->addRepository($files, $feature['repository']);
            $this->requirePackage($feature['package'], $feature['constraint']);
        }

        $this->writeDescriptor($files, $descriptorPath, $frontendKey, $uiLibrary, $mode, $featureKeys);

        $this->components->info('Xong. Xem config/starter-kit.php để biết stack đã chọn — mỗi kit tự in ra các bước thủ công còn lại (npm install, v.v.) ở trên.');

        return self::SUCCESS;
    }

    private function addRepository(Filesystem $files, string $url): void
    {
        $composerJsonPath = base_path('composer.json');

        /** @var array<string, mixed> $composer */
        $composer = json_decode($files->get($composerJsonPath), true);

        $composer['repositories'] ??= [];

        foreach ($composer['repositories'] as $repository) {
            if (($repository['url'] ?? null) === $url) {
                return;
            }
        }

        $composer['repositories'][] = ['type' => 'vcs', 'url' => $url];

        $files->put(
            $composerJsonPath,
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );
    }

    private function requirePackage(string $package, string $constraint): void
    {
        $this->components->task("composer require {$package}", fn () => Process::path(base_path())
            ->timeout(300)
            ->run(['composer', 'require', "{$package}:{$constraint}", '--no-interaction'])
            ->successful());
    }

    private function runArtisan(string $command): void
    {
        $this->components->task("php artisan {$command}", fn () => Process::path(base_path())
            ->timeout(120)
            ->run(['php', 'artisan', $command, '--no-interaction'])
            ->successful());
    }

    /** @param  list<string>  $features */
    private function writeDescriptor(Filesystem $files, string $path, string $frontend, ?string $uiLibrary, ?string $mode, array $features): void
    {
        $export = var_export([
            'frontend' => $frontend,
            'ui_library' => $uiLibrary,
            'mode' => $mode,
            'features' => array_values($features),
        ], true);

        $files->put($path, "<?php\n\nreturn {$export};\n");
    }
}
