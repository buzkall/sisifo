<?php

namespace Arzcode\Sisifo\Tests;

use Arzcode\Sisifo\SisifoPlugin;
use Arzcode\Sisifo\SisifoServiceProvider;
use BeyondCode\Mailbox\MailboxServiceProvider;
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Freeze time to a known weekday so schedule-aware tasks behave
        // deterministically regardless of the day the suite is run.
        Carbon::setTestNow('2026-06-29 12:00:00'); // Monday

        Schema::create('users', function(Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('notifications', function(Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function(Blueprint $table) {
            $table->id();
            $table->string('group');
            $table->string('name');
            $table->boolean('locked')->default(false);
            $table->json('payload');
            $table->timestamps();
            $table->unique(['group', 'name']);
        });

        $this->createBeyondCodeInboundEmailsTable();
        $this->artisan('migrate', ['--database' => 'testing'])->run();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelSettingsServiceProvider::class,
            MailboxServiceProvider::class,
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            SupportServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentServiceProvider::class,
            LivewireServiceProvider::class,
            SisifoServiceProvider::class,
            TestPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('settings.migrations_paths', [
            __DIR__ . '/../database/settings',
        ]);

        $app['config']->set('settings.cache', [
            'enabled' => false,
        ]);

        $app['config']->set('services.pushover', [
            'token'    => 'test-token',
            'user_key' => 'test-user',
        ]);

        $app['config']->set('sisifo.notifications.notifiable', null);
    }

    private function createBeyondCodeInboundEmailsTable(): void
    {
        if (Schema::hasTable('mailbox_inbound_emails')) {
            return;
        }

        Schema::create('mailbox_inbound_emails', function(Blueprint $table) {
            $table->id();
            $table->string('message_id')->nullable();
            $table->longText('message');
            $table->timestamps();
        });
    }
}

class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugin(SisifoPlugin::make());
    }
}
