import { Link } from "react-router-dom";
import { HeroTerminal } from "../components/HeroTerminal";
import { ArchDiagram } from "../components/ArchDiagram";
import { SectionHeading } from "../components/FeatureCard";
import { StoryFlow } from "../components/StoryFlow";
import { Terminal } from "../components/Terminal";
import { Button, Card, Feature, FeatureGrid, Hero } from "../components/ag";
import {
  ShieldCheck, Server, Cpu, GitBranch, Network, Activity, Database, ArrowRight, Lock,
} from "lucide-react";

const ARCH_E2E = "/images/End-to-End Architecture.png";

const HIGHLIGHTS = ["Ubuntu / systemd", "REST API gateway", "RBAC + PAT tokens", "DB circuit breaker"];

export default function Home() {
  return (
    <div data-testid="home-page" className="space-y-16">
      {/* HERO: dark ink banner with corner glows (design-doc §7.5) */}
      <Hero
        className="rise"
        eyebrow={<><span className="h-1.5 w-1.5 rounded-full bg-ag-mint" /> Self-hosted · For SREs by SREs</>}
        title={<>Operations at a glance,<br />inside your boundary.</>}
        actions={
          <>
            <Button to="/register" size="lg" data-testid="hero-cta-signup">
              Sign up free <ArrowRight size={16} />
            </Button>
            <Button to="/docs/quickstart" size="lg" variant="white" data-testid="hero-cta-quickstart">
              Read the quickstart
            </Button>
          </>
        }
        aside={<HeroTerminal />}
      >
        <p>
          A CLI-focused operations platform with a calm, auditable CLI and a self-hosted Management Console. No
          telemetry. No vendor lock-in.
        </p>
        <div className="mt-6 flex flex-wrap gap-x-5 gap-y-2 text-xs text-ag-ink-soft">
          {HIGHLIGHTS.map((h) => (
            <span key={h} className="flex items-center gap-1.5">
              <span className="h-1 w-1 rounded-full bg-ag-mint" /> {h}
            </span>
          ))}
        </div>
      </Hero>

      {/* TWO SURFACES */}
      <section>
        <SectionHeading
          eyebrow="One platform · Two surfaces"
          title="A CLI for the host. A Console for the org."
          sub="The CLI runs on every Linux/systemd host and handles discovery, registration and configuration safety. The Console runs inside your boundary and gives operations teams dashboards, RBAC and resilience."
        />
        <div className="mt-8 grid gap-4 md:grid-cols-2">
          {[
            {
              to: "/cli", icon: Cpu, title: "AtGlance CLI", meta: "/usr/local/bin/atglance", testId: "home-cli-card", cta: "Explore the CLI",
              text: "A focused agent for systemd hosts: service discovery, health, configuration backup and import, registration, deregistration and reactivation.",
            },
            {
              to: "/console", icon: Server, title: "Management Console", meta: "Self-hosted", testId: "home-console-card", cta: "Explore the Console",
              text: "Self-hosted console with REST API gateway, database and cache. Dashboards, RBAC, configuration history, queue resilience and a database circuit breaker.",
            },
          ].map((c) => (
            <Link key={c.to} to={c.to} data-testid={c.testId} className="group">
              <Card hover className="h-full p-7">
                <div className="mb-5 flex items-center justify-between">
                  <span className="inline-flex h-9 w-9 items-center justify-center rounded-[10px] bg-ag-ink text-ag-mint">
                    <c.icon size={16} />
                  </span>
                  <span className="font-mono text-[11px] text-ag-muted">{c.meta}</span>
                </div>
                <h3 className="mb-2 text-[22px] font-medium tracking-heading text-ag-ink">{c.title}</h3>
                <p className="mb-5 leading-relaxed text-ag-subtle">{c.text}</p>
                <span className="inline-flex items-center gap-1.5 text-sm font-medium text-ag-teal transition-all group-hover:gap-2.5">
                  {c.cta} <ArrowRight size={14} />
                </span>
              </Card>
            </Link>
          ))}
        </div>
      </section>

      {/* FEATURES */}
      <section>
        <SectionHeading eyebrow="Why AtGlance" title="Calm operations. Strict boundaries." />
        <FeatureGrid className="mt-6">
          <Feature testId="feat-self-hosted" icon={ShieldCheck} title="Self-hosted by design">
            Deploy the entire Console inside your VPC, on-prem or hybrid. No data leaves your perimeter.
          </Feature>
          <Feature testId="feat-systemd" icon={Activity} title="Native systemd discovery">
            The CLI reads journalctl and ss/netstat on Ubuntu hosts to discover services and their ports. No extra daemons.
          </Feature>
          <Feature testId="feat-config" icon={GitBranch} title="Configuration safety">
            Every config change writes a versioned backup. Import and validate before applying. Rollback is a single flag.
          </Feature>
          <Feature testId="feat-rbac" icon={Lock} title="RBAC + PAT tokens">
            Three roles (superadmin, admin, user) and short-lived Personal Access Tokens for the CLI. Every action is auditable.
          </Feature>
          <Feature testId="feat-resilience" icon={Database} title="DB circuit breaker">
            When the database is unhealthy, writes are buffered as queued jobs and replayed idempotently when it recovers.
          </Feature>
          <Feature testId="feat-api" icon={Network} title="One REST API">
            A single REST surface for registration, configuration files and token validation, fronted by a rate-limited gateway.
          </Feature>
        </FeatureGrid>
      </section>

      {/* RESILIENCE STORY */}
      <section>
        <SectionHeading
          eyebrow="Resilience story"
          title="Database outage? Your writes don't disappear."
          sub="What happens behind the curtain when the database goes down."
        />
        <div className="mt-8">
          <StoryFlow
            steps={[
              { title: "DB outage detected", desc: "The circuit breaker trips. Reads fall back to cache where safe." },
              { title: "Writes are queued", desc: "Mutations are encoded as jobs and buffered in the cache. Clients get fast acks." },
              { title: "DB recovers", desc: "Health checks pass; the circuit closes. Workers pick up where they left off." },
              { title: "Jobs replay", desc: "Queue workers apply buffered writes idempotently. State converges. Audit logs restored." },
            ]}
          />
        </div>
      </section>

      {/* ARCHITECTURE PEEK */}
      <section>
        <SectionHeading
          eyebrow="Architecture"
          title="A boring, predictable stack."
          sub="A REST gateway fronts the Console, backed by a database and a cache. The CLI talks to the gateway over HTTPS. That's it."
        />
        <Card className="mt-8 p-3 sm:p-4">
          <ArchDiagram
            testId="home-arch-diagram"
            src={ARCH_E2E}
            alt="AtGlance End-to-End Architecture"
            caption="End-to-end · CLI → Gateway → Console → Database / Cache"
          />
        </Card>
        <div className="mt-6 text-center">
          <Link to="/architecture" data-testid="home-arch-link" className="inline-flex items-center gap-2 text-sm font-medium text-ag-teal transition-all hover:gap-3">
            Read the architecture deep-dive <ArrowRight size={14} />
          </Link>
        </div>
      </section>

      {/* INSTALL: dark banner */}
      <section className="ag-hero px-6 py-8 sm:px-10 sm:py-12">
        <div className="grid items-center gap-10 lg:grid-cols-2">
          <div>
            <h2 className="mb-3 text-[26px] sm:text-[30px] font-medium tracking-heading text-white">
              One curl. systemd-friendly. Idempotent.
            </h2>
            <p className="max-w-lg text-base leading-[1.7] text-ag-ink-soft">
              The CLI is a single binary. It writes its config to ~/.config/atglance and registers your host with the Console.
            </p>
            <div className="mt-8 flex flex-wrap gap-3">
              <Button to="/register" data-testid="cta-bottom-signup">
                Get started free <ArrowRight size={16} />
              </Button>
              <Button to="/docs/cli" variant="white" data-testid="cta-bottom-cli">
                CLI reference
              </Button>
            </div>
          </div>
          <Terminal
            title="install.sh"
            className="ring-1 ring-white/10"
            lines={[
              "$ curl -sSL https://app.atglance.live/cli/install.sh | sudo bash",
              "→ Detected: Ubuntu 22.04 · systemd 249",
              "→ Installed atglance v2.0.0 → /usr/local/bin/atglance",
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
