import { useState } from "react";
import { Check, Copy } from "lucide-react";

// Terminals and code stay dark: ink cards with JetBrains Mono (design-doc §4, §7.3).
// Explicit hex colours on purpose, so the transitional zinc remap does not lighten them.

export function Terminal({ lines = [], title = "atglance@host", className = "", live = false }) {
  return (
    <div className={`relative overflow-hidden rounded-card bg-[#14171B] shadow-ag-strong ${className}`} data-testid="terminal-block">
      <div className="flex items-center gap-2 border-b border-white/10 px-4 py-2.5">
        <span className="h-2.5 w-2.5 rounded-full bg-[#E45757]/80" />
        <span className="h-2.5 w-2.5 rounded-full bg-[#D98A0B]/80" />
        <span className="h-2.5 w-2.5 rounded-full bg-[#1FA874]/80" />
        <span className="ml-3 font-mono text-[11px] text-[#8A9099]">{title}</span>
        <span className="ml-auto font-mono text-[10px] text-[#5B626B]">bash</span>
      </div>
      <pre className="m-0 overflow-x-auto p-5 font-mono text-[13px] leading-relaxed text-[#E6E9EE]">
        {lines.map((l, i) => {
          const prompt = l.startsWith("$") || l.startsWith("#");
          return (
            <div key={i} className="whitespace-pre">
              {prompt && <span className="text-[#71F7D4]">{l[0]} </span>}
              <span className={prompt ? "text-white" : "text-[#B7BEC6]"}>{prompt ? l.slice(2) : l}</span>
            </div>
          );
        })}
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
    <div className="relative overflow-hidden rounded-input bg-[#14171B]" data-testid="code-block">
      <div className="flex items-center justify-between border-b border-white/10 px-4 py-2">
        <span className="font-mono text-[11px] text-[#8A9099]">{title || lang}</span>
        <button
          onClick={onCopy}
          data-testid="code-copy-btn"
          className="flex items-center gap-1.5 text-xs text-[#B7BEC6] transition-colors hover:text-[#71F7D4]"
        >
          {copied ? <><Check size={12} /> Copied</> : <><Copy size={12} /> Copy</>}
        </button>
      </div>
      <pre className="m-0 overflow-x-auto p-4 font-mono text-[13px] leading-relaxed text-[#E6E9EE]">{code}</pre>
    </div>
  );
}
