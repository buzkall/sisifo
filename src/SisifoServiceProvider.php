<?php

namespace Arzcode\Sisifo;

use Arzcode\Sisifo\Console\Commands\ProcessMailbox;
use Arzcode\Sisifo\Contracts\EmbeddingStore;
use Arzcode\Sisifo\Contracts\LlmProvider;
use Arzcode\Sisifo\Embeddings\Drivers\MariaDbVectorStore;
use Arzcode\Sisifo\Embeddings\Drivers\MysqlBruteForceStore;
use Arzcode\Sisifo\Embeddings\Drivers\PgVectorStore;
use Arzcode\Sisifo\Llm\Drivers\LaravelAiDriver;
use Arzcode\Sisifo\Llm\Drivers\PrismDriver;
use Arzcode\Sisifo\Settings\MailboxSettings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use LogicException;

class SisifoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/sisifo.php', 'sisifo');

        $this->mergeSettingsMigrationsPaths();
        $this->registerMailboxSettings();

        $this->app->bind(LlmProvider::class, function() {
            return match (config('sisifo.llm.driver')) {
                'prism'      => new PrismDriver,
                'laravel-ai' => new LaravelAiDriver,
                default      => throw new LogicException('Unknown sisifo.llm.driver: ' . config('sisifo.llm.driver')),
            };
        });

        $this->app->bind(EmbeddingStore::class, function() {
            return match (DB::connection()->getDriverName()) {
                'pgsql'   => new PgVectorStore,
                'mariadb' => new MariaDbVectorStore,
                default   => new MysqlBruteForceStore,
            };
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'sisifo');

        $viewsPath = __DIR__ . '/../resources/views';

        if (is_dir($viewsPath)) {
            $this->loadViewsFrom($viewsPath, 'sisifo');
        }

        $this->commands([ProcessMailbox::class]);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/sisifo.php' => config_path('sisifo.php'),
            ], 'sisifo-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'sisifo-migrations');

            $this->publishes([
                __DIR__ . '/../database/settings' => database_path('settings'),
            ], 'sisifo-settings-migrations');

            $this->publishes([
                __DIR__ . '/../lang' => $this->app->langPath('vendor/sisifo'),
            ], 'sisifo-lang');

            if (is_dir($viewsPath)) {
                $this->publishes([
                    $viewsPath => resource_path('views/vendor/sisifo'),
                ], 'sisifo-views');
            }
        }

        $this->app->afterResolving(Schedule::class, function(Schedule $schedule) {
            $schedule->command('mailbox:process')
                ->environments(config('sisifo.schedule.environments', ['production']))
                ->everyMinute()
                ->withoutOverlapping();
        });
    }

    private function mergeSettingsMigrationsPaths(): void
    {
        $existing = config('settings.migrations_paths', []);
        $packagePath = __DIR__ . '/../database/settings';

        if (! in_array($packagePath, $existing, true)) {
            config()->set('settings.migrations_paths', array_merge($existing, [$packagePath]));
        }
    }

    private function registerMailboxSettings(): void
    {
        $existing = config('settings.settings', []);

        if (! in_array(MailboxSettings::class, $existing, true)) {
            config()->set('settings.settings', array_merge($existing, [MailboxSettings::class]));
        }
    }
}
