import { useEffect, useState } from "react";
import { toast } from "sonner";
import { KeyRound, Plus, Trash2, AlertTriangle, Eye } from "lucide-react";
import { Link } from "react-router-dom";
import { api, formatApiError } from "../lib/api";
import { CodeBlock } from "./Terminal";
import { useAuth } from "../lib/auth-context";
import RevokeLicense from "./RevokeLicense";

// Status colours from design-doc §2.4 (soft background + strong text).
const STATUS_STYLES = {
  unverified: [
    "bg-ag-surface text-ag-subtle",
    "bg-ag-muted",
    "Pending",
    "Enter the 5-digit code we emailed you to activate this licence.",
  ],
  under_review: [
    "bg-ag-warning-soft text-ag-warning",
    "bg-ag-warning",
    "Under review",
    "An AtGlance admin must approve this licence before the Management Console can use it.",
  ],
  ready: [
    "bg-ag-info-soft text-ag-info",
    "bg-ag-info",
    "Ready",
    "Active. Enter the key in the Management Console installer.",
  ],
  in_use: ["bg-ag-success-soft text-ag-success", "bg-ag-success", "In Use", undefined],
  expired: [
    "bg-red-50 text-red-600",
    "bg-red-500",
    "Expired",
    "This licence has expired. Create a new licence for the Management Console.",
  ],
};

function StatusBadge({ license }) {
  const [cls, dot, label, title] = STATUS_STYLES[license.status] || STATUS_STYLES.unverified;
  return (
    <span
      title={title}
      className={`inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg px-2.5 py-1 text-xs font-medium ${cls}`}
      data-testid={`license-status-${license.id}`}
    >
      <span className={`h-1.5 w-1.5 rounded-full ${dot}`} /> {label}
    </span>
  );
}

// "5 minutes ago", "3 days ago" — used for the console's last heartbeat.
function timeAgo(iso) {
  const secs = Math.max(0, (Date.now() - new Date(iso).getTime()) / 1000);
  const units = [
    ["year", 31536000],
    ["month", 2592000],
    ["day", 86400],
    ["hour", 3600],
    ["minute", 60],
  ];
  for (const [unit, size] of units) {
    if (secs >= size) return new Intl.RelativeTimeFormat(undefined, { numeric: "auto" }).format(-Math.floor(secs / size), unit);
  }
  return "just now";
}

function fmt(iso) {
  return iso ? new Date(iso).toLocaleDateString(undefined, { year: "numeric", month: "short", day: "numeric" }) : "—";
}

