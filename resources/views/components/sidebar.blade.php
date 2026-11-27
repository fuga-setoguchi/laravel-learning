{{-- LAYOUT 左のメニュー（docs/screens/LAYOUT_common.md）。項目は App\View\Components\Sidebar::items() に持つ --}}
<header class="sidebar">
    <a href="{{ route('home') }}" class="sidebar-brand"><strong>Laravel学習</strong></a>
    <nav aria-label="メインメニュー">
        <ul>
            @foreach ($items() as $item)
                <li>
                    <a href="{{ $item->url() }}" @if ($item->isActive()) aria-current="page" @endif>
                        <x-icon :name="$item->icon" />
                        <span>{{ $item->label }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
</header>
