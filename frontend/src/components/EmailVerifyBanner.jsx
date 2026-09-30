import { useEffect, useState } from "react";
import { useSearchParams } from "react-router-dom";
import { toast } from "sonner";
import { MailWarning } from "lucide-react";
import { api, formatApiError } from "../lib/api";
import { useAuth } from "../lib/auth-context";

const RESULT_MESSAGES = {
  1: ["success", "Email verified. Thanks!"],
  expired: ["error", "That verification link has expired. Send a new one below."],
  invalid: ["error", "That verification link is not valid. Send a new one below."],
};

// Shows the result of clicking the emailed link (?verified=...) and a resend banner while unverified.
export default function EmailVerifyBanner() {
  const { user, refresh } = useAuth();
  const [params, setParams] = useSearchParams();
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    const result = params.get("verified");
    if (!result) return;
    const [kind, msg] = RESULT_MESSAGES[result] || RESULT_MESSAGES.invalid;
    toast[kind](msg);
    params.delete("verified");
    setParams(params, { replace: true });
    refresh();
  }, [params, setParams, refresh]);

  if (!user || user.email_verified) return null;

  const onResend = async () => {
    setBusy(true);
    try {
      const { data } = await api.post("/auth/email/resend");
      toast.success(data.message);
    } catch (err) {
      toast.error(formatApiError(err.response?.data));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div
      className="mb-10 flex flex-col sm:flex-row sm:items-center gap-3 rounded-input bg-ag-warning-soft px-5 py-4"
      data-testid="email-verify-banner"
    >
      <MailWarning size={18} className="text-ag-warning shrink-0" />
      <div className="flex-1 text-sm text-zinc-300">
        {user.email_deliverable ? (
          <>
            Please verify <b>{user.email}</b>. We sent you a link; check your inbox and spam folder.
          </>
        ) : (
          <>Your GitHub account has no public email, so we cannot verify it. Add a public email on GitHub and sign in again.</>
        )}
      </div>
      {user.email_deliverable && (
        <button
          onClick={onResend}
          disabled={busy}
          data-testid="email-verify-resend"
          className="text-sm font-medium px-3.5 py-1.5 rounded-full bg-white text-ag-warning-text shadow-ag hover:shadow-ag-strong disabled:opacity-50"
        >
          {busy ? "Sending…" : "Resend email"}
        </button>
      )}
    </div>
  );
}
