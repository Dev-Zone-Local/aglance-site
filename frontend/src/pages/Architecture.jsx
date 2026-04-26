import { ArchDiagram } from "../components/ArchDiagram";
import { SectionHeading } from "../components/FeatureCard";

const ARCH = {
  cli: "https://customer-assets.emergentagent.com/job_a89336b5-1df0-4e9a-bcfc-111dd4dd1c6a/artifacts/f4o5vv9n_AtGlance%20CLI%20Tool%20Architecture.png",
  system: "https://customer-assets.emergentagent.com/job_a89336b5-1df0-4e9a-bcfc-111dd4dd1c6a/artifacts/4r5l0e0a_AtGlance%20Management%20Tool%20System%20Architecture.png",
  e2e: "https://customer-assets.emergentagent.com/job_a89336b5-1df0-4e9a-bcfc-111dd4dd1c6a/artifacts/7ejgk1sv_AtGlance%20Full%20Architecture%20cli%20with%20management%20console.png",
};

export default function Architecture() {
  return (
    <div data-testid="architecture-page" className="max-w-7xl mx-auto px-5 sm:px-8 py-16">
      <div className="text-[11px] uppercase tracking-[0.22em] text-amber-500 font-mono mb-4">Architecture</div>
      <h1 className="text-4xl sm:text-5xl lg:text-6xl font-semibold tracking-tighter text-zinc-50 leading-[1.05] mb-6 max-w-4xl">
        Everything you need.<br />Nothing you don't.
      </h1>
      <p className="text-lg text-zinc-400 max-w-3xl leading-relaxed">
        Three views — the CLI, the Console internals, and the end-to-end picture — at increasing levels of detail. All inside your boundary.
      </p>

      <section className="mt-16">
        <SectionHeading eyebrow="View 1 · CLI" title="AtGlance CLI" sub="Ubuntu/systemd host. Reads runtime, writes backups, talks to Kong." />
        <div className="mt-8">
          <ArchDiagram testId="arch-cli" src={ARCH.cli} alt="CLI architecture" caption="CLI · Local runtime · Backend gateway" />
        </div>
      </section>

      <section className="mt-20">
        <SectionHeading eyebrow="View 2 · Console" title="AtGlance Management System" sub="Laravel app, Kong gateway, MySQL 8, Redis (queue + cache + buffer), File storage, SSO providers." />
        <div className="mt-8">
          <ArchDiagram testId="arch-system" src={ARCH.system} alt="System architecture" caption="Console · Application · Redis · MySQL · Storage · SSO" />
        </div>
      </section>

      <section className="mt-20">
        <SectionHeading eyebrow="View 3 · End-to-end" title="Full picture" sub="The CLI commands, the gateway, the application layer, the data plane, and future capabilities." />
        <div className="mt-8">
          <ArchDiagram testId="arch-e2e" src={ARCH.e2e} alt="End-to-end architecture" caption="Operators → CLI → Kong → Laravel → Redis/MySQL → Future" />
        </div>
      </section>

      <section className="mt-20 grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
        {[
          ["Operators", "SREs, admins, developers — all interact through the CLI or the Console UI."],
          ["AtGlance CLI", "main() routes through configure/validate/register/runtime/export."],
          ["Kong API Gateway", "DB-less, declarative kong.yml. HTTPS on :8002. Rate-limited."],
          ["Laravel application", "Web UI, REST APIs, RBAC middleware, DatabaseCircuitBreaker, Job dispatch."],
          ["Redis", "Queue (jobs), Cache (reads), Buffer (writes during DB outage)."],
          ["MySQL 8", "Users, orgs, workspaces, systems, services, configuration, files, raw_data, tokens, activity_logs."],
        ].map(([t, d]) => (
          <div key={t} className="rounded-xl border border-zinc-800 bg-[#101012] p-5">
            <div className="text-[11px] uppercase tracking-[0.18em] text-amber-500 font-mono mb-2">{t}</div>
            <p className="text-sm text-zinc-400 leading-relaxed">{d}</p>
          </div>
        ))}
      </section>
    </div>
  );
}
