import { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { useAuth, goToAccountHome, useGithubEnabled } from "../lib/auth-context";
import { api, formatApiError } from "../lib/api";
import { ArrowRight } from "lucide-react";
import { AuthShell, GithubButton, OrDivider } from "../components/AuthShell";
import { Alert, Button, Input, Label } from "../components/ag";

export default function Register() {
  const { register } = useAuth();
  const nav = useNavigate();
  const githubEnabled = useGithubEnabled();
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [pw, setPw] = useState("");
  const [pw2, setPw2] = useState("");
  const [err, setErr] = useState(null);
  const [busy, setBusy] = useState(false);

  const onSubmit = async (e) => {
    e.preventDefault();
    setErr(null); setBusy(true);
    try {
      if (pw !== pw2) {
        setErr("Passwords do not match");
        return;
      }
      const u = await register(email, pw, pw2, name);
      goToAccountHome(u, nav);
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
    <AuthShell
      testId="register-page"
      title="Create your free account"
      subtitle="Get the AtGlance CLI and self-hosted Console downloads."
    >
      {githubEnabled && (
        <>
          <GithubButton onClick={onGithub}>Sign up with GitHub</GithubButton>
          <OrDivider />
        </>
      )}

      <form onSubmit={onSubmit} className="space-y-4">
        <div>
          <Label htmlFor="reg-name">Name</Label>
          <Input id="reg-name" data-testid="register-name-input" type="text" value={name} onChange={(e) => setName(e.target.value)} placeholder="Jane Doe" autoComplete="name" />
        </div>
        <div>
          <Label htmlFor="reg-email">Email</Label>
          <Input id="reg-email" data-testid="register-email-input" type="email" value={email} onChange={(e) => setEmail(e.target.value)} placeholder="you@company.com" required autoComplete="email" />
        </div>
        <div>
          <Label htmlFor="reg-password">Password</Label>
          <Input id="reg-password" data-testid="register-password-input" type="password" value={pw} onChange={(e) => setPw(e.target.value)} placeholder="At least 6 characters" required minLength={6} autoComplete="new-password" />
        </div>
        <div>
          <Label htmlFor="reg-password2">Confirm password</Label>
          <Input id="reg-password2" data-testid="register-password-confirm-input" type="password" value={pw2} onChange={(e) => setPw2(e.target.value)} placeholder="Repeat your password" required minLength={6} autoComplete="new-password" />
        </div>
        {err && <Alert variant="danger" data-testid="register-error">{err}</Alert>}
        <Button type="submit" disabled={busy} data-testid="register-submit-btn" className="w-full">
          {busy ? "Creating account…" : <>Create account <ArrowRight size={14} /></>}
        </Button>
      </form>

      <div className="mt-6 text-sm text-ag-subtle">
        Already have an account?{" "}
        <Link to="/login" className="font-medium text-ag-teal hover:underline" data-testid="register-to-login">
          Sign in
        </Link>
      </div>
    </AuthShell>
  );
}
