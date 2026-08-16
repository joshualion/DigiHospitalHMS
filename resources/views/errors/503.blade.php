@php($page = app(\App\Support\MaintenancePage::class)->data())
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page['brand']['name'] }} | Temporary Website Update</title>
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="description" content="The hospital website is being refreshed and will be available again soon.">
    <style>
        :root {
            color-scheme: dark light;
            --brand-accent: {{ $page['brand']['accent'] }};
            --brand-accent-deep: {{ $page['brand']['accent_deep'] }};
            --brand-accent-soft: {{ $page['brand']['accent_soft'] }};
            --bg: #07111f;
            --bg-muted: #0d1b2e;
            --surface: rgba(11, 23, 40, 0.78);
            --surface-strong: rgba(15, 32, 53, 0.92);
            --surface-card: rgba(20, 40, 68, 0.94);
            --line: rgba(148, 163, 184, 0.18);
            --text: #f8fafc;
            --text-secondary: rgba(226, 232, 240, 0.82);
            --text-muted: rgba(203, 213, 225, 0.64);
            --shadow: 0 28px 80px rgba(0, 0, 0, 0.34);
        }

        @media (prefers-color-scheme: light) {
            :root {
                --bg: #eef6f8;
                --bg-muted: #dfeef2;
                --surface: rgba(255, 255, 255, 0.86);
                --surface-strong: rgba(255, 255, 255, 0.94);
                --surface-card: rgba(255, 255, 255, 0.98);
                --line: rgba(15, 23, 42, 0.1);
                --text: #07111f;
                --text-secondary: rgba(51, 65, 85, 0.88);
                --text-muted: rgba(71, 85, 105, 0.72);
                --shadow: 0 28px 80px rgba(15, 23, 42, 0.12);
            }
        }

        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            margin: 0;
            font-family: "Inter", "Segoe UI", sans-serif;
            background:
                linear-gradient(180deg, rgba(7, 17, 31, 0.44), rgba(7, 17, 31, 0.72)),
                url('{{ asset($page['hero']['background_image']) }}') center/cover no-repeat,
                var(--bg);
            color: var(--text);
        }
        @media (prefers-color-scheme: light) {
            body {
                background:
                    linear-gradient(180deg, rgba(238, 246, 248, 0.62), rgba(238, 246, 248, 0.86)),
                    url('{{ asset($page['hero']['background_image']) }}') center/cover no-repeat,
                    var(--bg);
            }
        }

        .page-shell {
            min-height: 100%;
            position: relative;
            overflow: hidden;
        }
        .page-shell::before,
        .page-shell::after {
            content: "";
            position: absolute;
            border-radius: 999px;
            pointer-events: none;
            filter: blur(14px);
        }
        .page-shell::before {
            width: 24rem;
            height: 24rem;
            top: -9rem;
            right: -7rem;
            background: color-mix(in srgb, var(--brand-accent) 30%, transparent);
            opacity: 0.7;
        }
        .page-shell::after {
            width: 16rem;
            height: 16rem;
            bottom: 7rem;
            left: -5rem;
            background: color-mix(in srgb, var(--brand-accent-soft) 80%, transparent);
            opacity: 0.22;
        }

        .container {
            width: min(1200px, calc(100% - 2rem));
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }
        .hero {
            min-height: 78vh;
            display: grid;
            align-items: center;
            padding: 2rem 0 8rem;
        }
        .hero-panel {
            background: linear-gradient(180deg, color-mix(in srgb, var(--surface-strong) 96%, transparent), color-mix(in srgb, var(--surface) 92%, transparent));
            border: 1px solid var(--line);
            border-radius: 2rem;
            box-shadow: var(--shadow);
            backdrop-filter: blur(16px);
            padding: clamp(1.5rem, 2vw + 1rem, 3rem);
            position: relative;
            overflow: hidden;
        }
        .hero-panel::before {
            content: "";
            position: absolute;
            inset: 0 0 auto;
            height: 1px;
            background: linear-gradient(90deg, transparent, color-mix(in srgb, var(--brand-accent) 50%, #fff), transparent);
            opacity: 0.7;
        }
        .grid {
            display: grid;
            gap: 2rem;
            align-items: center;
        }
        @media (min-width: 980px) {
            .grid {
                grid-template-columns: minmax(0, 1.1fr) minmax(320px, 0.9fr);
                gap: 2.5rem;
            }
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 0.95rem;
            min-width: 0;
        }
        .brand-badge {
            width: 3.25rem;
            height: 3.25rem;
            border-radius: 1.2rem;
            border: 1px solid color-mix(in srgb, var(--brand-accent) 22%, var(--line));
            background: color-mix(in srgb, var(--brand-accent) 10%, var(--surface-card));
            color: var(--brand-accent);
            display: grid;
            place-items: center;
            flex-shrink: 0;
        }
        .brand-badge svg {
            width: 1.45rem;
            height: 1.45rem;
        }
        .brand-name {
            display: block;
            font-size: clamp(1.15rem, 0.5vw + 1rem, 1.8rem);
            line-height: 1;
            font-weight: 900;
            letter-spacing: 0.015em;
            color: var(--text);
            overflow-wrap: anywhere;
        }
        .brand-tagline-row {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            margin-top: 0.45rem;
            max-width: 28rem;
        }
        .brand-tagline-mark {
            width: 1.4rem;
            height: 2px;
            border-radius: 999px;
            background: var(--brand-accent);
            flex: 0 0 auto;
        }
        .brand-tagline {
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            color: color-mix(in srgb, var(--brand-accent) 74%, var(--text-secondary));
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            margin-top: 2rem;
            padding: 0.55rem 0.9rem;
            border-radius: 999px;
            background: color-mix(in srgb, var(--brand-accent) 10%, var(--surface-card));
            border: 1px solid color-mix(in srgb, var(--brand-accent) 24%, var(--line));
            color: color-mix(in srgb, var(--brand-accent) 70%, var(--text));
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .status-badge::before {
            content: "";
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 999px;
            background: var(--brand-accent);
            box-shadow: 0 0 0 0 rgba(14, 165, 233, 0.45);
            animation: pulse 2.2s infinite;
        }
        .headline {
            margin: 1.2rem 0 0;
            font-size: clamp(2.3rem, 4vw, 4.7rem);
            line-height: 0.98;
            letter-spacing: -0.03em;
            font-weight: 950;
            max-width: 12ch;
        }
        .copy {
            max-width: 39rem;
            margin-top: 1.2rem;
            font-size: clamp(1rem, 0.32vw + 0.98rem, 1.15rem);
            line-height: 1.8;
            color: var(--text-secondary);
        }
        .secondary {
            margin-top: 1rem;
            color: color-mix(in srgb, var(--brand-accent) 34%, var(--text-muted));
            font-size: 0.96rem;
            font-weight: 600;
        }
        .launch-note {
            margin-top: 0.85rem;
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.8rem;
            margin-top: 1.6rem;
        }
        .action-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.65rem;
            min-height: 44px;
            padding: 0.78rem 1.2rem;
            border-radius: 999px;
            border: 1px solid transparent;
            font-weight: 800;
            font-size: 0.95rem;
            text-decoration: none;
            transition: transform 180ms ease, border-color 180ms ease, background-color 180ms ease, color 180ms ease, box-shadow 180ms ease;
        }
        .action-button svg {
            width: 1rem;
            height: 1rem;
            flex-shrink: 0;
        }
        .action-button--primary {
            background: var(--brand-accent);
            color: #fff;
            box-shadow: 0 16px 32px color-mix(in srgb, var(--brand-accent) 24%, transparent);
        }
        .action-button--secondary {
            background: color-mix(in srgb, var(--surface-card) 88%, transparent);
            border-color: var(--line);
            color: var(--text);
        }
        .action-button:hover,
        .action-button:focus-visible {
            transform: translateY(-1px);
        }
        .action-button--secondary:hover,
        .action-button--secondary:focus-visible {
            border-color: color-mix(in srgb, var(--brand-accent) 40%, var(--line));
            color: var(--brand-accent);
        }

        .countdown-card {
            background: color-mix(in srgb, var(--surface-card) 96%, transparent);
            border: 1px solid var(--line);
            border-radius: 1.8rem;
            padding: 1.4rem;
        }
        .countdown-heading {
            margin: 0;
            font-size: 0.82rem;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: color-mix(in srgb, var(--brand-accent) 62%, var(--text));
        }
        .countdown-grid {
            display: grid;
            gap: 0.9rem;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            margin-top: 1.2rem;
        }
        .countdown-unit {
            background: color-mix(in srgb, var(--surface-strong) 82%, transparent);
            border: 1px solid var(--line);
            border-radius: 1.2rem;
            padding: 1rem 0.8rem;
            text-align: center;
        }
        .countdown-value {
            display: block;
            font-size: clamp(1.7rem, 4vw, 2.8rem);
            line-height: 1;
            font-weight: 900;
            color: var(--text);
        }
        .countdown-label {
            display: block;
            margin-top: 0.45rem;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: var(--text-muted);
        }
        .countdown-status {
            margin: 1rem 0 0;
            min-height: 1.4rem;
            font-size: 0.92rem;
            font-weight: 700;
            color: var(--text-secondary);
        }

        .info-band-wrap {
            position: relative;
            z-index: 1;
            margin-top: -4.5rem;
            padding-bottom: 2rem;
        }
        .info-band {
            display: grid;
            gap: 0;
            overflow: hidden;
            border-radius: 2rem;
            border: 1px solid var(--line);
            background: color-mix(in srgb, var(--surface-strong) 96%, transparent);
            box-shadow: var(--shadow);
            backdrop-filter: blur(16px);
        }
        .info-item {
            padding: 1.5rem;
            border-bottom: 1px solid var(--line);
        }
        .info-item:last-child { border-bottom: 0; }
        .info-icon {
            width: 2.9rem;
            height: 2.9rem;
            border-radius: 1rem;
            display: grid;
            place-items: center;
            background: color-mix(in srgb, var(--brand-accent) 10%, var(--surface-card));
            color: var(--brand-accent);
            border: 1px solid color-mix(in srgb, var(--brand-accent) 16%, var(--line));
        }
        .info-icon svg { width: 1.2rem; height: 1.2rem; }
        .info-title {
            margin: 1rem 0 0;
            font-size: 1rem;
            font-weight: 900;
            color: var(--text);
        }
        .info-text {
            margin: 0.6rem 0 0;
            font-size: 0.94rem;
            line-height: 1.7;
            color: var(--text-secondary);
        }

        @media (min-width: 700px) {
            .countdown-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
            .info-band { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .info-item:nth-child(odd) { border-right: 1px solid var(--line); }
            .info-item:nth-last-child(-n+2) { border-bottom: 0; }
        }
        @media (min-width: 1100px) {
            .hero {
                min-height: 84vh;
                padding-bottom: 10rem;
            }
            .info-band { grid-template-columns: repeat(4, minmax(0, 1fr)); }
            .info-item {
                border-bottom: 0;
                border-right: 1px solid var(--line);
            }
            .info-item:last-child { border-right: 0; }
        }
        @media (max-width: 699px) {
            .brand-tagline-row { display: none; }
            .hero {
                min-height: auto;
                padding-bottom: 7rem;
            }
            .info-band-wrap { margin-top: -3.25rem; }
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 color-mix(in srgb, var(--brand-accent) 34%, transparent); }
            70% { box-shadow: 0 0 0 12px rgba(0, 0, 0, 0); }
            100% { box-shadow: 0 0 0 0 rgba(0, 0, 0, 0); }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.001ms !important;
                animation-iteration-count: 1 !important;
                scroll-behavior: auto !important;
                transition-duration: 0.001ms !important;
            }
        }
    </style>
</head>
<body>
    <div class="page-shell">
        <main class="container">
            <section class="hero" aria-labelledby="maintenance-heading">
                <div class="hero-panel">
                    <div class="grid">
                        <div>
                            <div class="brand" aria-label="{{ $page['brand']['name'] }}">
                                <span class="brand-badge" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M19.5 13.5c1.5-1.5 2.3-3.3 2.3-5.3A5.7 5.7 0 0 0 16 2.5c-1.8 0-3.5.8-4.6 2.2C10.2 3.3 8.5 2.5 6.7 2.5A5.7 5.7 0 0 0 1 8.2c0 2 .8 3.8 2.3 5.3L11.4 21l8.1-7.5Z"></path>
                                        <path d="M7.4 11.8h2.6l1.3-2.6 2.2 5 1.4-2.4h1.9"></path>
                                    </svg>
                                </span>
                                <span>
                                    <span class="brand-name">{{ $page['brand']['name'] }}</span>
                                    <span class="brand-tagline-row">
                                        <span class="brand-tagline-mark" aria-hidden="true"></span>
                                        <span class="brand-tagline">{{ $page['brand']['tagline'] }}</span>
                                    </span>
                                </span>
                            </div>

                            <div class="status-badge">{{ $page['hero']['badge'] }}</div>
                            <h1 id="maintenance-heading" class="headline">{{ $page['hero']['heading'] }}</h1>
                            <p class="copy">{{ $page['hero']['message'] }}</p>
                            <p class="secondary">{{ $page['hero']['secondary_message'] }}</p>
                            @if ($page['launch']['label'])
                                <p class="launch-note">Expected launch window: {{ $page['launch']['label'] }}</p>
                            @endif

                            @if ($page['actions'])
                                <div class="actions" aria-label="Hospital contact actions">
                                    @foreach ($page['actions'] as $index => $action)
                                        <a
                                            class="action-button {{ $index === 0 ? 'action-button--primary' : 'action-button--secondary' }}"
                                            href="{{ $action['href'] }}"
                                            @if (str_starts_with($action['href'], 'http')) target="_blank" rel="noopener noreferrer" @endif
                                        >
                                            @if ($action['icon'] === 'phone')
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2A19.8 19.8 0 0 1 11.2 19a19.3 19.3 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4 2h3a2 2 0 0 1 2 1.7l.5 3a2 2 0 0 1-.6 1.8L7 10a16 16 0 0 0 7 7l1.5-1.9a2 2 0 0 1 1.8-.6l3 .5A2 2 0 0 1 22 16.9Z"></path></svg>
                                            @elseif ($action['icon'] === 'whatsapp')
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5A8.5 8.5 0 0 1 8.4 19l-4.4 1.4L5.4 16A8.5 8.5 0 1 1 21 11.5Z"></path><path d="M9.7 8.8c.2-.5.3-.5.5-.5h.4c.1 0 .4 0 .6.5.2.5.8 1.8.9 1.9.1.1.1.4 0 .6-.1.2-.2.4-.4.5-.2.2-.4.4-.2.7.1.2.8 1.3 1.8 2.1 1.2.9 2.2 1.2 2.5 1.4.3.1.5.1.7-.1.2-.2.8-.9 1-1.2.2-.3.4-.2.7-.1.3.1 1.7.8 2 .9.3.2.5.3.5.5 0 .2-.1 1-.7 1.6-.6.5-1.3.7-1.8.7-.5 0-1.2-.1-2.1-.5-.9-.4-2-.9-3.5-2.2-1.8-1.5-2.9-3.4-3.2-3.9-.3-.5-.8-1.3-.8-2.5 0-1.1.6-1.7.9-2Z"></path></svg>
                                            @elseif ($action['icon'] === 'email')
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"></path><path d="m22 7-10 7L2 7"></path></svg>
                                            @else
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-4.4 7-11a7 7 0 1 0-14 0c0 6.6 7 11 7 11Z"></path><circle cx="12" cy="10" r="2.5"></circle></svg>
                                            @endif
                                            <span>{{ $action['label'] }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <aside class="countdown-card" aria-label="Public launch countdown">
                            <p class="countdown-heading">Countdown to launch</p>
                            <div class="countdown-grid" id="launch-countdown" data-launch-at="{{ $page['launch']['iso'] ?? '' }}">
                                @foreach (['days' => 'Days', 'hours' => 'Hours', 'minutes' => 'Minutes', 'seconds' => 'Seconds'] as $key => $label)
                                    <div class="countdown-unit">
                                        <span class="countdown-value" data-unit="{{ $key }}">00</span>
                                        <span class="countdown-label">{{ $label }}</span>
                                    </div>
                                @endforeach
                            </div>
                            <p class="countdown-status" id="countdown-status" aria-live="polite">We are preparing the public launch window.</p>
                        </aside>
                    </div>
                </div>
            </section>

            <section class="info-band-wrap" aria-label="Hospital visitor information">
                <div class="info-band">
                    @foreach ($page['info'] as $item)
                        <article class="info-item">
                            <div class="info-icon" aria-hidden="true">
                                @if ($item['icon'] === 'phone')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2A19.8 19.8 0 0 1 11.2 19a19.3 19.3 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4 2h3a2 2 0 0 1 2 1.7l.5 3a2 2 0 0 1-.6 1.8L7 10a16 16 0 0 0 7 7l1.5-1.9a2 2 0 0 1 1.8-.6l3 .5A2 2 0 0 1 22 16.9Z"></path></svg>
                                @elseif ($item['icon'] === 'clock')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg>
                                @elseif ($item['icon'] === 'email')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"></path><path d="m22 7-10 7L2 7"></path></svg>
                                @else
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-4.4 7-11a7 7 0 1 0-14 0c0 6.6 7 11 7 11Z"></path><circle cx="12" cy="10" r="2.5"></circle></svg>
                                @endif
                            </div>
                            <h2 class="info-title">{{ $item['title'] }}</h2>
                            <p class="info-text">{{ $item['text'] }}</p>
                        </article>
                    @endforeach
                </div>
            </section>
        </main>
    </div>

    <script>
        (() => {
            const countdown = document.getElementById('launch-countdown');
            const status = document.getElementById('countdown-status');

            if (!countdown || !status) return;

            const launchAt = countdown.dataset.launchAt ? new Date(countdown.dataset.launchAt) : null;
            const units = {
                days: countdown.querySelector('[data-unit="days"]'),
                hours: countdown.querySelector('[data-unit="hours"]'),
                minutes: countdown.querySelector('[data-unit="minutes"]'),
                seconds: countdown.querySelector('[data-unit="seconds"]'),
            };

            const paint = (days, hours, minutes, seconds) => {
                units.days.textContent = String(days).padStart(2, '0');
                units.hours.textContent = String(hours).padStart(2, '0');
                units.minutes.textContent = String(minutes).padStart(2, '0');
                units.seconds.textContent = String(seconds).padStart(2, '0');
            };

            const markReady = (message) => {
                paint(0, 0, 0, 0);
                status.textContent = message;
            };

            if (!launchAt || Number.isNaN(launchAt.getTime())) {
                markReady('Launching shortly.');
                return;
            }

            const tick = () => {
                const remaining = Math.max(0, launchAt.getTime() - Date.now());

                if (remaining <= 0) {
                    markReady("We're almost ready.");
                    return false;
                }

                const totalSeconds = Math.floor(remaining / 1000);
                const days = Math.floor(totalSeconds / 86400);
                const hours = Math.floor((totalSeconds % 86400) / 3600);
                const minutes = Math.floor((totalSeconds % 3600) / 60);
                const seconds = totalSeconds % 60;

                paint(days, hours, minutes, seconds);
                status.textContent = 'Thank you for your patience while we complete the final touches.';

                return true;
            };

            if (!tick()) return;

            const timer = window.setInterval(() => {
                if (!tick()) {
                    window.clearInterval(timer);
                }
            }, 1000);
        })();
    </script>
</body>
</html>
