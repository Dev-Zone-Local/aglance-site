import { useEffect, useState } from "react";
import { api } from "../lib/api";
import { Mail, Headphones, MessageSquare, Github } from "lucide-react";

export default function Contact() {
  const [c, setC] = useState(null);
  useEffect(() => {
    api.get("/cms/contact").then((r) => setC(r.data));
  }, []);

  if (!c) return <div className="max-w-3xl mx-auto px-5 sm:px-8 py-16 text-zinc-500 font-mono">Loading…</div>;

  const items = [
    { icon: Mail, label: "General", value: c.email },
    { icon: MessageSquare, label: "Sales", value: c.sales_email },
    { icon: Headphones, label: "Support", value: c.support_email },
  ];

  return (
    <div data-testid="contact-page" className="max-w-4xl mx-auto px-5 sm:px-8 py-16">
      <div className="text-[11px] uppercase tracking-[0.22em] text-amber-500 font-mono mb-4">Contact</div>
      <h1 className="text-4xl sm:text-5xl font-semibold tracking-tighter text-zinc-50 leading-[1.05] mb-6">
        Talk to a real engineer.
      </h1>
      <p className="text-lg text-zinc-400 leading-relaxed">
        AtGlance is built by a small team. If you reach out, an actual SRE will read your message.
      </p>

      <div className="grid md:grid-cols-3 gap-5 mt-12">
        {items.map((it, i) => (
          <a
            key={i}
            href={`mailto:${it.value}`}
            data-testid={`contact-${it.label.toLowerCase()}`}
            className="lift rounded-xl border border-zinc-800 bg-[#101012] p-6 block"
          >
            <it.icon size={18} className="text-amber-500 mb-4" />
            <div className="text-[11px] uppercase tracking-[0.18em] text-zinc-500 font-mono mb-1">{it.label}</div>
            <div className="text-zinc-100 break-all">{it.value}</div>
          </a>
        ))}
      </div>

      {c.github && (
        <a
          href={c.github}
          target="_blank"
          rel="noreferrer"
          data-testid="contact-github"
          className="lift inline-flex items-center gap-3 mt-8 rounded-xl border border-zinc-800 bg-[#101012] px-5 py-4"
        >
          <Github size={18} className="text-amber-500" />
          <div>
            <div className="text-[11px] uppercase tracking-[0.18em] text-zinc-500 font-mono">GitHub</div>
            <div className="text-zinc-100">{c.github}</div>
          </div>
        </a>
      )}

      <div className="mt-12 text-sm text-zinc-500 font-mono">
        {c.address}
      </div>
    </div>
  );
}
