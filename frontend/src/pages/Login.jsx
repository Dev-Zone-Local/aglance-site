import { useState } from "react";
import { Link, useNavigate, useLocation } from "react-router-dom";
import { useAuth, goToAccountHome, useGithubEnabled } from "../lib/auth-context";
import { api, formatApiError } from "../lib/api";
import { ArrowRight } from "lucide-react";
import { AuthShell, GithubButton, OrDivider } from "../components/AuthShell";
import { Alert, Button, Input, Label } from "../components/ag";

export default function Login() {
  const { login } = useAuth();
  const nav = useNavigate();
  const githubEnabled = useGithubEnabled();
  const loc = useLocation();
  const [email, setEmail] = useState("");
  const [pw, setPw] = useState("");
  const [err, setErr] = useState(null);
  const [busy, setBusy] = useState(false);

  const onSubmit = async (e) => {
    e.preventDefault();
    setErr(null); setBusy(true);
    try {
      const u = await login(email, pw);
      if (loc.state?.from) nav(loc.state.from, { replace: true });
      else goToAccountHome(u, nav);
    } catch (e) {
      setErr(formatApiError(e.response?.data));
    } finally { setBusy(false); }
  };

  const onGithub = async () => {
    try {
      const { data } = await api.get("/auth/github/start");
      window.location.href = data.auth_url;
    } catch (e) {
      setErr(formatApiError(e.response?.data));
    }
  };

  return (
    <AuthShell testId="login-page" title="Welcome back" subtitle="Sign in to access your downloads and licences.">
      {githubEnabled && (
        <>
          <GithubButton onClick={onGithub}>Continue with GitHub</GithubButton>
          <OrDivider />
        </>
      )}

      <form onSubmit={onSubmit} className="space-y-4">
        <div>
          <Label htmlFor="login-email">Email</Label>
          <Input
            id="login-email"
            data-testid="login-email-input"
            type="email"
            required
            autoComplete="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder="you@company.com"
          />
        </div>
        <div>
          <Label htmlFor="login-password">Password</Label>
          <Input
            id="login-password"
            data-testid="login-password-input"
            type="password"
            required
            autoComplete="current-password"
            value={pw}
            onChange={(e) => setPw(e.target.value)}
            placeholder="••••••••"
          />
        </div>
        {err && <Alert variant="danger" data-testid="login-error">{err}</Alert>}
        <Button type="submit" disabled={busy} data-testid="login-submit-btn" className="w-full">
          {busy ? "Signing in…" : <>Sign in <ArrowRight size={14} /></>}
        </Button>
      </form>

      <div className="mt-6 text-sm text-ag-subtle">
        New to AtGlance?{" "}
        <Link to="/register" className="font-medium text-ag-teal hover:underline" data-testid="login-to-register">
          Create a free account
        </Link>
      </div>
    </AuthShell>
  );
}
