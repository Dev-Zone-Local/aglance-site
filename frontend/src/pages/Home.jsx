import { Link } from "react-router-dom";
import { HeroTerminal } from "../components/HeroTerminal";
import { ArchDiagram } from "../components/ArchDiagram";
import { FeatureCard, SectionHeading } from "../components/FeatureCard";
import { StoryFlow } from "../components/StoryFlow";
import { Terminal } from "../components/Terminal";
import {
  ShieldCheck, Server, Workflow, Cpu, GitBranch, Network, Activity, Database, ArrowRight, Lock,
} from "lucide-react";

const ARCH_E2E = "https://customer-assets.emergentagent.com/job_a89336b5-1df0-4e9a-bcfc-111dd4dd1c6a/artifacts/7ejgk1sv_AtGlance%20Full%20Architecture%20cli%20with%20management%20console.png";

export default function Home() {
  return (
    <div data-testid="home-page">
      {/* HERO */}
      <section className="relative overflow-hidden">
        <div className="absolute inset-0 grid-bg opacity-50 pointer-events-none" />
        <div className="absolute inset-0 dotted-bg opacity-30 pointer-events-none" />
        <div className="absolute -top-32 -right-32 w-[480px] h-[480px] rounded-full bg-amber-500/10 blur-[120px] pointer-events-none" />
        <div className="relative max-w-7xl mx-auto px-5 sm:px-8 pt-20 pb-24 grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
          <div className="rise">
            <div className="inline-flex items-center gap-2 text-[11px] font-mono uppercase tracking-[0.22em] text-amber-500 px-3 py-1.5 rounded-full bg-amber-500/10 border border-amber-500/30 mb-7" data-testid="hero-eyebrow">
              <span className="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse" />
              Self-hosted · For SREs by SREs
            </div>
            <h1 className="text-4xl sm:text-5xl lg:text-6xl font-semibold tracking-tighter text-zinc-50 leading-[1.05] mb-6">
              Operations,{" "}
              <span className="text-amber-500">at a glance</span>
              <br />
              inside your boundary.
            </h1>
            <p className="text-lg text-zinc-400 leading-relaxed max-w-xl mb-8">
              An Ubuntu-focused operations platform with a calm, auditable CLI and a self-hosted Management Console — built on Kong, Laravel, MySQL and Redis. No telemetry. No vendor lock-in.
            </p>
            <div className="flex flex-wrap items-center gap-3 mb-8">
              <Link
                to="/register"
                data-testid="hero-cta-signup"
                className="inline-flex items-center gap-2 bg-amber-500 text-zinc-950 font-medium px-5 py-3 rounded-md hover:bg-amber-400 transition-colors"
              >
                Sign up — Free <ArrowRight size={16} />
              </Link>
              <Link
                to="/docs/quickstart"
                data-testid="hero-cta-quickstart"
                className="inline-flex items-center gap-2 border border-zinc-800 text-zinc-200 px-5 py-3 rounded-md hover:bg-zinc-900 transition-colors"
              >
                Read the quickstart
              </Link>
            </div>
            <div className="flex flex-wrap items-center gap-x-6 gap-y-2 text-xs font-mono text-zinc-500">
              <span className="flex items-center gap-1.5"><span className="w-1 h-1 rounded-full bg-amber-500" /> Ubuntu / systemd</span>
              <span className="flex items-center gap-1.5"><span className="w-1 h-1 rounded-full bg-amber-500" /> Kong API Gateway</span>
              <span className="flex items-center gap-1.5"><span className="w-1 h-1 rounded-full bg-amber-500" /> RBAC + PAT tokens</span>
              <span className="flex items-center gap-1.5"><span className="w-1 h-1 rounded-full bg-amber-500" /> DB circuit breaker</span>
            </div>
          </div>
          <div className="rise" style={{ animationDelay: "120ms" }}>
            <HeroTerminal />
          </div>
        </div>
      </section>

      {/* TWO PARTS */}
      <section className="max-w-7xl mx-auto px-5 sm:px-8 py-20">
        <SectionHeading
          eyebrow="One platform · Two surfaces"
          title="A CLI for the host. A Console for the org."
          sub="The CLI runs on every Linux/systemd host and handles discovery, registration, configuration safety. The Console runs inside your boundary and gives operations teams dashboards, RBAC and resilience."
        />
        <div className="grid md:grid-cols-2 gap-6 mt-12">
          <Link to="/cli" className="lift relative rounded-2xl border border-zinc-800 bg-[#101012] p-7 group" data-testid="home-cli-card">
            <span className="absolute top-5 right-5 text-[10px] uppercase tracking-[0.2em] font-mono text-amber-500">/usr/local/bin/atglance</span>
            <Cpu size={20} className="text-amber-500 mb-5" />
            <h3 className="text-2xl font-semibold text-zinc-100 mb-2">AtGlance CLI</h3>
            <p className="text-zinc-400 mb-5 leading-relaxed">
              A focused agent for systemd hosts: service discovery, health, configuration backup &amp; import, registration, deregistration, reactivation.
            </p>
            <div className="text-sm text-amber-500 inline-flex items-center gap-1.5 group-hover:gap-2.5 transition-all">
              Explore the CLI <ArrowRight size={14} />
            </div>
          </Link>
          <Link to="/console" className="lift relative rounded-2xl border border-zinc-800 bg-[#101012] p-7 group" data-testid="home-console-card">
            <span className="absolute top-5 right-5 text-[10px] uppercase tracking-[0.2em] font-mono text-amber-500">https://console.local:8002</span>
            <Server size={20} className="text-amber-500 mb-5" />
            <h3 className="text-2xl font-semibold text-zinc-100 mb-2">Management Console</h3>
            <p className="text-zinc-400 mb-5 leading-relaxed">
              Self-hosted Laravel app with Kong, MySQL and Redis. Dashboards, RBAC, configuration history, queue resilience and a database circuit breaker.
            </p>
            <div className="text-sm text-amber-500 inline-flex items-center gap-1.5 group-hover:gap-2.5 transition-all">
              Explore the Console <ArrowRight size={14} />
            </div>
          </Link>
        </div>
      </section>

      {/* FEATURES */}
      <section className="max-w-7xl mx-auto px-5 sm:px-8 py-20">
        <SectionHeading
          eyebrow="Why AtGlance"
          title="Calm operations. Strict boundaries."
        />
        <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-5 mt-12">
          <FeatureCard testId="feat-self-hosted" icon={ShieldCheck} title="Self-hosted by design" desc="Deploy the entire Console — Kong, Laravel, MySQL, Redis — inside your VPC, on-prem or hybrid. No data leaves your perimeter." />
          <FeatureCard testId="feat-systemd" icon={Activity} title="Native systemd discovery" desc="The CLI reads journalctl + ss/netstat on Ubuntu hosts to discover services and their ports. No agents, no daemons you didn't ask for." />
          <FeatureCard testId="feat-config" icon={GitBranch} title="Configuration safety" desc="Every config change writes a versioned backup to ~/config-backups. Import and validate before applying. Rollback is a single flag." />
          <FeatureCard testId="feat-rbac" icon={Lock} title="RBAC + PAT tokens" desc="Three roles — superadmin, admin, user — and short-lived Personal Access Tokens for the CLI. Every action is auditable." />
          <FeatureCard testId="feat-resilience" icon={Database} title="DB circuit breaker" desc="When MySQL is unhealthy, writes are buffered to Redis as queued jobs and replayed idempotently when the database recovers." />
          <FeatureCard testId="feat-kong" icon={Network} title="Kong-native API" desc="A single REST surface — /system-register, /config-files, /validate-token — fronted by Kong in DB-less mode. Rate-limited, declarative." />
        </div>
      </section>

      {/* RESILIENCE STORY */}
      <section className="max-w-7xl mx-auto px-5 sm:px-8 py-20">
        <SectionHeading
          eyebrow="Resilience story"
          title="DB outage? Your writes don't disappear."
          sub="A short flow that explains what happens behind the curtain when MySQL goes down."
        />
        <div className="mt-10">
          <StoryFlow
            steps={[
              { title: "DB outage detected", desc: "DatabaseCircuitBreaker middleware trips. Reads fall back to cache where safe." },
              { title: "Writes are queued", desc: "Mutations are encoded as jobs and buffered in Redis. Clients get fast acks." },
              { title: "DB recovers", desc: "Health checks pass; the circuit closes. Workers pick up where they left off." },
              { title: "Jobs replay", desc: "Queue workers apply buffered writes idempotently. State converges. Audit logs restored." },
            ]}
          />
        </div>
      </section>

      {/* ARCH PEEK */}
      <section className="max-w-7xl mx-auto px-5 sm:px-8 py-20">
        <SectionHeading
          eyebrow="Architecture"
          title="A boring, predictable stack."
          sub="Kong fronts a Laravel app backed by MySQL 8 and Redis. The CLI talks to Kong via HTTPS. That's it."
        />
        <div className="mt-10 rise">
          <ArchDiagram
            testId="home-arch-diagram"
            src={ARCH_E2E}
            alt="AtGlance End-to-End Architecture"
            caption="End-to-end · CLI → Kong → Laravel → MySQL/Redis"
          />
        </div>
        <div className="mt-6 text-center">
          <Link
            to="/architecture"
            data-testid="home-arch-link"
            className="inline-flex items-center gap-2 text-amber-500 text-sm hover:gap-3 transition-all"
          >
            Read the architecture deep-dive <ArrowRight size={14} />
          </Link>
        </div>
      </section>

      {/* CLI INSTALL */}
      <section className="max-w-7xl mx-auto px-5 sm:px-8 py-20">
        <div className="grid lg:grid-cols-2 gap-12 items-center">
          <div>
            <SectionHeading
              eyebrow="Install in seconds"
              title="One curl. systemd-friendly. Idempotent."
              sub="The CLI is a single binary. It writes its config to ~/.config/atglance and registers your host with the Console."
            />
            <div className="mt-6 flex flex-wrap gap-3">
              <Link to="/register" data-testid="cta-bottom-signup" className="inline-flex items-center gap-2 bg-amber-500 text-zinc-950 font-medium px-5 py-3 rounded-md hover:bg-amber-400">
                Get started — Free <ArrowRight size={16} />
              </Link>
              <Link to="/docs/cli" data-testid="cta-bottom-cli" className="inline-flex items-center gap-2 border border-zinc-800 px-5 py-3 rounded-md text-zinc-200 hover:bg-zinc-900">
                CLI reference
              </Link>
            </div>
          </div>
          <Terminal
            title="install.sh"
            lines={[
              "$ curl -sSL https://atglance.live/install.sh | sudo bash",
              "→ Detected: Ubuntu 22.04 · systemd 249",
              "→ Installed atglance v1.0.0 → /usr/local/bin/atglance",
              "$ atglance --configure",
              "✓ Wrote ~/.config/atglance/config.json",
              "$ atglance --system-register",
              "✓ Registered system_id=sys_8e1a in org=acme",
            ]}
          />
        </div>
      </section>
    </div>
  );
}
