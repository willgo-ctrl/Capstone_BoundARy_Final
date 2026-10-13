<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Create account | boundARy</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="login-page">
    <section class="auth-art">
        <a class="brand" href="{{ route('register') }}"><span class="brand-word">bound<span>AR</span>y</span></a>
        <div class="auth-copy">
            <p class="eyebrow">From survey plan to field view</p>
            <h1>Keep every parcel in perspective.</h1>
            <p>Start a private workspace for your property records, reference plans, and measured boundary courses.</p>
        </div>
        <p class="auth-foot">Preliminary coordinates are for visualization only, not legal boundary determination.</p>
    </section>
    <section class="auth-panel">
        <form class="auth-form" method="POST" action="{{ route('register.store') }}">
            @csrf
            <p class="eyebrow">Your field desk</p>
            <h2>Create account</h2>
            <p>Set up your private property workspace.</p>
            @if ($errors->any())
                <ul class="error-list" role="alert">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            @endif
            <div class="field"><label for="name">Full name</label><input id="name" name="name" autocomplete="name" value="{{ old('name') }}" maxlength="120" required autofocus></div>
            <div class="field"><label for="email">Email address</label><input id="email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" required></div>
            <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="10" required><span class="field-hint">Use at least 10 characters.</span></div>
            <div class="field"><label for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></div>
            <button class="primary-button" type="submit">Create workspace</button>
            <p class="auth-switch">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
        </form>
    </section>
</main>
</body>
</html>