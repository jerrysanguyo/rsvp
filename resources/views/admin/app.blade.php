<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="theme-color" content="#f86eac">
        <meta name="robots" content="noindex, nofollow">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $page === 'login' ? 'Admin Sign In' : 'RSVP Dashboard' }}</title>
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/admin.jsx'])
    </head>
    <body>
        <div
            id="admin-app"
            data-page="{{ $page }}"
            data-payload="{{ json_encode($payload) }}"
        ></div>
    </body>
</html>
