import { useState } from "react";
import { Link } from "react-router-dom";
import { ArrowLeft, MailCheck } from "lucide-react";
import { api, ensureCsrf, formatApiError } from "../lib/api";
import { AuthShell } from "../components/AuthShell";
import { Alert, Button, Input, Label } from "../components/ag";

export default function ForgotPassword() {
  const [email, setEmail] = useState("");
  const [busy, setBusy] = useState(false);
  const [sent, setSent] = useState(null);
  const [err, setErr] = useState(null);

  const onSubmit = async (e) => {
    e.preventDefault();
    setErr(null);
    setBusy(true);
    try {
      await ensureCsrf();
      const { data } = await api.post("/auth/forgot-password", { email });
      setSent(data.message);
    } catch (e) {
      setErr(formatApiError(e.response?.data));
    } finally {
      setBusy(false);
    }
  };

  return (
    <AuthShell testId="forgot-password-page" title="Reset your password" subtitle="We'll email you a link to set a new password.">
      {sent ? (
        <Alert variant="success" icon={MailCheck} data-testid="forgot-password-sent">
          {sent} The link expires in 60 minutes; check your spam folder too.
        </Alert>
      ) : (
        <form onSubmit={onSubmit} className="space-y-4">
          <div>
            <Label htmlFor="forgot-email">Email</Label>
            <Input
              id="forgot-email"
              data-testid="forgot-email-input"
              type="email"
              required
              autoComplete="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="you@company.com"
            />
          </div>
          {err && <Alert variant="danger">{err}</Alert>}
          <Button type="submit" disabled={busy} data-testid="forgot-submit-btn" className="w-full">
            {busy ? "Sending…" : "Send reset link"}
          </Button>
        </form>
      )}

      <Link to="/login" className="mt-6 inline-flex items-center gap-1.5 text-sm font-medium text-ag-teal hover:underline">
        <ArrowLeft size={14} /> Back to sign in
      </Link>
    </AuthShell>
  );
}
