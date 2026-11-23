<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Concerns\NormalizesPhoneNumbers;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    use NormalizesPhoneNumbers;

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureAuthentication();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Log Parents in by their phone number, typed in any local form.
     */
    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?User {
            $parent = User::query()
                ->where('phone', $this->normalizePhoneNumber($request->input('phone')))
                ->first();

            if ($parent && Hash::check((string) $request->input('password'), $parent->password)) {
                return $parent;
            }

            return null;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn () => Inertia::render('auth/login'));

        Fortify::registerView(fn () => Inertia::render('auth/register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $phone = $this->normalizePhoneNumber($request->input('phone'));
            $throttleKey = (is_string($phone) ? $phone : '').'|'.$request->ip();

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
