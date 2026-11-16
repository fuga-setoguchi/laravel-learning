<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Laravel学習')</title>
</head>
<body>
    <header>
        <nav>
            <a href="{{ route('home') }}">Laravel学習</a>
        </nav>
    </header>
    <main>
        @yield('content')
    </main>
</body>
</html>
