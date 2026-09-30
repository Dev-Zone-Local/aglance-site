import { useState } from "react";
import { Link, useNavigate, useSearchParams } from "react-router-dom";
import { toast } from "sonner";
import { api, ensureCsrf, formatApiError } from "../lib/api";
import { AuthShell } from "../components/AuthShell";
import { Alert, Button, Input, Label } from "../components/ag";

// Target of the link in the password reset email: /reset-password?token=...&email=...
export default function ResetPassword() {
  const [params] = useSearchParams();
  const nav = useNavigate();
  const token = params.get("token") || "";
  const email = params.get("email") || "";
  const [pw, setPw] = useState("");
  const [pw2, setPw2] = useState("");
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState(null);

  const onSubmit = async (e) => {
    e.preventDefault();
    setErr(null);
    if (pw !== pw2) {
      setErr("Passwords do not match");
      return;
    }
    setBusy(true);
    try {
      await ensureCsrf();
      const { data } = await api.post("/auth/reset-password", { token, email, password: pw, password_confirmation: pw2 });
      toast.success(data.message);
      nav("/login", { replace: true });
    } catch (e) {
      setErr(formatApiError(e.response?.data));
    } finally {
      setBusy(false);
    }
  };

  if (!token || !email) {
    return (
      <AuthShell testId="reset-password-page" title="Reset link incomplete">
        <Alert variant="danger">This reset link is missing information. Request a new one.</Alert>
        <Button to="/forgot-password" className="mt-5 w-full">Request a new link</Button>
      </AuthShell>
    );
  }

  return (
    <AuthShell testId="reset-password-page" title="Choose a new password" subtitle={`For ${email}`}>
      <form onSubmit={onSubmit} className="space-y-4">
        <div>
          <Label htmlFor="reset-password">New password</Label>
          <Input
            id="reset-password"
            data-testid="reset-password-input"
            type="password"
            required
            minLength={6}
            autoComplete="new-password"
            value={pw}
            onChange={(e) => setPw(e.target.value)}
            placeholder="At least 6 characters"
          />
        </div>
        <div>
          <Label htmlFor="reset-password2">Confirm new password</Label>
          <Input
            id="reset-password2"
            data-testid="reset-password-confirm-input"
            type="password"
            required
            minLength={6}
            autoComplete="new-password"
            value={pw2}
            onChange={(e) => setPw2(e.target.value)}
            placeholder="Repeat the password"
          />
        </div>
        {err && (
          <Alert variant="danger" data-testid="reset-password-error">
            {err}{" "}
            {/expired|not valid|invalid/i.test(err) && (
              <Link to="/forgot-password" className="font-medium underline">
                Request a new link
              </Link>
            )}
          </Alert>
        )}
        <Button type="submit" disabled={busy} data-testid="reset-submit-btn" className="w-full">
          {busy ? "Saving…" : "Set new password"}
        </Button>
      </form>
    </AuthShell>
  );
}
