// Livewire ships Alpine.js. Importing both from Livewire's ESM build lets us register small
// Alpine components here before Livewire starts (the layout uses @livewireScriptConfig).
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';

// Sign in / sign up panel under the header buttons (components/auth-panel.blade.php).
// Hovering a button opens it for a quick look; clicking, focusing or typing in it keeps it open.
// It closes on Escape, a click outside, or when the pointer leaves an untouched panel.
// Any element can open it: <button x-on:click="$dispatch('open-auth', 'register')">.
Alpine.data('authPanel', (initial = null) => ({
    open: initial !== null,
    mode: initial ?? 'login',
    pinned: initial !== null,
    timer: null,
    holdOpen: false,
    canHover: window.matchMedia('(hover: hover) and (pointer: fine)').matches,
    hover(mode) {
        if (!this.canHover || this.pinned) return;
        clearTimeout(this.timer);
        this.mode = mode;
        this.open = true;
    },
    leave() {
        if (this.pinned) return;
        clearTimeout(this.timer);
        this.timer = setTimeout(() => { this.open = false; }, 350);
    },
    stay() {
        clearTimeout(this.timer);
    },
    show(mode, focus = true) {
        clearTimeout(this.timer);
        this.mode = mode;
        this.open = true;
        this.pinned = true;
        if (focus) this.$nextTick(() => this.$refs[mode]?.querySelector('input:not([type=hidden])')?.focus());
    },
    // Opened by a button elsewhere on the page: that same click must not count as "outside".
    openFrom(mode) {
        this.holdOpen = true;
        setTimeout(() => { this.holdOpen = false; }, 0);
        window.scrollTo({ top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
        this.show(mode === 'register' ? 'register' : 'login');
    },
    toggle(mode) {
        if (this.open && this.pinned && this.mode === mode) return this.close();
        this.show(mode);
    },
    close() {
        if (this.holdOpen) return;
        clearTimeout(this.timer);
        this.open = false;
        this.pinned = false;
    },
}));

// Copy-to-clipboard button: <div x-data="copy"> ... <code x-ref="text"> ... <button @click="copy">
Alpine.data('copy', () => ({
    copied: false,
    async copy() {
        const text = this.$refs.text.innerText.trim();
        try {
            await navigator.clipboard.writeText(text);
        } catch {
            const range = document.createRange();
            range.selectNodeContents(this.$refs.text);
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
            document.execCommand('copy');
            sel.removeAllRanges();
        }
        this.copied = true;
        setTimeout(() => (this.copied = false), 1400);
    },
}));

// Home page terminal that types a few CLI commands in a loop.
// Real CLI 2.x output, shortened (taken from the CLI's docs/images/v2 captures).
const SCRIPT = [
    {
        t: 'atglance status',
        out: [
            'SERVICE                  STATE           UPTIME  PORTS  HEALTH',
            'atglance-demo.service    active/running  0m      -      Starting',
            'cron.service             active/running  53m     -      Working',
            'rsyslog.service          active/running  53m     -      Working',
            '⋮',
        ],
    },
    {
        t: 'sudo atglance system register --name wsl-docs --workspace 3 --service-account',
        out: ['Service account key (atgsa-...):', 'registered as system 9878846096 (wsl-docs, status active)'],
    },
    {
        t: 'sudo atglance sync',
        out: ['backed up atglance-demo.service as version v3 (id 2243)', 'unchanged console-getty.service', 'unchanged cron.service', '⋮'],
    },
];

Alpine.data('heroTerminal', () => ({
    history: [],
    typed: '',
    output: [],
    typing: true,
    init() {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            this.history = SCRIPT.slice(0, -1);
            this.typed = SCRIPT.at(-1).t;
            this.output = SCRIPT.at(-1).out;
            this.typing = false;
            return;
        }
        this.run(0);
    },
    wait(ms) {
        return new Promise((r) => setTimeout(r, ms));
    },
    async run(i) {
        const cmd = SCRIPT[i];
        this.typed = '';
        this.output = [];
        this.typing = true;
        for (const ch of cmd.t) {
            this.typed += ch;
            await this.wait(55);
        }
        await this.wait(350);
        this.typing = false;
        for (const line of cmd.out) {
            this.output.push(line);
            await this.wait(320);
        }
        await this.wait(1900);
        const next = (i + 1) % SCRIPT.length;
        this.history = next === 0 ? [] : [...this.history, cmd];
        this.run(next);
    },
}));

Livewire.start();
