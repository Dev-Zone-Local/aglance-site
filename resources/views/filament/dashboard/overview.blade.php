{{--
    Admin overview. $s = App\Support\AdminStats::all().
    Own styles (Filament does not compile Tailwind classes used in custom views). Charts are inline SVG.
--}}
@php
    use App\Filament\Resources\ContactMessages\ContactMessageResource;
    use App\Filament\Resources\Licenses\LicenseResource;
    use App\Filament\Resources\Users\UserResource;
    use App\Models\ContactMessage;

    $u = $s['users'];
    $l = $s['licences'];
    $m = $s['messages'];
    $n = fn ($v) => number_format((int) $v);
    $chip = function (?int $pct, bool $upIsGood = true) {
        if ($pct === null) {
            return '<span class="agx-chip">new</span>';
        }
        $good = $upIsGood ? $pct >= 0 : $pct <= 0;

        return '<span class="agx-chip '.($good ? 'up' : 'down').'">'.($pct >= 0 ? '↑' : '↓').' '.abs($pct).'%</span>';
    };

    // 30-day sign-up bars.
    $daily = $u['daily'];
    $dmax = max(1, ...$daily);

    // Enterprise share gauge (half circle, radius 52).
    $paying = $u['free'] + $u['enterprise'];
    $entShare = $paying ? $u['enterprise'] / $paying : 0;
    $arc = M_PI * 52;

    // 6-month trend lines.
    $t = $s['trend'];
    $tmax = max(1, ...$t['users'], ...$t['licences']);
    $pt = fn (array $vals) => collect($vals)->map(fn ($v, $i) => round(20 + $i * (560 / max(1, count($vals) - 1)), 1).','.round(150 - ($v / $tmax) * 120, 1))->implode(' ');
    $last = count($t['users']) - 1;

    $statusClass = ['new' => 'warn', 'in_progress' => 'info', 'resolved' => 'ok', 'spam' => 'mute'];
@endphp

