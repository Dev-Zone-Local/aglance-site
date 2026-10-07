// Livewire ships Alpine.js. Importing both from Livewire's ESM build lets us register small
// Alpine components here before Livewire starts (the layout uses @livewireScriptConfig).
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';

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
const SCRIPT = [
    { t: 'atglance --configure', out: ['✓ Wrote ~/.config/atglance/config.json'] },
    {
        t: 'atglance --system-register',
        out: ['→ POST https://console.local:8002/system-register', '✓ Registered as system_id=sys_8e1a • org=acme'],
    },
    {
        t: 'atglance --show-my-services',
        out: [
            'nginx.service           active   8080,8443',
            'postgresql@14-main      active   5432',
            'atglance-agent.service  active   ─',
            '✓ 3 services discovered via systemd',
        ],
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
