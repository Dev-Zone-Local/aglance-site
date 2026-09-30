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
    // Ink terminal with explicit colours (not zinc), so it stays dark on the light theme.
    <div className="relative overflow-hidden rounded-card bg-[#0E1114] ring-1 ring-white/10" data-testid="hero-terminal">
      <div className="flex items-center gap-2 border-b border-white/10 px-4 py-3">
        <span className="h-2.5 w-2.5 rounded-full bg-[#E45757]/80" />
        <span className="h-2.5 w-2.5 rounded-full bg-[#D98A0B]/80" />
        <span className="h-2.5 w-2.5 rounded-full bg-[#1FA874]/80" />
        <span className="ml-3 font-mono text-[11px] text-[#8A9099]">sre@web-01 — atglance</span>
        <span className="ml-auto font-mono text-[10px] text-[#71F7D4]">● live</span>
      </div>
      <div className="min-h-[320px] overflow-x-auto p-5 font-mono text-[12px] sm:text-[13px] leading-7 [&>div]:whitespace-nowrap">
        {history.map((h, i) => (
          <div key={i} className="mb-3 opacity-60">
            <div><span className="text-[#71F7D4]">$</span> <span className="text-white">{h.t}</span></div>
            {h.out.map((o, j) => <div key={j} className="text-[#8A9099]">{o}</div>)}
          </div>
        ))}
        <div>
          <span className="text-[#71F7D4]">$</span>{" "}
          <span className="text-white">{typed}</span>
          {!showOut && <span className="cursor" />}
        </div>
        {showOut && currentCmd.out.slice(0, outIdx).map((o, i) => (
          <div key={i} className="rise text-[#B7BEC6]">{o}</div>
        ))}
      </div>
    </div>
  );
}