export default function Licenses() {
  const [data, setData] = useState(null); // { plan, plan_label, limit, licenses: [] }
  const [name, setName] = useState("");
  const [busy, setBusy] = useState(false);
  const [created, setCreated] = useState(null); // { name, key } — shown once
  const [pending, setPending] = useState(null); // { id, name, message } request waiting for the emailed code
  const [code, setCode] = useState("");
  const [revoking, setRevoking] = useState(null); // licence being revoked
  const { refresh } = useAuth();

  const load = () =>
    api
      .get("/licenses")
      .then((r) => setData(r.data))
      .catch(() => setData({ licenses: [] }));

  useEffect(() => {
    load();
  }, []);

  const licenses = data?.licenses || [];
  const limit = data?.limit ?? null; // null = unlimited
  const atLimit = limit !== null && licenses.length >= limit;

  // Save the request (visible to admins at once), show the key, and email a 5-digit verification code.
  const onRequest = async (e) => {
    e.preventDefault();
    setBusy(true);
    try {
      const { data: lic } = await api.post("/licenses", { name: name.trim() });
      setCreated(lic);
      setPending({ id: lic.id, name: lic.name, message: lic.message });
      setName("");
      setCode("");
      load();
    } catch (err) {
      toast.error(formatApiError(err.response?.data));
    } finally {
      setBusy(false);
    }
  };

  // The emailed code activates the licence (and verifies the user's email address).
  const onConfirm = async (e) => {
    e.preventDefault();
    setBusy(true);
    try {
      const { data: res } = await api.post(`/licenses/${pending.id}/confirm`, { code });
      toast.success(res.message);
      setPending(null);
      setCode("");
      load();
      refresh(); // the code also verifies the email address
    } catch (err) {
      toast.error(formatApiError(err.response?.data));
    } finally {
      setBusy(false);
    }
  };

  const resendCode = async () => {
    setBusy(true);
    try {
      const { data: res } = await api.post(`/licenses/${pending.id}/confirm-code`);
      setPending((p) => ({ ...p, message: res.message }));
      toast.success(res.message);
    } catch (err) {
      toast.error(formatApiError(err.response?.data));
      load();
    } finally {
      setBusy(false);
    }
  };

  // Resume an unconfirmed request from the list (e.g. after a reload).
  const onEnterCode = (lic) => {
    setPending({ id: lic.id, name: lic.name, message: "Enter the 5-digit code we emailed you." });
    setCode("");
    window.scrollTo({ top: 0, behavior: "smooth" });
  };


  const onRevoke = (lic) => setRevoking(lic);

  // Re-show a key; the API allows this for 30 minutes after creation.
  const onShowKey = async (lic) => {
    try {
      const { data: res } = await api.get(`/licenses/${lic.id}/key`);
      setCreated({ ...lic, key: res.key, key_visible_until: res.key_visible_until });
      window.scrollTo({ top: 0, behavior: "smooth" });
    } catch (err) {
      toast.error(formatApiError(err.response?.data));
      load();
    }
  };

  return (
    <div className="mt-10 rounded-card bg-white shadow-ag p-7" data-testid="licenses">
      <div className="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div className="flex items-center gap-2 text-xs font-medium text-ag-teal">
          <KeyRound size={14} /> Management Console licences
        </div>
        {data?.plan_label && (
          <div className="text-xs font-mono text-zinc-400" data-testid="license-usage">
            {data.plan_label} plan · {licenses.length} / {limit === null ? "unlimited" : limit} used
          </div>
        )}
      </div>
      <h3 className="text-2xl font-semibold text-zinc-100 mb-1">Licence for the Management Console</h3>
      <p className="text-sm text-zinc-400 mb-6 max-w-2xl">
        The AtGlance Management Console installer asks for a licence key to confirm the installation belongs to your
        account. Create one here and paste it when the installer prompts for it. Licences stay valid until you revoke
        them.
      </p>

      {created && (
        <div className="mb-6 rounded-input bg-ag-warning-soft p-5" data-testid="license-created">
          <div className="flex items-start gap-2 text-sm text-ag-warning-text mb-3">
            <AlertTriangle size={16} className="mt-0.5 shrink-0" />
            <span>
              Copy the licence key for <b>{created.name}</b> and keep it safe.
              {created.key_visible_until
                ? ` You can view it again from the list until ${new Date(created.key_visible_until).toLocaleTimeString()} (30 minutes after creation). After that it cannot be shown again; if you lose it, revoke the licence and create a new one.`
                : " If you lose it, revoke the licence and create a new one."}
            </span>
          </div>
          {created.requires_approval && !created.approved && (
            <div className="mb-3 text-sm text-zinc-300" data-testid="license-under-review-note">
              After you enter the emailed code, an AtGlance admin must also approve this licence before the Management
              Console can use it. We will email you when it is approved.
            </div>
          )}
          <CodeBlock title="licence key" code={created.key} />
          <button
            onClick={() => setCreated(null)}
            className="mt-4 text-sm text-zinc-400 hover:text-zinc-100"
            data-testid="license-dismiss"
          >
            I've saved it
          </button>
        </div>
      )}

      {pending ? (
          <form onSubmit={onConfirm} className="mb-6 rounded-xl border border-zinc-800 bg-zinc-950 p-5" data-testid="license-code-form">
            <div className="text-sm text-zinc-300 mb-1">
              Activate licence <b className="font-mono">{pending.name}</b> — it works once you enter the emailed code.
            </div>
            <div className="text-sm text-zinc-400 mb-3">{pending.message} The code expires in 10 minutes.</div>
            <div className="flex flex-col sm:flex-row gap-3">
              <input
                value={code}
                onChange={(e) => setCode(e.target.value.replace(/\D/g, "").slice(0, 5))}
                required
                inputMode="numeric"
                autoComplete="one-time-code"
                autoFocus
                placeholder="5-digit code"
                data-testid="license-code"
                className="sm:w-44 rounded-input border border-transparent bg-ag-surface px-3.5 py-[11px] text-lg tracking-[0.4em] font-mono text-zinc-100 placeholder:text-zinc-600 placeholder:tracking-normal placeholder:text-sm focus:outline-none focus:border-ag-mint focus:bg-white focus:shadow-ag-focus"
              />
              <button
                type="submit"
                disabled={busy || code.length !== 5}
                data-testid="license-create"
                className="inline-flex items-center justify-center gap-2 bg-ag-gradient text-ag-ink font-medium px-4 py-2.5 rounded-full hover:bg-ag-gradient-hover hover:shadow-ag-glow transition-colors disabled:opacity-50"
              >
                Activate
              </button>
              <button type="button" onClick={resendCode} disabled={busy} className="text-sm text-zinc-400 hover:text-zinc-100 px-2">
                Resend code
              </button>
            </div>
          </form>
      ) : atLimit ? (
        <div className="mb-6 rounded-md border border-zinc-800 bg-zinc-950 px-4 py-3 text-sm text-zinc-400" data-testid="license-limit">
          Your {data.plan_label} plan includes {limit} {limit === 1 ? "licence" : "licences"}. Revoke the existing one to
          create a new licence, or{" "}
          <Link to="/pricing" className="text-amber-500 hover:text-amber-400">
            upgrade your plan
          </Link>
          .
        </div>
      ) : (
          <form onSubmit={onRequest} className="flex flex-col sm:flex-row gap-3 mb-6">
            <input
              value={name}
              onChange={(e) => setName(e.target.value)}
              required
              maxLength={100}
              placeholder="Licence name, e.g. prod-console"
              data-testid="license-name"
              className="flex-1 rounded-input border border-transparent bg-ag-surface px-3.5 py-[11px] text-sm text-zinc-100 placeholder:text-zinc-600 focus:outline-none focus:border-ag-mint focus:bg-white focus:shadow-ag-focus"
            />
            <button
              type="submit"
              disabled={busy || !name.trim()}
              data-testid="license-send-code"
              className="inline-flex items-center justify-center gap-2 bg-ag-gradient text-ag-ink font-medium px-4 py-2.5 rounded-full hover:bg-ag-gradient-hover hover:shadow-ag-glow transition-colors disabled:opacity-50"
            >
              <Plus size={14} /> {busy ? "Requesting…" : "Create licence"}
            </button>
          </form>
      )}

      {revoking && (
        <RevokeLicense
          key={revoking.id}
          license={revoking}
          onCancel={() => setRevoking(null)}
          onDone={() => {
            setRevoking(null);
            load();
          }}
        />
      )}

      {data === null ? (
        <div className="text-sm text-zinc-500 font-mono">Loading…</div>
      ) : licenses.length === 0 ? (
        <div className="text-sm text-zinc-500">No licences yet.</div>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-sm" data-testid="license-list">
            <thead>
              <tr className="text-left text-[11px] uppercase tracking-[0.16em] text-zinc-500 border-b border-zinc-800">
                <th className="py-2 pr-4 font-medium">Name</th>
                <th className="py-2 pr-4 font-medium">Status</th>
                <th className="py-2 pr-4 font-medium">Organization</th>
                <th className="py-2 pr-4 font-medium">Created</th>
                <th className="py-2 pr-4 font-medium">Expires</th>
                <th className="py-2 pr-4 font-medium">Last seen</th>
                <th className="py-2" />
              </tr>
            </thead>
            <tbody>
              {licenses.map((lic) => (
                <tr key={lic.id} className="border-b border-zinc-900 text-zinc-300">
                  <td className="py-2.5 pr-4 font-mono">{lic.name}</td>
                  <td className="py-2.5 pr-4">
                    <StatusBadge license={lic} />
                  </td>
                  <td className="py-2.5 pr-4 text-zinc-400">
                    {lic.console ? (
                      <span title={`In use since ${fmt(lic.console.activated_at)}`}>
                        <span className="text-zinc-200">{lic.console.org_name || "—"}</span>
                        {lic.console.version && <span className="text-zinc-500"> · v{lic.console.version}</span>}
                      </span>
                    ) : (
                      "—"
                    )}
                  </td>
                  <td className="py-2.5 pr-4">{fmt(lic.created_at)}</td>
                  <td className={`py-2.5 pr-4 ${lic.expired ? "text-red-500" : ""}`} data-testid={`license-expires-${lic.id}`}>
                    {lic.expires_at ? fmt(lic.expires_at) : "Never"}
                  </td>
                  <td className="py-2.5 pr-4" title={lic.console?.last_seen_at || ""}>
                    {lic.console?.last_seen_at ? timeAgo(lic.console.last_seen_at) : "Never"}
                  </td>
                  <td className="py-2.5 text-right whitespace-nowrap">
                    {lic.awaiting_code && (
                      <button
                        onClick={() => onEnterCode(lic)}
                        data-testid={`license-enter-code-${lic.id}`}
                        className="inline-flex items-center gap-1.5 text-amber-500 hover:text-amber-400 text-xs mr-4"
                      >
                        <KeyRound size={13} /> Enter code
                      </button>
                    )}
                    {lic.key_visible_until && new Date(lic.key_visible_until) > new Date() && (
                      <button
                        onClick={() => onShowKey(lic)}
                        data-testid={`license-show-key-${lic.id}`}
                        title={`Viewable until ${new Date(lic.key_visible_until).toLocaleTimeString()}`}
                        className="inline-flex items-center gap-1.5 text-amber-500 hover:text-amber-400 text-xs mr-4"
                      >
                        <Eye size={13} /> Show key
                      </button>
                    )}
                    <button
                      onClick={() => onRevoke(lic)}
                      data-testid={`license-revoke-${lic.id}`}
                      className="inline-flex items-center gap-1.5 text-zinc-500 hover:text-red-400 text-xs"
                    >
                      <Trash2 size={13} /> Revoke
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
