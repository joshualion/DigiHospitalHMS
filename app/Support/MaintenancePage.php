<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

class MaintenancePage
{
    public function data(): array
    {
        $config = config('maintenance_page', []);
        $timezone = Arr::get($config, 'timezone', config('app.timezone', 'UTC'));
        $brand = Arr::get($config, 'brand', []);
        $contact = Arr::get($config, 'contact', []);
        $info = Arr::get($config, 'info', []);
        $hero = Arr::get($config, 'hero', []);
        $launchAt = $this->launchAt(Arr::get($config, 'launch_at'), $timezone);

        return [
            'brand' => [
                'name' => Arr::get($brand, 'name', 'Testimony'),
                'tagline' => Arr::get($brand, 'tagline', 'Healthcare & Surgeries'),
                'accent' => Arr::get($brand, 'accent', '#0EA5E9'),
                'accent_deep' => Arr::get($brand, 'accent_deep', '#0369A1'),
                'accent_soft' => Arr::get($brand, 'accent_soft', '#E0F2FE'),
            ],
            'hero' => [
                'badge' => Arr::get($hero, 'badge', 'Website Update in Progress'),
                'heading' => Arr::get($hero, 'heading', "We're preparing something better for you."),
                'message' => Arr::get($hero, 'message', 'Our digital front desk is being carefully refreshed to make every visit easier, faster, and more reassuring.'),
                'secondary_message' => Arr::get($hero, 'secondary_message', 'Hospital care continues as usual while this website experience is being updated.'),
                'background_image' => Arr::get($hero, 'background_image', 'frontend/images/slider/1.png'),
            ],
            'launch' => [
                'iso' => $launchAt?->setTimezone($timezone)->toIso8601String(),
                'label' => $launchAt?->setTimezone($timezone)->format('F j, Y g:i A T'),
                'timezone' => $timezone,
            ],
            'actions' => array_values(array_filter([
                $this->action('Call Hospital', 'phone', Arr::get($contact, 'phone'), $this->telHref(Arr::get($contact, 'phone'))),
                $this->action('WhatsApp Us', 'whatsapp', Arr::get($contact, 'whatsapp'), $this->whatsAppHref(Arr::get($contact, 'whatsapp'))),
                $this->action('Send Email', 'email', Arr::get($contact, 'email'), $this->emailHref(Arr::get($contact, 'email'))),
                $this->action('Get Directions', 'directions', 'Hospital directions', $this->directionsHref(Arr::get($contact, 'directions_url'))),
            ])),
            'info' => [
                [
                    'title' => 'Emergency Contact',
                    'text' => Arr::get($info, 'emergency') ?: (Arr::get($contact, 'phone') ?: 'Please contact the hospital directly for urgent support.'),
                    'icon' => 'phone',
                ],
                [
                    'title' => 'Opening Hours',
                    'text' => Arr::get($info, 'hours', 'Care teams remain available during regular hospital hours.'),
                    'icon' => 'clock',
                ],
                [
                    'title' => 'Email',
                    'text' => Arr::get($contact, 'email') ?: 'Email assistance remains available through our hospital care desk.',
                    'icon' => 'email',
                ],
                [
                    'title' => 'Location',
                    'text' => Arr::get($info, 'location', 'Our main hospital campus continues to welcome patients and visitors.'),
                    'icon' => 'map',
                ],
            ],
        ];
    }

    private function launchAt(?string $value, string $timezone): ?CarbonImmutable
    {
        if (! $value) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, $timezone);
        } catch (\Throwable) {
            return null;
        }
    }

    private function action(string $label, string $icon, ?string $value, ?string $href): ?array
    {
        if (! $href) {
            return null;
        }

        return [
            'label' => $label,
            'icon' => $icon,
            'value' => $value,
            'href' => $href,
        ];
    }

    private function telHref(?string $phone): ?string
    {
        $normalized = preg_replace('/[^0-9+]/', '', (string) $phone);

        return $normalized ? 'tel:'.$normalized : null;
    }

    private function whatsAppHref(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        $normalized = preg_replace('/\D+/', '', $value);

        return $normalized ? 'https://wa.me/'.$normalized : null;
    }

    private function emailHref(?string $email): ?string
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? 'mailto:'.$email : null;
    }

    private function directionsHref(?string $url): ?string
    {
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }
}
