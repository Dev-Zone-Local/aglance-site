import { Link } from "react-router-dom";
import { Terminal as TerminalIcon } from "lucide-react";

export function Footer() {
  return (
    <footer className="border-t border-zinc-900 bg-[#08080a] mt-32" data-testid="site-footer">
      <div className="max-w-7xl mx-auto px-5 sm:px-8 py-14 grid grid-cols-2 md:grid-cols-5 gap-8">
        <div className="col-span-2">
          <Link to="/" className="flex items-center gap-2.5 mb-4">
            <div className="w-8 h-8 rounded-md bg-amber-500/10 border border-amber-500/40 flex items-center justify-center">
              <TerminalIcon size={15} className="text-amber-500" />
            </div>
            <span className="font-semibold tracking-tight text-zinc-100">AtGlance</span>
          </Link>
          <p className="text-sm text-zinc-500 leading-relaxed max-w-xs">
            A self-hosted operations platform for SREs. CLI + Management Console, inside your boundary.
          </p>
        </div>
        <div>
          <div className="text-[11px] uppercase tracking-[0.2em] text-zinc-500 font-mono mb-3">Product</div>
          <ul className="space-y-2 text-sm">
            <li><Link to="/product" className="text-zinc-400 hover:text-amber-500">Overview</Link></li>
            <li><Link to="/cli" className="text-zinc-400 hover:text-amber-500">CLI</Link></li>
            <li><Link to="/console" className="text-zinc-400 hover:text-amber-500">Console</Link></li>
            <li><Link to="/architecture" className="text-zinc-400 hover:text-amber-500">Architecture</Link></li>
            <li><Link to="/pricing" className="text-zinc-400 hover:text-amber-500">Pricing</Link></li>
          </ul>
        </div>
        <div>
          <div className="text-[11px] uppercase tracking-[0.2em] text-zinc-500 font-mono mb-3">Resources</div>
          <ul className="space-y-2 text-sm">
            <li><Link to="/docs" className="text-zinc-400 hover:text-amber-500">Docs</Link></li>
            <li><Link to="/docs/quickstart" className="text-zinc-400 hover:text-amber-500">Quickstart</Link></li>
            <li><Link to="/docs/cli" className="text-zinc-400 hover:text-amber-500">CLI reference</Link></li>
            {/* <li><Link to="/docs/api" className="text-zinc-400 hover:text-amber-500">API reference</Link></li> */}
            <li><Link to="/security" className="text-zinc-400 hover:text-amber-500">Security</Link></li>
            <li><Link to="/faq" className="text-zinc-400 hover:text-amber-500">FAQ</Link></li>
          </ul>
        </div>
        <div>
          <div className="text-[11px] uppercase tracking-[0.2em] text-zinc-500 font-mono mb-3">Company</div>
          <ul className="space-y-2 text-sm">
            <li><Link to="/about" className="text-zinc-400 hover:text-amber-500">About</Link></li>
            <li><Link to="/contact" className="text-zinc-400 hover:text-amber-500">Contact</Link></li>
            <li><Link to="/terms" className="text-zinc-400 hover:text-amber-500">Terms</Link></li>
            <li><Link to="/privacy" className="text-zinc-400 hover:text-amber-500">Privacy</Link></li>
          </ul>
        </div>
      </div>
      <div className="border-t border-zinc-900">
        <div className="max-w-7xl mx-auto px-5 sm:px-8 py-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
          <div className="text-xs text-zinc-600 font-mono">© 2026 AtGlance. Self-hosted, by design.</div>
          <div className="text-xs text-zinc-600 font-mono">CLI + Rest API + Application + Database + Cache</div>
        </div>
      </div>
    </footer>
  );
}
