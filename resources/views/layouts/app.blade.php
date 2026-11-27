<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Laravel学習')</title>
    {{-- CSS・JSは public/vendor/ に同梱し、外部から読み込まない（docs/02_architecture.md 2章・6章） --}}
    <link rel="stylesheet" href="{{ asset('vendor/pico/pico.classless.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/highlight/github.min.css') }}" media="(prefers-color-scheme: light)">
    <link rel="stylesheet" href="{{ asset('vendor/highlight/github-dark.min.css') }}" media="(prefers-color-scheme: dark)">
</head>
<body>
    <x-sidebar />
    <main>
        @if (session('status'))
            <p role="status"><mark>{{ session('status') }}</mark></p>
        @endif

        @if ($errors->any())
            <ul role="alert">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        @yield('content')
    </main>
    {{-- JavaScript はコードのハイライトにだけ使う。読み込めなくてもコードはそのまま読める --}}
    <script src="{{ asset('vendor/highlight/highlight.min.js') }}"></script>
    <script>
        if (window.hljs) {
            hljs.registerAliases(['blade'], { languageName: 'php-template' });
            hljs.highlightAll();
        }
    </script>
</body>
</html>
