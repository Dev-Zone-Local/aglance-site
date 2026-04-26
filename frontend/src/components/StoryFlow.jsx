import { ArrowRight } from "lucide-react";

export function StoryFlow({ steps, title, eyebrow }) {
  return (
    <div className="rounded-2xl border border-zinc-800 bg-[#0c0c0e] p-6 sm:p-8" data-testid="story-flow">
      {eyebrow && <div className="text-[11px] uppercase tracking-[0.22em] text-amber-500 font-mono mb-2">{eyebrow}</div>}
      {title && <h3 className="text-xl font-semibold text-zinc-100 mb-6">{title}</h3>}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-3 md:gap-2">
        {steps.map((s, i) => (
          <div key={i} className="relative group">
            <div className="rounded-lg border border-zinc-800 bg-zinc-950 p-4 h-full lift">
              <div className="text-[10px] font-mono text-amber-500 mb-2">STEP {String(i + 1).padStart(2, "0")}</div>
              <div className="text-sm font-semibold text-zinc-100 mb-1">{s.title}</div>
              <div className="text-xs text-zinc-500 leading-relaxed">{s.desc}</div>
            </div>
            {i < steps.length - 1 && (
              <ArrowRight
                size={14}
                className="hidden md:block absolute top-1/2 -right-1 -translate-y-1/2 text-amber-500/70 z-10 bg-[#0c0c0e]"
              />
            )}
          </div>
        ))}
      </div>
    </div>
  );
}
