import { Link } from "react-router-dom";
import { ArchDiagram } from "../components/ArchDiagram";
import { FeatureCard, SectionHeading } from "../components/FeatureCard";
import { StoryFlow } from "../components/StoryFlow";
import { Terminal, CodeBlock } from "../components/Terminal";
import { Cpu, Server, ArrowRight, Workflow } from "lucide-react";

const ARCH_E2E = "https://customer-assets.emergentagent.com/job_a89336b5-1df0-4e9a-bcfc-111dd4dd1c6a/artifacts/7ejgk1sv_AtGlance%20Full%20Architecture%20cli%20with%20management%20console.png";

export default function Product() {
  return (
    <div data-testid="product-page" className="max-w-7xl mx-auto px-5 sm:px-8 py-16">
      <div className="text-[11px] uppercase tracking-[0.22em] text-amber-500 font-mono mb-4">Product</div>
      <h1 className="text-4xl sm:text-5xl lg:text-6xl font-semibold tracking-tighter text-zinc-50 leading-[1.05] mb-6 max-w-4xl">
        Two surfaces. One source of truth.
      </h1>
      <p className="text-lg text-zinc-400 max-w-3xl leading-relaxed">
        AtGlance gives operations teams a single, auditable surface for service inventory and configuration — without forcing your data outside your boundary.
      </p>

      {/* The two surfaces side by side */}
      <div className="grid md:grid-cols-2 gap-6 mt-14">
        <div className="rounded-2xl border border-zinc-800 bg-[#101012] p-7" data-testid="product-cli">
          <Cpu size={20} className="text-amber-500 mb-5" />
          <h3 className="text-2xl font-semibold text-zinc-100 mb-2">CLI agent</h3>
          <p className="text-zinc-400 leading-relaxed mb-5">
            Runs on every host. Reads systemd, journalctl, ss/netstat. Writes config backups. Talks to Rest API over HTTPS using a PAT token.
          </p>
          <ul className="text-sm text-zinc-400 space-y-2">
            <li>· <code className="text-amber-500 font-mono">--system-register / --restore -f/--file</code></li>
            <li>· <code className="text-amber-500 font-mono">--system-deregister[-force]</code></li>
            <li>· <code className="text-amber-500 font-mono">--system-reactivate[-force]</code></li>
            <li>· <code className="text-amber-500 font-mono">--show-my-services</code>, <code className="text-amber-500 font-mono">--app &lt;svc&gt;</code></li>
            <li>· <code className="text-amber-500 font-mono">--config-show</code>, <code className="text-amber-500 font-mono">--config-import</code></li>
            <li>· <code className="text-amber-500 font-mono">watch [--interval]</code>, <code className="text-amber-500 font-mono">export --format json</code></li>
          </ul>
          <Link to="/cli" data-testid="product-cli-link" className="inline-flex items-center gap-2 mt-5 text-amber-500 text-sm hover:gap-3 transition-all">CLI reference <ArrowRight size={14} /></Link>
        </div>

        <div className="rounded-2xl border border-zinc-800 bg-[#101012] p-7" data-testid="product-console">
          <Server size={20} className="text-amber-500 mb-5" />
          <h3 className="text-2xl font-semibold text-zinc-100 mb-2">Management Console</h3>
          <p className="text-zinc-400 leading-relaxed mb-5">
            Application + Rest API + Database 8 + Cache. Deploy on-prem, in your VPC, or hybrid. RBAC, dashboards, configuration history.
          </p>
          <ul className="text-sm text-zinc-400 space-y-2">
            <li>· Web UI for admin/user/superadmin</li>
            <li>· REST APIs behind Rest API (DB-less, declarative)</li>
            <li>· Queue workers with retry/backoff</li>
            <li>· DatabaseCircuitBreaker middleware</li>
            <li>· File storage: local or S3</li>
            <li>· SSO providers: GitHub, Azure AD, Okta, Auth0, Google</li>
          </ul>
          <Link to="/console" data-testid="product-console-link" className="inline-flex items-center gap-2 mt-5 text-amber-500 text-sm hover:gap-3 transition-all">Console deep-dive <ArrowRight size={14} /></Link>
        </div>
      </div>

      {/* End-to-end */}
      <div className="mt-20">
        <SectionHeading eyebrow="End-to-end" title="How they fit together" />
        <div className="mt-8">
          <ArchDiagram
            testId="product-arch-diagram"
            src={ARCH_E2E}
            alt="End-to-end architecture"
            caption="CLI → Rest API API Gateway → Application + Cache + Database"
          />
        </div>
      </div>

      {/* Lifecycle */}
      <div className="mt-20">
        <SectionHeading eyebrow="System lifecycle" title="Register · Deregister · Reactivate" sub="A safe, idempotent flow for every host." />
        <div className="mt-8">
          <StoryFlow
            steps={[
              { title: "register", desc: "atglance --system-register hashes /etc/environment and registers org+system." },
              { title: "watch & sync", desc: "atglance watch streams services and ports. Configs are saved as backups." },
              { title: "deregister", desc: "--system-deregister soft-decommissions; -force removes the system_id." },
              { title: "reactivate", desc: "--system-reactivate brings the host back without losing identity." },
            ]}
          />
        </div>
      </div>

      {/* Sample install */}
      <div className="mt-20 grid lg:grid-cols-2 gap-10 items-center">
        <SectionHeading
          eyebrow="Sample"
          title="A 3-line install."
          sub="The CLI is a single binary. Configuration lives in ~/.config/atglance/config.json."
        />
        <CodeBlock
          title="bash"
          code={`curl -sSL https://atglance.live/install.sh | sudo bash
atglance --configure
atglance --system-register`}
        />
      </div>
    </div>
  );
}
