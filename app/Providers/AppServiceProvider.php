<?php

namespace App\Providers;

use App\Console\Commands\ServeCommand;
use App\Contracts\SpeechTranscriber;
use App\Services\OpenAiCompatibleTranscriber;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Console\ServeCommand as FrameworkServeCommand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SpeechTranscriber::class, OpenAiCompatibleTranscriber::class);
        $this->app->singleton(FrameworkServeCommand::class, ServeCommand::class);
    }

    public function boot(): void
    {
        RateLimiter::for('stables', function (Request $request) {
            return Limit::perMinute((int) config('stt.rate_limit_per_minute'))->by($request->ip());
        });

        RateLimiter::for('memos', function (Request $request) {
            $header = $request->header('X-Tenant');
            $tenant = is_string($header) && $header !== ''
                ? $header
                : $request->input('tenant');
            $key = is_string($tenant) && $tenant !== ''
                ? strtoupper(trim($tenant))
                : 'unknown';

            return Limit::perMinute((int) config('stt.rate_limit_per_minute'))->by($key);
        });
    }
}
