import { SectionHeading } from "../components/FeatureCard";
import { RBACTable } from "../components/RBACTable";
import { Lock, ShieldCheck, KeyRound, FileLock, Database, Activity } from "lucide-react";

const ITEMS = [
  { icon: Lock, title: "Self-hosted, by design", desc: "The Console runs entirely inside your boundary. There is no telemetry to atglance.live." },
  { icon: KeyRound, title: "PAT tokens with scopes", desc: "Each CLI host carries a Personal Access Token. Tokens are short-lived, revocable, scoped per system." },
  { icon: ShieldCheck, title: "RBAC at the route layer", desc: "auth.session + auth.pat + admin.role middleware composed declaratively. Policy enforcement is impossible to forget." },
  { icon: FileLock, title: "Configuration backups, immutable", desc: "Every imported configuration is hashed and versioned. File storage can be local or S3 with object-level locks." },
  { icon: Database, title: "Database circuit breaker", desc: "When the DB is unhealthy, mutations buffer to Cache. Replays are idempotent. No silent data loss." },
  { icon: Activity, title: "Auditable everything", desc: "register / deregister / reactivate / config-import all write to activity_logs in Database with operator + timestamp." },
];

export default function Security() {
  return (
    <div data-testid="security-page" className="max-w-7xl mx-auto px-5 sm:px-8 py-16">
      <div className="text-[11px] uppercase tracking-[0.22em] text-amber-500 font-mono mb-4">Security</div>
      <h1 className="text-4xl sm:text-5xl lg:text-6xl font-semibold tracking-tighter text-zinc-50 leading-[1.05] mb-6 max-w-4xl">
        Quiet by default.<br />Auditable by design.
      </h1>
      <p className="text-lg text-zinc-400 max-w-3xl leading-relaxed">
        AtGlance is engineered for organisations whose first question is "where does the data live?" — and whose second is "who can change what?"
      </p>

      <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-5 mt-14">
        {ITEMS.map((it, i) => (
          <div key={i} className="lift rounded-xl border border-zinc-800 bg-[#101012] p-6" data-testid={`security-${i}`}>
            <div className="w-10 h-10 rounded-lg flex items-center justify-center bg-amber-500/10 border border-amber-500/30 mb-5">
              <it.icon size={18} className="text-amber-500" />
            </div>
            <h3 className="text-lg font-semibold text-zinc-100 mb-2">{it.title}</h3>
            <p className="text-sm text-zinc-400 leading-relaxed">{it.desc}</p>
          </div>
        ))}
      </div>

      <div className="mt-20">
        <SectionHeading eyebrow="RBAC" title="Three roles. Strict separation." />
        <div className="mt-8"><RBACTable /></div>
      </div>
    </div>
  );
}
