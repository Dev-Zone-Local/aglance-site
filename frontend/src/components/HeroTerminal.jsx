import { useEffect, useState } from "react";

const SCRIPT = [
  { p: "$ ", t: "atglance --configure", out: ["✓ Wrote ~/.config/atglance/config.json"] },
  { p: "$ ", t: "atglance --system-register", out: [
    "→ POST https://console.local:8002/system-register",
    "✓ Registered as system_id=sys_8e1a • org=acme",
  ]},
  { p: "$ ", t: "atglance --show-my-services", out: [
    "nginx.service           active   8080,8443",
    "postgresql@14-main      active   5432",
    "atglance-agent.service  active   ─",
    "✓ 3 services discovered via systemd",
  ]},
];

export function HeroTerminal() {
  const [cmdIdx, setCmdIdx] = useState(0);
  const [typed, setTyped] = useState("");
  const [showOut, setShowOut] = useState(false);
  const [outIdx, setOutIdx] = useState(0);

  useEffect(() => {
    const cmd = SCRIPT[cmdIdx];
    if (typed.length < cmd.t.length) {
      const id = setTimeout(() => setTyped(cmd.t.slice(0, typed.length + 1)), 55);
      return () => clearTimeout(id);
    }
    if (!showOut) {
      const id = setTimeout(() => setShowOut(true), 350);
      return () => clearTimeout(id);
    }
    if (outIdx < cmd.out.length) {
      const id = setTimeout(() => setOutIdx(outIdx + 1), 320);
      return () => clearTimeout(id);
    }
    const id = setTimeout(() => {
      setCmdIdx((cmdIdx + 1) % SCRIPT.length);
      setTyped("");
      setShowOut(false);
      setOutIdx(0);
    }, 1900);
    return () => clearTimeout(id);
  }, [cmdIdx, typed, showOut, outIdx]);

  const history = SCRIPT.slice(0, cmdIdx);
  const currentCmd = SCRIPT[cmdIdx];

  return (
    <div className="relative rounded-2xl overflow-hidden border border-zinc-800 bg-[#08080a] gold-glow" data-testid="hero-terminal">
      <div className="flex items-center gap-2 px-4 py-3 border-b border-zinc-800 bg-zinc-950/80">
        <span className="w-2.5 h-2.5 rounded-full bg-zinc-700" />
        <span className="w-2.5 h-2.5 rounded-full bg-zinc-700" />
        <span className="w-2.5 h-2.5 rounded-full bg-zinc-700" />
        <span className="ml-3 text-[11px] uppercase tracking-[0.18em] text-zinc-500 font-mono">sre@web-01 — atglance</span>
        <span className="ml-auto text-[10px] text-amber-500/80 font-mono">● live</span>
      </div>
      <div className="p-5 font-mono text-[13px] leading-7 min-h-[340px]">
        {history.map((h, i) => (
          <div key={i} className="opacity-60 mb-3">
            <div><span className="text-amber-500">$</span> <span className="text-zinc-200">{h.t}</span></div>
            {h.out.map((o, j) => <div key={j} className="text-zinc-500">{o}</div>)}
          </div>
        ))}
        <div>
          <span className="text-amber-500">$</span>{" "}
          <span className="text-zinc-100">{typed}</span>
          {!showOut && <span className="cursor" />}
        </div>
        {showOut && currentCmd.out.slice(0, outIdx).map((o, i) => (
          <div key={i} className="text-zinc-400 rise">{o}</div>
        ))}
      </div>
    </div>
  );
}
