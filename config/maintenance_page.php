<?php

return [
    'brand' => [
        'name' => env('MAINTENANCE_BRAND_NAME', 'Testimony'),
        'tagline' => env('MAINTENANCE_BRAND_TAGLINE', 'Healthcare & Surgeries'),
        'accent' => env('MAINTENANCE_BRAND_ACCENT', '#0EA5E9'),
        'accent_deep' => env('MAINTENANCE_BRAND_ACCENT_DEEP', '#0369A1'),
        'accent_soft' => env('MAINTENANCE_BRAND_ACCENT_SOFT', '#E0F2FE'),
    ],
    'timezone' => env('MAINTENANCE_TIMEZONE', env('APP_TIMEZONE', 'UTC')),
    'launch_at' => env('MAINTENANCE_LAUNCH_AT'),
    'hero' => [
        'badge' => env('MAINTENANCE_BADGE_TEXT', 'Website Update in Progress'),
        'heading' => env('MAINTENANCE_HEADING', "We're preparing something better for you."),
        'message' => env('MAINTENANCE_MESSAGE', 'Our digital front desk is being carefully refreshed to make every visit easier, faster, and more reassuring.'),
        'secondary_message' => env('MAINTENANCE_SECONDARY_MESSAGE', 'Hospital care continues as usual while this website experience is being updated.'),
        'background_image' => env('MAINTENANCE_BACKGROUND_IMAGE', 'frontend/images/slider/1.png'),
    ],
    'contact' => [
        'phone' => env('MAINTENANCE_PHONE'),
        'whatsapp' => env('MAINTENANCE_WHATSAPP'),
        'email' => env('MAINTENANCE_EMAIL'),
        'directions_url' => env('MAINTENANCE_DIRECTIONS_URL'),
    ],
    'info' => [
        'emergency' => env('MAINTENANCE_EMERGENCY_TEXT'),
        'hours' => env('MAINTENANCE_OPEN_HOURS', 'Care teams remain available during regular hospital hours.'),
        'location' => env('MAINTENANCE_LOCATION_TEXT', 'Our main hospital campus continues to welcome patients and visitors.'),
    ],
];
