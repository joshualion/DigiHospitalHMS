<?php

namespace App\Services;

use App\Models\OutboundMailSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

class OutboundMailConfiguration
{
    public function apply(): void
    {
        if (! Schema::hasTable('outbound_mail_settings')) {
            return;
        }

        $setting = OutboundMailSetting::query()->first();
        if (! $setting || ! $setting->enabled) {
            return; // Fall back to existing server .env credentials.
        }

        Config::set('mail.mailers.hms_smtp', [
            'transport' => 'smtp',
            'host' => $setting->host,
            'port' => $setting->port,
            'scheme' => $setting->encryption === 'ssl' ? 'smtps' : 'smtp',
            'username' => $setting->username,
            'password' => $setting->password,
            'timeout' => 15,
            'auto_tls' => $setting->encryption === 'tls',
            'require_tls' => $setting->encryption === 'tls',
        ]);
        Config::set('mail.default', 'hms_smtp');
        Config::set('mail.from.address', $setting->from_address);
        Config::set('mail.from.name', $setting->from_name);
    }
}
