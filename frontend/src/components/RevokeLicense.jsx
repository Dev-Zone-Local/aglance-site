import { useState } from "react";
import { toast } from "sonner";
import { Trash2 } from "lucide-react";
import { api, formatApiError } from "../lib/api";
import { useAuth } from "../lib/auth-context";

const inputCls =
  "bg-zinc-950 border border-zinc-800 rounded-md px-3 py-2.5 text-sm text-zinc-100 placeholder:text-zinc-600 focus:outline-none focus:border-red-500/60";

// Confirms revoking a licence with the account password or a 5-digit emailed code.
export default function RevokeLicense({ license, onDone, onCancel }) {
  const { user } = useAuth();
  const canUsePassword = !!user?.has_password;
  const [mode, setMode] = useState(canUsePassword ? "password" : "code");
  const [password, setPassword] = useState("");
  const [code, setCode] = useState("");
  const [codeSent, setCodeSent] = useState(null);
  const [busy, setBusy] = useState(false);

  const sendCode = async () => {
    setBusy(true);
    try {
      const { data } = await api.post(`/licenses/${license.id}/revoke-code`);
      setCodeSent(data.message);
      setMode("code");
      setCode("");
    } catch (err) {
      toast.error(formatApiError(err.response?.data));
    } finally {
      setBusy(false);
    }
  };

  const onSubmit = async (e) => {
    e.preventDefault();
    setBusy(true);
    try {
      await api.delete(`/licenses/${license.id}`, { data: mode === "password" ? { password } : { code } });
      toast.success(`Licence "${license.name}" revoked`);
      onDone();
    } catch (err) {
      toast.error(formatApiError(err.response?.data));
    } finally {
      setBusy(false);
    }
  };

  const ready = mode === "password" ? password.length > 0 : code.length === 5;

  return (
    <form
      onSubmit={onSubmit}
      className="mb-6 rounded-xl border border-red-500/40 bg-red-500/5 p-5"
      data-testid="license-revoke-panel"
    >
      <div className="text-sm text-zinc-200 mb-1">
        Revoke licence <b className="font-mono">{license.name}</b>?
      </div>
      <div className="text-sm text-zinc-400 mb-4">
        Consoles installed with it can no longer be verified. This cannot be undone.{" "}
        {mode === "password" ? "Enter your password to confirm." : "Enter the 5-digit code we email you to confirm."}
      </div>

      {mode === "password" ? (
        <input
          type="password"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          autoFocus
          autoComplete="current-password"
          placeholder="Your password"
          data-testid="license-revoke-password"
          className={`${inputCls} w-full sm:w-72`}
        />
      ) : codeSent ? (
        <>
          <div className="text-sm text-zinc-300 mb-2">{codeSent} The code expires in 10 minutes.</div>
          <input
            value={code}
            onChange={(e) => setCode(e.target.value.replace(/\D/g, "").slice(0, 5))}
            inputMode="numeric"
            autoComplete="one-time-code"
            autoFocus
            placeholder="5-digit code"
            data-testid="license-revoke-code"
            className={`${inputCls} sm:w-44 text-lg tracking-[0.4em] font-mono placeholder:tracking-normal placeholder:text-sm`}
          />
        </>
      ) : (
        <button
          type="button"
          onClick={sendCode}
          disabled={busy}
          data-testid="license-revoke-send-code"
          className="text-sm font-medium px-3.5 py-2 rounded-md border border-zinc-700 text-zinc-200 hover:bg-zinc-900 disabled:opacity-50"
        >
          {busy ? "Sending…" : "Email me a code"}
        </button>
      )}

      <div className="mt-4 flex flex-wrap items-center gap-3">
        {(mode === "password" || codeSent) && (
          <button
            type="submit"
            disabled={busy || !ready}
            data-testid="license-revoke-confirm"
            className="inline-flex items-center gap-2 bg-red-500 text-white font-medium px-4 py-2 rounded-md hover:bg-red-400 disabled:opacity-50"
          >
            <Trash2 size={14} /> Revoke licence
          </button>
        )}
        {mode === "password" ? (
          <button type="button" onClick={sendCode} disabled={busy} className="text-sm text-zinc-400 hover:text-zinc-100">
            Email me a code instead
          </button>
        ) : (
          <>
            {codeSent && (
              <button type="button" onClick={sendCode} disabled={busy} className="text-sm text-zinc-400 hover:text-zinc-100">
                Resend code
              </button>
            )}
            {canUsePassword && (
              <button type="button" onClick={() => setMode("password")} className="text-sm text-zinc-400 hover:text-zinc-100">
                Use password instead
              </button>
            )}
          </>
        )}
        <button type="button" onClick={onCancel} className="text-sm text-zinc-500 hover:text-zinc-300">
          Cancel
        </button>
      </div>
    </form>
  );
}
