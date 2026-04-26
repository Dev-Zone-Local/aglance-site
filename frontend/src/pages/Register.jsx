import { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { useAuth } from "../lib/auth-context";
import { api, formatApiError } from "../lib/api";
import { Github, ArrowRight, Terminal as TerminalIcon } from "lucide-react";

export default function Register() {
  const { register } = useAuth();
  const nav = useNavigate();
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [pw, setPw] = useState("");
  const [err, setErr] = useState(null);
  const [busy, setBusy] = useState(false);

  const onSubmit = async (e) => {
    e.preventDefault();
    setErr(null); setBusy(true);
    try {
      const u = await register(email, pw, name);
      nav(u.role === "admin" ? "/admin" : "/dashboard", { replace: true });
    } catch (e) {
      setErr(formatApiError(e.response?.data?.detail) || e.message);
    } finally { setBusy(false); }
  };

  const onGithub = async () => {
    try {
      const { data } = await api.get("/auth/github/start");
      window.location.href = data.auth_url;
    } catch (e) {
      setErr(formatApiError(e.response?.data?.detail) || "GitHub OAuth not available");
    }
  };

  return (
    <div className="min-h-[calc(100vh-4rem)] flex items-center justify-center px-5 py-12" data-testid="register-page">
      <div className="w-full max-w-md">
        <Link to="/" className="inline-flex items-center gap-2.5 mb-8">
          <div className="w-8 h-8 rounded-md bg-amber-500/10 border border-amber-500/40 flex items-center justify-center">
            <TerminalIcon size={15} className="text-amber-500" />
          </div>
          <span className="font-semibold tracking-tight text-zinc-100">AtGlance</span>
        </Link>
        <h1 className="text-3xl font-semibold tracking-tighter text-zinc-50 mb-2">Create your free account</h1>
        <p className="text-zinc-500 mb-8 text-sm">Get access to the AtGlance CLI and self-hosted Console downloads.</p>

        <button
          onClick={onGithub}
          data-testid="github-sso-btn"
          className="lift w-full inline-flex items-center justify-center gap-2.5 px-4 py-2.5 rounded-md border border-zinc-800 bg-zinc-950 text-zinc-200 hover:bg-zinc-900 mb-5"
        >
          <Github size={16} /> Sign up with GitHub
        </button>

        <div className="flex items-center gap-3 mb-5">
          <div className="flex-1 h-px bg-zinc-900" />
          <span className="text-[11px] uppercase tracking-[0.2em] text-zinc-500 font-mono">or</span>
          <div className="flex-1 h-px bg-zinc-900" />
        </div>

        <form onSubmit={onSubmit} className="space-y-3">
          <div>
            <label className="text-[11px] uppercase tracking-[0.18em] text-zinc-500 font-mono">Name</label>
            <input data-testid="register-name-input" type="text" value={name} onChange={(e) => setName(e.target.value)}
              className="mt-1 w-full px-3 py-2.5 bg-zinc-950 border border-zinc-800 rounded-md text-zinc-100 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/30"
              placeholder="Jane Doe" />
          </div>
          <div>
            <label className="text-[11px] uppercase tracking-[0.18em] text-zinc-500 font-mono">Email</label>
            <input data-testid="register-email-input" type="email" required value={email} onChange={(e) => setEmail(e.target.value)}
              className="mt-1 w-full px-3 py-2.5 bg-zinc-950 border border-zinc-800 rounded-md text-zinc-100 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/30"
              placeholder="you@company.com" />
          </div>
          <div>
            <label className="text-[11px] uppercase tracking-[0.18em] text-zinc-500 font-mono">Password</label>
            <input data-testid="register-password-input" type="password" required minLength={6} value={pw} onChange={(e) => setPw(e.target.value)}
              className="mt-1 w-full px-3 py-2.5 bg-zinc-950 border border-zinc-800 rounded-md text-zinc-100 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/30"
              placeholder="At least 6 characters" />
          </div>
          {err && <div data-testid="register-error" className="text-sm text-red-400">{err}</div>}
          <button type="submit" disabled={busy} data-testid="register-submit-btn"
            className="w-full inline-flex items-center justify-center gap-2 bg-amber-500 text-zinc-950 font-medium py-2.5 rounded-md hover:bg-amber-400 disabled:opacity-60">
            {busy ? "Creating account…" : <>Create account <ArrowRight size={14} /></>}
          </button>
        </form>

        <div className="text-sm text-zinc-500 mt-6">
          Already have an account? <Link to="/login" className="text-amber-500" data-testid="register-to-login">Sign in</Link>
        </div>
      </div>
    </div>
  );
}
