import { SectionHeading } from "../components/FeatureCard";
import { Server, ShieldCheck, GitBranch, Network, Activity, Layers } from "lucide-react";

const CASES = [
  { icon: Server, title: "Multi-host service inventory", desc: "Discover every systemd service, port and config across hundreds of Ubuntu hosts in minutes — without installing yet another agent." },
  { icon: GitBranch, title: "Safe configuration rollouts", desc: "Backup before you import. Validate before you apply. Roll back with one flag. Auditable in the Console." },
  { icon: ShieldCheck, title: "Compliance-grade auditability", desc: "Every register/deregister/config-import action is logged. RBAC enforced server-side. PAT tokens scoped per host." },
  { icon: Network, title: "Air-gapped operations", desc: "Self-host the Console behind your VPN. Rest API fronts it. The CLI talks only to your Rest API. No egress to atglance.live." },
  { icon: Activity, title: "Resilient under DB outages", desc: "Buffer writes via Cache when Database is down. Replay idempotently when it recovers. Operations don't stop." },
  { icon: Layers, title: "Hybrid + multi-cloud control", desc: "Run the same Console from one private cloud, manage hosts across on-prem and AWS/Azure/GCP. One source of truth." },
];

export default function UseCases() {
  return (
    <div data-testid="use-cases-page" className="max-w-7xl mx-auto px-5 sm:px-8 py-16">
      <div className="text-[11px] uppercase tracking-[0.22em] text-amber-500 font-mono mb-4">Use cases</div>
      <h1 className="text-4xl sm:text-5xl lg:text-6xl font-semibold tracking-tighter text-zinc-50 leading-[1.05] mb-6 max-w-4xl">
        Built for the teams who get paged.
      </h1>
      <p className="text-lg text-zinc-400 max-w-3xl leading-relaxed">
        Six concrete jobs AtGlance was built to do — without forcing you to adopt a SaaS observability stack.
      </p>

      <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-5 mt-12">
        {CASES.map((c, i) => (
          <div key={i} className="lift rounded-xl border border-zinc-800 bg-[#101012] p-6" data-testid={`use-case-${i}`}>
            <div className="w-10 h-10 rounded-lg flex items-center justify-center bg-amber-500/10 border border-amber-500/30 mb-5">
              <c.icon size={18} className="text-amber-500" />
            </div>
            <h3 className="text-lg font-semibold text-zinc-100 mb-2">{c.title}</h3>
            <p className="text-sm text-zinc-400 leading-relaxed">{c.desc}</p>
          </div>
        ))}
      </div>

      <div className="mt-20">
        <SectionHeading eyebrow="Personas" title="Who reaches for AtGlance" />
        <div className="grid md:grid-cols-3 gap-5 mt-8">
          {[
            { role: "Platform Engineer", q: "Can I roll out config changes safely across 300 Ubuntu hosts?" },
            { role: "SRE Manager", q: "Where is the audit trail for last week's incident?" },
            { role: "Security / Compliance Lead", q: "Does any data leave our perimeter? Who can do what?" },
          ].map((p, i) => (
            <div key={i} className="rounded-xl border border-zinc-800 bg-[#101012] p-6" data-testid={`persona-${i}`}>
              <div className="text-[11px] uppercase tracking-[0.18em] text-amber-500 font-mono mb-3">{p.role}</div>
              <p className="text-zinc-300 leading-relaxed">"{p.q}"</p>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
