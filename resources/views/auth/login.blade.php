<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign in | boundARy</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="login-page">
    <section class="auth-art">
        <a class="brand" href="{{ route('login') }}"><span class="brand-word">bound<span>AR</span>y</span></a>
        <div class="auth-copy">
            <p class="eyebrow">Property survey workspace</p>
            <h1>Know the lines behind the land.</h1>
            <p>Keep parcel records, survey courses, and preliminary boundaries together from desk to field.</p>
        </div>
        <p class="auth-foot">Every calculated boundary needs review by a licensed geodetic engineer.</p>
    </section>
    <section class="auth-panel">
        <form class="auth-form" method="POST" action="{{ route('login.authenticate') }}">
            @csrf
            <p class="eyebrow">Welcome back</p>
            <h2>Sign in</h2>
            <p>Your property records are ready when you are.</p>
            @if ($errors->any())
                <ul class="error-list" role="alert">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            @endif
            <div class="field"><label for="email">Email address</label><input id="email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" required autofocus></div>
            <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
            <label class="remember-row"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
            <button class="primary-button" type="submit">Sign in</button>
            <p class="auth-switch">New to boundARy? <a href="{{ route('register') }}">Create an account</a></p>
        </form>
    </section>
</main>
</body>
</html>