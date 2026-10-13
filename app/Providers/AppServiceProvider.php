<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules\Password;
use Livewire\Features\SupportFileUploads\FilePreviewController;
use Livewire\Features\SupportFileUploads\FileUploadController;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;
use Livewire\Mechanisms\HandleRequests\RequireLivewireHeaders;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        $this->configureAppUrl();
        $this->configureLivewireRoutes();
        $this->configureLivewireFileUploads();
        $this->configureRateLimits();

        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureAppUrl(): void
    {
        $appUrl = config('app.url');

        if (! is_string($appUrl) || $appUrl === '') {
            return;
        }

        URL::forceRootUrl(rtrim($appUrl, '/'));
    }

    /**
     * The browser exam room sends about one request per question change; this
     * only stops a runaway loop from one browser.
     */
    protected function configureRateLimits(): void
    {
        RateLimiter::for('mode-ujian-answers', fn (Request $request) => Limit::perMinute(120)
            ->by((string) ($request->user()?->id ?? $request->ip())));
    }

    protected function configureLivewireFileUploads(): void
    {
        $disk = env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK', 'local');

        config(['livewire.temporary_file_upload.disk' => $disk]);

        $directory = config('livewire.temporary_file_upload.directory') ?: 'livewire-tmp';

        if (! Storage::disk($disk)->exists($directory)) {
            Storage::disk($disk)->makeDirectory($directory);
        }
    }

    protected function configureLivewireRoutes(): void
    {
        $basePath = config('app.base_path');

        if (! is_string($basePath) || $basePath === '') {
            return;
        }

        Livewire::setUpdateRoute(function ($handle) use ($basePath) {
            return Route::post($basePath.EndpointResolver::updatePath(), $handle)
                ->middleware(['web', RequireLivewireHeaders::class])
                ->name('base-path.livewire.update');
        });

        Livewire::setScriptRoute(function ($handle) use ($basePath) {
            return Route::get($basePath.EndpointResolver::scriptPath(minified: ! config('app.debug')), $handle)
                ->name('base-path.livewire.script');
        });

        Route::post($basePath.EndpointResolver::uploadPath(), [FileUploadController::class, 'handle'])
            ->name('base-path.livewire.upload-file');

        Route::get($basePath.EndpointResolver::previewPath(), [FilePreviewController::class, 'handle'])
            ->name('base-path.livewire.preview-file');
    }
}