<style>
    .agx { display: grid; gap: 16px; grid-template-columns: repeat(12, minmax(0, 1fr)); color: #14171b; }
    .agx * { box-sizing: border-box; }
    .agx-card { background: #fff; border-radius: 20px; padding: 18px 20px; box-shadow: 0 8px 30px rgba(17, 20, 24, .06); min-width: 0; position: relative; }
    .agx-card.dark { background: radial-gradient(420px 260px at 85% 15%, rgba(76, 201, 255, .28), transparent 70%), radial-gradient(380px 260px at 10% 100%, rgba(113, 247, 212, .22), transparent 70%), #111519; color: #fff; overflow: hidden; }
    .agx-head { display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 500; }
    .agx-head .ico { width: 24px; height: 24px; border-radius: 8px; background: #14171b; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; }
    .agx-card.dark .agx-head .ico { background: rgba(255, 255, 255, .12); }
    .agx-head a { margin-left: auto; font-size: 12px; font-weight: 500; color: #0f7f96; text-decoration: none; padding: 4px 10px; border-radius: 999px; background: #eef7fa; }
    .agx-card.dark .agx-head a { background: rgba(255, 255, 255, .1); color: #71f7d4; }
    .agx-big { font-size: 40px; line-height: 1; font-weight: 500; letter-spacing: -.03em; }
    .agx-sub { font-size: 12px; color: #8a9099; margin-top: 4px; }
    .agx-card.dark .agx-sub { color: rgba(255, 255, 255, .55); }
    .agx-chip { display: inline-flex; align-items: center; gap: 2px; font-size: 11px; font-weight: 600; padding: 2px 7px; border-radius: 7px; background: #e6e9ee; color: #5b626b; vertical-align: middle; }
    .agx-chip.up { background: #dff8ee; color: #138a5c; }
    .agx-chip.down { background: #fde8e8; color: #c0392b; }
    .agx-row { display: flex; align-items: flex-end; gap: 12px; margin-top: 14px; }
    .agx-pill { display: inline-block; padding: 3px 9px; border-radius: 8px; font-size: 11px; font-weight: 600; }
    .agx-pill.warn { background: #fff3dc; color: #a06307; } .agx-pill.info { background: #e3f4fa; color: #0f7f96; }
    .agx-pill.ok { background: #dff8ee; color: #138a5c; } .agx-pill.mute { background: #eef0f3; color: #8a9099; }
    .agx-list { list-style: none; margin: 16px 0 0; padding: 0; display: grid; gap: 10px; }
    .agx-list li { display: flex; align-items: center; gap: 10px; font-size: 13px; }
    .agx-list .bar { flex: 1; height: 6px; border-radius: 99px; background: rgba(255, 255, 255, .1); overflow: hidden; }
    .agx-list .bar i { display: block; height: 100%; border-radius: 99px; background: linear-gradient(90deg, #71f7d4, #4cc9ff); }
    .agx-list .num { width: 44px; text-align: right; font-variant-numeric: tabular-nums; }
    .agx-list .lbl { width: 120px; color: rgba(255, 255, 255, .7); }
    .agx-split { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 12px; }
    .agx-split div { background: #f4f6f8; border-radius: 12px; padding: 10px 12px; }
    .agx-split b { display: block; font-size: 22px; font-weight: 500; letter-spacing: -.02em; }
    .agx-split span { font-size: 12px; color: #5b626b; }
    .agx-heat { display: grid; grid-template-columns: 92px repeat(7, 1fr); gap: 5px; margin-top: 14px; font-size: 11px; color: #5b626b; align-items: center; }
    .agx-heat .cell { aspect-ratio: 1; border-radius: 6px; background: #eef1f4; }
    .agx-heat .day { text-align: center; color: #8a9099; }
    .agx-table { width: 100%; border-collapse: separate; border-spacing: 0 6px; font-size: 13px; margin-top: 8px; }
    .agx-table th { text-align: left; font-size: 11px; font-weight: 500; color: #8a9099; padding: 6px 10px; background: #f4f6f8; }
    .agx-table th:first-child { border-radius: 10px 0 0 10px; } .agx-table th:last-child { border-radius: 0 10px 10px 0; }
    .agx-table td { padding: 8px 10px; vertical-align: middle; }
    .agx-table tr.r td { background: #fff; }
    .agx-table tr.r:hover td { background: #f7f9fb; }
    .agx-table .who { display: flex; align-items: center; gap: 10px; min-width: 0; }
    .agx-table .av { width: 30px; height: 30px; flex: none; border-radius: 9px; display: inline-flex; align-items: center; justify-content: center; font-weight: 600; font-size: 12px; color: #14171b; background: linear-gradient(135deg, #b8fbe9, #a9e6ff); }
    .agx-table .t { font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 220px; }
    .agx-table .s { font-size: 11px; color: #8a9099; }
    .agx-table a { color: inherit; text-decoration: none; }
    .agx-legend { display: flex; gap: 14px; margin-left: auto; font-size: 12px; color: #5b626b; }
    .agx-legend i { display: inline-block; width: 9px; height: 9px; border-radius: 3px; margin-right: 5px; vertical-align: -1px; }
    .agx-empty { font-size: 13px; color: #8a9099; padding: 16px 4px; }
    .agx-strip { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
    .agx-mini { display: flex; align-items: center; gap: 12px; }
    .agx-mini .ico { width: 38px; height: 38px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #71f7d4, #4cc9ff); font-weight: 700; color: #14171b; font-size: 15px; }
    .agx-mini b { display: block; font-size: 22px; font-weight: 500; letter-spacing: -.02em; line-height: 1.1; }
    .agx-mini span { font-size: 12px; color: #8a9099; }
    .s12 { grid-column: span 12; } .s7 { grid-column: span 7; } .s5 { grid-column: span 5; } .s4 { grid-column: span 4; } .s3 { grid-column: span 3; }
    @media (max-width: 1279px) { .s5, .s4, .s3 { grid-column: span 6; } .s7 { grid-column: span 12; } .agx-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 767px) { .s7, .s5, .s4, .s3 { grid-column: span 12; } .agx-strip { grid-template-columns: 1fr; } }
    @media (prefers-reduced-motion: no-preference) { .agx-card { animation: agx-in .35s ease-out both; } }
    @keyframes agx-in { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
</style>

<div class="agx">
    {{-- Key numbers --}}
    <div class="agx-card s12">
        <div class="agx-strip">
            <a class="agx-mini" href="{{ UserResource::getUrl() }}" style="color:inherit;text-decoration:none">
                <span class="ico" aria-hidden="true">U</span>
                <span><b>{{ $n($u['total']) }}</b><span>Users · {{ $n($u['verified']) }} verified</span></span>
            </a>
            <a class="agx-mini" href="{{ LicenseResource::getUrl() }}" style="color:inherit;text-decoration:none">
                <span class="ico" aria-hidden="true">L</span>
                <span><b>{{ $n($l['in_use']) }} <small style="font-size:13px;color:#8a9099">/ {{ $n($l['total']) }}</small></b><span>Licences in use</span></span>
            </a>
            <a class="agx-mini" href="{{ ContactMessageResource::getUrl() }}" style="color:inherit;text-decoration:none">
                <span class="ico" aria-hidden="true">M</span>
                <span><b>{{ $n($m['new']) }}</b><span>New messages · {{ $n($m['total']) }} total</span></span>
            </a>
            <div class="agx-mini">
                <span class="ico" aria-hidden="true">D</span>
                <span><b>{{ $n($s['downloads']) }}</b><span>File downloads</span></span>
            </div>
        </div>
    </div>

    {{-- Licences (dark feature card) --}}
    <section class="agx-card dark s5" aria-labelledby="agx-lic">
        <div class="agx-head"><span class="ico" aria-hidden="true">◆</span><span id="agx-lic">Licences in use</span><a href="{{ LicenseResource::getUrl() }}">Open</a></div>
        <div class="agx-row" style="align-items:center;gap:24px">
            <div>
                <div class="agx-big">{{ $n($l['in_use']) }}</div>
                <div class="agx-sub">of {{ $n($l['total']) }} licences · {{ $n($l['seen_today']) }} consoles seen in 24 h</div>
            </div>
            <svg width="96" height="96" viewBox="0 0 96 96" role="img" aria-label="{{ $l['share'] }}% of licences in use" style="margin-left:auto;flex:none">
                <circle cx="48" cy="48" r="40" fill="none" stroke="rgba(255,255,255,.1)" stroke-width="10" />
                @if ($l['share'] > 0)
                    <circle cx="48" cy="48" r="40" fill="none" stroke="url(#agxg)" stroke-width="10" stroke-linecap="round"
                        stroke-dasharray="{{ round(2 * M_PI * 40 * $l['share'] / 100, 2) }} 999" transform="rotate(-90 48 48)" />
                @endif
                <defs><linearGradient id="agxg" x1="0" x2="1"><stop offset="0" stop-color="#71f7d4" /><stop offset="1" stop-color="#4cc9ff" /></linearGradient></defs>
                <text x="48" y="54" text-anchor="middle" fill="#fff" font-size="18" font-weight="500">{{ $l['share'] }}%</text>
            </svg>
        </div>
        @php($lmax = max(1, $l['total']))
        <ul class="agx-list">
            @foreach ([['Online (key)', $l['online']], ['Licence file', $l['by_file']], ['Awaiting approval', $l['awaiting']], ['Expired', $l['expired']]] as [$label, $v])
                <li><span class="lbl">{{ $label }}</span><span class="bar"><i style="width: {{ round($v / $lmax * 100) }}%"></i></span><span class="num">{{ $n($v) }}</span></li>
            @endforeach
        </ul>
    </section>

    {{-- Users --}}
    <section class="agx-card s4" aria-labelledby="agx-users">
        <div class="agx-head"><span class="ico" aria-hidden="true">●</span><span id="agx-users">Users</span><a href="{{ UserResource::getUrl() }}">Open</a></div>
        <div class="agx-row">
            <div>
                <div class="agx-big">{{ $n($u['total']) }}</div>
                <div class="agx-sub">{!! $chip($u['change']) !!} {{ $n($u['new_week']) }} new this week</div>
            </div>
            <svg viewBox="0 0 150 46" width="150" height="46" style="margin-left:auto;flex:none" role="img" aria-label="Sign-ups per day, last 30 days">
                @foreach ($daily as $i => $v)
                    @php($h = max(2, round($v / $dmax * 42)))
                    <rect x="{{ $i * 5 }}" y="{{ 46 - $h }}" width="3" height="{{ $h }}" rx="1.5" fill="{{ $v ? '#2cb7d9' : '#dbe3ea' }}" />
                @endforeach
            </svg>
        </div>
        <div class="agx-split">
            <div><b>{{ $n($u['free']) }}</b><span>Free plan</span></div>
            <div><b>{{ $n($u['enterprise']) }}</b><span>Enterprise plan</span></div>
        </div>
        <div style="display:flex;align-items:center;gap:12px;margin-top:12px">
            <svg viewBox="0 0 124 70" width="124" height="70" role="img" aria-label="{{ round($entShare * 100) }}% of customers on Enterprise">
                <path d="M10 62 A52 52 0 0 1 114 62" fill="none" stroke="#e6e9ee" stroke-width="12" stroke-linecap="round" />
                @if ($entShare > 0)
                    <path d="M10 62 A52 52 0 0 1 114 62" fill="none" stroke="url(#agxg2)" stroke-width="12" stroke-linecap="round" stroke-dasharray="{{ round($arc * $entShare, 2) }} 999" />
                @endif
                <defs><linearGradient id="agxg2" x1="0" x2="1"><stop offset="0" stop-color="#9eeaff" /><stop offset="1" stop-color="#2cb7d9" /></linearGradient></defs>
            </svg>
            <div><div style="font-size:22px;font-weight:500">{{ round($entShare * 100) }}%</div><div class="agx-sub" style="margin:0">on Enterprise · admins not counted</div></div>
        </div>
    </section>

    {{-- Activity heatmap --}}
    <section class="agx-card s3" aria-labelledby="agx-heat">
        <div class="agx-head"><span class="ico" aria-hidden="true">▦</span><span id="agx-heat">Last 7 days</span></div>
        <div class="agx-sub">{{ $n($s['heatmap']['total']) }} events</div>
        <div class="agx-heat" role="table" aria-label="Activity per day">
            <span></span>
            @foreach ($s['heatmap']['days'] as $d)<span class="day" role="columnheader">{{ substr($d, 0, 2) }}</span>@endforeach
            @foreach ($s['heatmap']['rows'] as $label => $vals)
                <span role="rowheader">{{ $label }}</span>
                @foreach ($vals as $i => $v)
                    @php($a = $v ? 0.25 + 0.75 * $v / $s['heatmap']['max'] : 0)
                    <span class="cell" role="cell" title="{{ $label }}, {{ $s['heatmap']['days'][$i] }}: {{ $v }}" aria-label="{{ $v }}"
                        @if ($v) style="background: rgba(44, 183, 217, {{ round($a, 2) }})" @endif></span>
                @endforeach
            @endforeach
        </div>
    </section>

    {{-- Messages --}}
    <section class="agx-card s5" aria-labelledby="agx-msg">
        <div class="agx-head"><span class="ico" aria-hidden="true">✉</span><span id="agx-msg">Messages</span><a href="{{ ContactMessageResource::getUrl() }}">All messages</a></div>
        <div class="agx-sub" style="margin-top:8px">
            <span class="agx-pill warn">{{ $n($m['new']) }} new</span>
            <span class="agx-pill info">{{ $n($m['in_progress']) }} in progress</span>
            <span class="agx-pill ok">{{ $n($m['resolved']) }} resolved</span>
            · {!! $chip($m['change'], false) !!} {{ $n($m['week']) }} this week
        </div>
        @if ($m['latest']->isNotEmpty())
            <table class="agx-table">
                <thead><tr><th scope="col">From</th><th scope="col">Topic</th><th scope="col">Status</th></tr></thead>
                <tbody>
                    @foreach ($m['latest'] as $msg)
                        <tr class="r">
                            <td>
                                <a class="who" href="{{ ContactMessageResource::getUrl('view', ['record' => $msg]) }}">
                                    <span class="av" aria-hidden="true">{{ mb_strtoupper(mb_substr($msg->name ?: $msg->email, 0, 1)) }}</span>
                                    <span style="min-width:0"><span class="t" style="display:block">{{ $msg->subject ?: '(no subject)' }}</span><span class="s">{{ $msg->name ?: $msg->email }} · {{ $msg->created_at?->diffForHumans() }}</span></span>
                                </a>
                            </td>
                            <td class="s">{{ ContactMessage::TOPICS[$msg->topic] ?? ucfirst((string) $msg->topic) }}</td>
                            <td><span class="agx-pill {{ $statusClass[$msg->status] ?? 'mute' }}">{{ ContactMessage::STATUSES[$msg->status] ?? $msg->status }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="agx-empty">No messages yet.</div>
        @endif
    </section>

    {{-- Growth --}}
    <section class="agx-card s7" aria-labelledby="agx-trend">
        <div class="agx-head" style="flex-wrap:wrap">
            <span class="ico" aria-hidden="true">↗</span><span id="agx-trend">Growth, last 6 months</span>
            <span class="agx-legend"><span><i style="background:#2cb7d9"></i>New users</span><span><i style="background:#71f7d4"></i>New licences</span></span>
        </div>
        <div class="agx-row" style="margin-top:6px">
            <div><div class="agx-big" style="font-size:30px">{{ $n(array_sum($t['users'])) }}</div><div class="agx-sub">users · {{ $n(array_sum($t['licences'])) }} licences</div></div>
        </div>
        <svg viewBox="0 0 600 180" width="100%" height="200" preserveAspectRatio="none" role="img"
            aria-label="New users per month: {{ collect($t['labels'])->zip($t['users'])->map(fn ($p) => $p[0].' '.$p[1])->implode(', ') }}">
            @foreach ([0, 0.5, 1] as $g)
                <line x1="20" x2="580" y1="{{ 150 - $g * 120 }}" y2="{{ 150 - $g * 120 }}" stroke="#eef1f4" stroke-dasharray="{{ $g ? '4 4' : '' }}" />
                <text x="0" y="{{ 154 - $g * 120 }}" font-size="10" fill="#8a9099">{{ round($tmax * $g) }}</text>
            @endforeach
            <defs><linearGradient id="agxf" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#2cb7d9" stop-opacity=".22" /><stop offset="1" stop-color="#2cb7d9" stop-opacity="0" /></linearGradient></defs>
            <polygon points="20,150 {{ $pt($t['users']) }} 580,150" fill="url(#agxf)" />
            <polyline points="{{ $pt($t['licences']) }}" fill="none" stroke="#71f7d4" stroke-width="2.5" stroke-linejoin="round" />
            <polyline points="{{ $pt($t['users']) }}" fill="none" stroke="#2cb7d9" stroke-width="2.5" stroke-linejoin="round" />
            @foreach ($t['labels'] as $i => $label)
                <text x="{{ 20 + $i * (560 / max(1, $last)) }}" y="172" font-size="11" fill="#8a9099" text-anchor="middle">{{ $label }}</text>
            @endforeach
            @php([$lx, $ly] = explode(',', explode(' ', $pt($t['users']))[$last]))
            <circle cx="{{ $lx }}" cy="{{ $ly }}" r="5" fill="#fff" stroke="#2cb7d9" stroke-width="3" />
        </svg>
    </section>
</div>
