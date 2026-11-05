<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class SecurityServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->registerRateLimiters();
    }

    private function registerRateLimiters(): void
    {
        $this->registerLimiter('login', fn (Request $request): string => $request->ip().'|'.$request->input('email'));
        $this->registerLimiter('password-reset', fn (Request $request): string => $request->ip().'|'.$request->input('email'));
        $this->registerLimiter('checkout', fn (Request $request): string => (string) $request->ip());
        $this->registerLimiter('webhooks', fn (Request $request): string => (string) $request->ip());
    }

    /**
     * @param  callable(Request): string  $signatureFor
     */
    private function registerLimiter(string $name, callable $signatureFor): void
    {
        $key = str_replace('-', '_', $name);
        $config = config("security.rate_limits.{$key}");

        RateLimiter::for(
            $name,
            fn (Request $request) => Limit::perMinutes($config['decay_minutes'], $config['attempts'])
                ->by($signatureFor($request)),
        );
    }
}
