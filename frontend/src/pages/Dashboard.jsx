import { useEffect, useState } from "react";
import { api } from "../lib/api";
import { useAuth } from "../lib/auth-context";
import { Cpu, Server, Download, BookOpen, Terminal as TerminalIcon, ExternalLink } from "lucide-react";
import { Link } from "react-router-dom";
import { CodeBlock } from "../components/Terminal";
import Licenses from "../components/Licenses";
import EmailVerifyBanner from "../components/EmailVerifyBanner";

export default function Dashboard() {
  const { user } = useAuth();
  const [dl, setDl] = useState(null);

  useEffect(() => {
    api.get("/downloads").then((r) => setDl(r.data));
  }, []);

  return (
    <div data-testid="dashboard-page" className="max-w-6xl mx-auto px-5 sm:px-8 py-16">
      <EmailVerifyBanner />
      <div className="text-xs font-medium text-ag-teal mb-4">Dashboard</div>
      <h1 className="text-3xl sm:text-4xl font-medium tracking-heading text-zinc-50 mb-2">
        Welcome back{user?.name ? `, ${user.name}` : ""}.
      </h1>
      <p className="text-zinc-400 mb-12">Download AtGlance and start running it inside your boundary.</p>

      <div className="grid md:grid-cols-2 gap-6">
        <div className="lift relative rounded-card bg-white shadow-ag p-7 overflow-hidden" data-testid="dashboard-cli-card">
          <div className="absolute inset-0 grid-bg opacity-30 pointer-events-none" />
          <div className="relative">
            <div className="flex items-center gap-2 text-xs font-medium text-ag-teal mb-4">
              <Cpu size={14} /> AtGlance CLI
            </div>
            <h3 className="text-2xl font-semibold text-zinc-100 mb-1">Linux / systemd agent</h3>
            <p className="text-sm text-zinc-400 mb-5">v{dl?.cli_version || "1.0.0"} · Ubuntu LTS recommended</p>
            <a
              href="https://github.com/atglance-app/Ubuntu-CLI"
              target="_blank"
              rel="noreferrer"
              data-testid="dashboard-cli-download"
              className="inline-flex items-center gap-2 bg-ag-gradient text-ag-ink font-medium px-4 py-2.5 rounded-full hover:bg-ag-gradient-hover hover:shadow-ag-glow transition-colors"
            >
              <Download size={14} /> Download CLI <ExternalLink size={12} />
            </a>
            <div className="mt-6 text-xs text-zinc-500 font-mono break-all">
              {dl?.cli_url}
            </div>
          </div>
        </div>

        <div className="lift relative rounded-card bg-white shadow-ag p-7 overflow-hidden" data-testid="dashboard-console-card">
          <div className="absolute inset-0 grid-bg opacity-30 pointer-events-none" />
          <div className="relative">
            <div className="flex items-center gap-2 text-xs font-medium text-ag-teal mb-4">
              <Server size={14} /> Self-hosted Console
            </div>
            <h3 className="text-2xl font-semibold text-zinc-100 mb-1">Application + Rest API + Database + Cache</h3>
            <p className="text-sm text-zinc-400 mb-5">v{dl?.console_version || "1.0.0"} · Docker compose included</p>
            <a
              href="https://github.com/atglance-app/ce-console"
              target="_blank"
              rel="noreferrer"
              data-testid="dashboard-console-download"
              className="inline-flex items-center gap-2 bg-ag-gradient text-ag-ink font-medium px-4 py-2.5 rounded-full hover:bg-ag-gradient-hover hover:shadow-ag-glow transition-colors"
            >
              <Download size={14} /> Download Console <ExternalLink size={12} />
            </a>
            <div className="mt-6 text-xs text-zinc-500 font-mono break-all">
              {dl?.console_url}
            </div>
          </div>
        </div>
      </div>

      <Licenses />

      <div className="mt-10">
        <div className="text-[11px] uppercase tracking-[0.22em] text-zinc-500 font-mono mb-3">Quick install</div>
        <CodeBlock
          title="bash"
          code={dl?.cli_install_command || "curl -sSL https://app.atglance.live/cli/install.sh | sudo bash"}
        />
      </div>

      <div className="mt-10 grid sm:grid-cols-3 gap-4">
        <Link to="/docs/quickstart" data-testid="dashboard-quickstart-link" className="lift rounded-card bg-white shadow-ag p-5">
          <BookOpen size={16} className="text-amber-500 mb-3" />
          <div className="font-medium text-zinc-100">Quickstart</div>
          <div className="text-xs text-zinc-500 mt-1">5-minute setup</div>
        </Link>
        <Link to="/docs/cli" className="lift rounded-card bg-white shadow-ag p-5">
          <TerminalIcon size={16} className="text-amber-500 mb-3" />
          <div className="font-medium text-zinc-100">CLI reference</div>
          <div className="text-xs text-zinc-500 mt-1">Every flag</div>
        </Link>
        <Link to="/docs/self-hosting" className="lift rounded-card bg-white shadow-ag p-5">
          <Server size={16} className="text-amber-500 mb-3" />
          <div className="font-medium text-zinc-100">Self-host the Console</div>
          <div className="text-xs text-zinc-500 mt-1">docker-compose</div>
        </Link>
      </div>
    </div>
  );
}
