@props(['code', 'language' => 'php'])

{{-- AIコードレビュー問題のコード。エスケープして表示し、highlight.js が色付けする --}}
<pre><code class="language-{{ $language }}">{{ $code }}</code></pre>
