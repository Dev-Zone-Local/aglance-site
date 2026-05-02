import { ArchDiagram } from "../components/ArchDiagram";
import { Terminal, CodeBlock } from "../components/Terminal";
import { SectionHeading } from "../components/FeatureCard";
import { StoryFlow } from "../components/StoryFlow";
import { ScreenshotCarousel } from "../components/ScreenshotCarousel";
import { Link } from "react-router-dom";
import { ArrowRight } from "lucide-react";

const ARCH_CLI = "/images/AtGlance_CLI Tool Architecture.png";

const FLAGS = [
  ["--version", "Print CLI version"],
  ["--configure / configure", "Initial configuration"],
  ["--validate", "Validate local configuration"],
  ["--system-register [--restore -f/--file]", "Register the host (optionally restore from backup)"],
  ["--system-deregister", "Soft decommission"],
  ["--system-deregister-force", "Hard remove system_id"],
  ["--system-regenerate-hash", "Rotate the validation hash"],
  ["--system-reactivate", "Bring host back online"],
  ["--system-reactivate-force", "Reactivate even with stale hash"],
  ["--show-my-services", "List discovered systemd services"],
  ["--app <service>", "Inspect a single service"],
  ["--config-show / --config-import", "View / import configurations"],
  ["watch [--interval]", "Continuous health watch"],
  ["export --format json", "Export inventory"],
];

export default function Cli() {
  return (
    <div data-testid="cli-page" className="max-w-7xl mx-auto px-5 sm:px-8 py-16">
      <div className="text-[11px] uppercase tracking-[0.22em] text-amber-500 font-mono mb-4">AtGlance CLI</div>
      <h1 className="text-4xl sm:text-5xl lg:text-6xl font-semibold tracking-tighter text-zinc-50 leading-[1.05] mb-6 max-w-4xl">
        A focused, idempotent agent for systemd hosts.
      </h1>
      <p className="text-lg text-zinc-400 max-w-3xl leading-relaxed">
        Runs on Ubuntu LTS. Reads journalctl, ss/netstat, /etc/environment. Writes config backups. Talks to the Console only via Rest API.
      </p>

      <div className="grid lg:grid-cols-2 gap-10 items-center mt-14">
        <Terminal
          title="atglance · live"
          lines={[
            "$ atglance --version",
            "atglance v1.0.0 (build d8e9c41)",
            "$ atglance --validate",
            "✓ config.json valid",
            "✓ /etc/environment readable",
            "✓ journalctl + ss available",
            "✓ Console reachable (Rest API :8002)",
          ]}
        />
        <div>
          <h2 className="text-2xl font-semibold text-zinc-100 mb-3">Built around <span className="text-amber-500 font-mono">main()</span> routing</h2>
          <p className="text-zinc-400 leading-relaxed mb-5">
            The CLI's <code className="text-amber-500 font-mono text-sm">main()</code> dispatches in a strict order — config, validation, registration, runtime — so behaviour is predictable across distros.
          </p>
          <Link to="/docs/cli" data-testid="cli-docs-link" className="inline-flex items-center gap-2 text-amber-500 text-sm hover:gap-3 transition-all">
            Full CLI reference <ArrowRight size={14} />
          </Link>
        </div>
      </div>

      <div className="mt-20">
        <SectionHeading eyebrow="Flag catalog" title="Every command, one table." />
        <div className="mt-8 rounded-2xl border border-zinc-800 overflow-hidden">
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-zinc-950 border-b border-zinc-800">
                <th className="text-left px-5 py-3 font-medium text-zinc-300 text-xs uppercase tracking-[0.16em]">Flag</th>
                <th className="text-left px-5 py-3 font-medium text-zinc-300 text-xs uppercase tracking-[0.16em]">Purpose</th>
              </tr>
            </thead>
            <tbody>
              {FLAGS.map(([f, d], i) => (
                <tr key={i} className="border-b border-zinc-900 last:border-0 hover:bg-zinc-950/40">
                  <td className="px-5 py-3 text-amber-500 font-mono text-[13px] whitespace-nowrap">{f}</td>
                  <td className="px-5 py-3 text-zinc-400">{d}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      <div className="mt-20">
        <SectionHeading eyebrow="Architecture" title="Inside the CLI" />
        <div className="mt-8">
          <ArchDiagram testId="cli-arch-diagram" src={ARCH_CLI} alt="AtGlance CLI architecture" caption="CLI → Rest API → Backend · Local runtime & persistent paths" />
        </div>
      </div>

      <div className="mt-20">
        <SectionHeading eyebrow="Lifecycle" title="register → deregister → reactivate" />
        <div className="mt-8">
          <StoryFlow
            steps={[
              { title: "register", desc: "Hash + token issued. system_id persisted to /etc/environment." },
              { title: "watch", desc: "Service inventory and ports streamed to Console." },
              { title: "deregister", desc: "Soft-remove (audit trail kept) or -force (hard delete)." },
              { title: "reactivate", desc: "Brings host back without losing system_id or backups." },
            ]}
          />
        </div>
      </div>

      <div className="mt-20">
        <SectionHeading eyebrow="Persistent paths" title="Where state lives" />
        <CodeBlock
          title="paths"
          code={`~/.config/atglance/config.json   # CLI config (PAT, console URL, org_id)
config-backups/                  # versioned config snapshots
config-imports/                  # staged imports awaiting --validate
/etc/environment                 # validation hash, system_id, org_id`}
        />
      </div>

      {/* <div className="mt-20">
        <SectionHeading eyebrow="Gallery" title="CLI in action" />
        <div className="mt-8">
          <ScreenshotCarousel type="cli" />
        </div>
      </div> */}
    </div>
  );
}
