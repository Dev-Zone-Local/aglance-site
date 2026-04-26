import { useState } from "react";
import { Check, Copy } from "lucide-react";

export function Terminal({ lines = [], title = "atglance@host", className = "", live = false }) {
  return (
    <div className={`relative rounded-xl overflow-hidden border border-zinc-800 bg-[#08080a] ${className}`} data-testid="terminal-block">
      <div className="flex items-center gap-2 px-4 py-2.5 border-b border-zinc-800 bg-zinc-950/60">
        <span className="w-2.5 h-2.5 rounded-full bg-zinc-700" />
        <span className="w-2.5 h-2.5 rounded-full bg-zinc-700" />
        <span className="w-2.5 h-2.5 rounded-full bg-zinc-700" />
        <span className="ml-3 text-[11px] uppercase tracking-[0.18em] text-zinc-500 font-mono">{title}</span>
        <span className="ml-auto text-[10px] text-zinc-600 font-mono">bash</span>
      </div>
      <pre className="m-0 p-5 font-mono text-[13px] leading-relaxed text-zinc-300 overflow-x-auto">
        {lines.map((l, i) => (
          <div key={i} className="whitespace-pre">
            {l.startsWith("$") || l.startsWith("#") ? (
              <span className="text-amber-500">{l[0]} </span>
            ) : null}
            <span className={l.startsWith("$") || l.startsWith("#") ? "text-zinc-200" : "text-zinc-400"}>
              {l.startsWith("$") || l.startsWith("#") ? l.slice(2) : l}
            </span>
          </div>
        ))}
        {live && <span className="cursor" />}
      </pre>
    </div>
  );
}

export function CodeBlock({ code, lang = "bash", title }) {
  const [copied, setCopied] = useState(false);
  const onCopy = async () => {
    try {
      await navigator.clipboard.writeText(code);
      setCopied(true);
      setTimeout(() => setCopied(false), 1400);
    } catch {}
  };
  return (
    <div className="relative rounded-lg overflow-hidden border border-zinc-800 bg-zinc-950" data-testid="code-block">
      <div className="flex items-center justify-between px-4 py-2 border-b border-zinc-800 bg-zinc-900/60">
        <span className="text-[11px] uppercase tracking-[0.18em] text-zinc-500 font-mono">{title || lang}</span>
        <button
          onClick={onCopy}
          data-testid="code-copy-btn"
          className="text-zinc-500 hover:text-amber-500 text-xs flex items-center gap-1.5 transition-colors"
        >
          {copied ? <><Check size={12} /> Copied</> : <><Copy size={12} /> Copy</>}
        </button>
      </div>
      <pre className="m-0 p-4 font-mono text-[13px] leading-relaxed text-zinc-300 overflow-x-auto">{code}</pre>
    </div>
  );
}
