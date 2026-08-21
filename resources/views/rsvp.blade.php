<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="theme-color" content="#f86eac">
        <meta name="description" content="{{ ($rsvpLink['title'] ?? 'Gaia\'s third birthday').' RSVP' }}">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('images/favicon.png') }}">
        <title>{{ ($rsvpLink['title'] ?? 'Gaia\'s 3rd Birthday').' RSVP' }}</title>
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    </head>
    <body>
        <div id="app" data-rsvp-link="{{ json_encode($rsvpLink ?? null) }}"></div>
    </body>
</html>
