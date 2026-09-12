<?php

namespace LaravelCore\Tests;

use Illuminate\Filesystem\Filesystem;
use LaravelCore\Menu\MenuRegistry;
use LaravelCore\Settings\SettingsRegistry;

class TranslationTest extends TestCase
{
    protected function tearDown(): void
    {
        @unlink(base_path('lang/en.json'));
        app()->setLocale('en');

        parent::tearDown();
    }

    public function test_menu_item_labels_are_translated_through_the_apps_json_language_file(): void
    {
        $this->seedTranslation(['Xin chào' => 'Hello']);

        $registry = new MenuRegistry;
        $registry->register(['label' => 'Xin chào']);

        $this->assertSame('Hello', $registry->items()[0]['label']);
    }

    public function test_menu_children_labels_are_also_translated(): void
    {
        $this->seedTranslation(['Người dùng' => 'Users']);

        $registry = new MenuRegistry;
        $registry->register(['label' => 'Người dùng'], parentKey: 'management');

        $this->assertSame('Users', $registry->childrenFor('management')[0]['label']);
    }

    public function test_a_label_with_no_matching_translation_is_returned_unchanged(): void
    {
        $registry = new MenuRegistry;
        $registry->register(['label' => 'Không có bản dịch']);

        $this->assertSame('Không có bản dịch', $registry->items()[0]['label']);
    }

    public function test_settings_panel_labels_are_translated(): void
    {
        $this->seedTranslation(['Hồ sơ' => 'Profile']);

        $registry = new SettingsRegistry;
        $registry->register('profile', ['label' => 'Hồ sơ', 'view' => 'x']);

        $this->assertSame('Profile', $registry->all()[0]['label']);
    }

    /** @param  array<string, string>  $lines */
    private function seedTranslation(array $lines): void
    {
        $files = new Filesystem;
        $files->ensureDirectoryExists(base_path('lang'));
        $files->put(base_path('lang/en.json'), json_encode($lines));

        app()->setLocale('en');
    }
}
