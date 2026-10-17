<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OutboundMailSetting;
use App\Services\OutboundMailConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OutboundMailSettingController extends Controller
{
    private function authorizeSuperadmin(Request $request): void
    {
        abort_unless($request->user()?->hasRole('superadmin'), 403);
    }

    public function edit(Request $request): Response
    {
        $this->authorizeSuperadmin($request);
        $setting = OutboundMailSetting::query()->first();

        return Inertia::render('Admin/Settings/OutboundMail', [
            'suggestedDomain' => $this->suggestedDomain($request),
            'settings' => $setting ? [
                'enabled' => $setting->enabled,
                'provider' => $setting->provider,
                'host' => $setting->host,
                'port' => $setting->port,
                'encryption' => $setting->encryption,
                'username' => $setting->username,
                'from_address' => $setting->from_address,
                'from_name' => $setting->from_name,
                'has_password' => filled($setting->password),
            ] : null,
        ]);
    }

    private function suggestedDomain(Request $request): ?string
    {
        // Use the actual installation's host, never an example customer's domain.
        // Ignore localhost and IP hosts, for which a branded mailbox cannot be inferred.
        $host = strtolower(rtrim($request->getHost(), '.'));
        if ($host === 'localhost' || ! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
            || filter_var($host, FILTER_VALIDATE_IP) || ! str_contains($host, '.')) {
            return null;
        }

        return $host;
    }

    public function update(Request $request, OutboundMailConfiguration $configuration): RedirectResponse
    {
        $this->authorizeSuperadmin($request);
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'provider' => ['required', Rule::in(['custom'])],
            'host' => ['required', 'string', 'max:253'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'encryption' => ['required', Rule::in(['tls', 'ssl'])],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:1024'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
        ]);

        $setting = OutboundMailSetting::query()->first();
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
            if (! $setting || blank($setting->password)) {
                return back()->withErrors(['password' => 'Enter the SMTP mailbox password.']);
            }
        }

        OutboundMailSetting::query()->updateOrCreate(['id' => $setting?->id ?? 1], $data);
        $configuration->apply();

        return back()->with('success', 'Email settings saved. Send a test email before inviting staff.');
    }

    public function test(Request $request, OutboundMailConfiguration $configuration): RedirectResponse
    {
        $this->authorizeSuperadmin($request);
        $data = $request->validate(['recipient' => ['required', 'email', 'max:255']]);
        $key = 'smtp-test:'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->withErrors(['recipient' => 'Too many tests. Try again later.']);
        }
        RateLimiter::hit($key, 600);

        try {
            $configuration->apply();
            Mail::raw('This is a test email from your hospital management system.', function ($message) use ($data) {
                $message->to($data['recipient'])->subject('Hospital email configuration test');
            });
        } catch (\Throwable $exception) {
            report($exception);
            return back()->withErrors(['recipient' => 'Unable to send the test email. Confirm SMTP credentials, port and server connectivity.']);
        }

        return back()->with('success', 'Test email accepted by the mail transport. Check the recipient inbox.');
    }
}
