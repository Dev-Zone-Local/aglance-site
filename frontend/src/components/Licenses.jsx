import { useEffect, useState } from "react";
import { toast } from "sonner";
import { KeyRound, Plus, Trash2, AlertTriangle } from "lucide-react";
import { Link } from "react-router-dom";
import { api, API, formatApiError } from "../lib/api";
import { CodeBlock } from "./Terminal";

function fmt(iso) {
  return iso ? new Date(iso).toLocaleDateString(undefined, { year: "numeric", month: "short", day: "numeric" }) : "—";
}

// Absolute API URL the Console installer calls to confirm a licence.
function verifyUrl() {
  return new URL(`${API}/licenses/verify`, window.location.origin).toString();
}

export default function Licenses() {
  const [data, setData] = useState(null); // { plan, plan_label, limit, licenses: [] }
  const [name, setName] = useState("");
  const [busy, setBusy] = useState(false);
  const [created, setCreated] = useState(null); // { name, key } — shown once

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

  const onCreate = async (e) => {
    e.preventDefault();
    setBusy(true);
    try {
      const { data: lic } = await api.post("/licenses", { name: name.trim() });
      setCreated(lic);
      setName("");
      load();
    } catch (err) {
      toast.error(formatApiError(err.response?.data));
    } finally {
      setBusy(false);
    }
  };

  const onRevoke = async (lic) => {
    if (!window.confirm(`Revoke licence "${lic.name}"? Consoles installed with it can no longer be verified.`)) return;
    try {
      await api.delete(`/licenses/${lic.id}`);
      toast.success("Licence revoked");
      load();
    } catch (err) {
      toast.error(formatApiError(err.response?.data));
    }
  };

  return (
    <div className="mt-10 rounded-2xl border border-zinc-800 bg-[#101012] p-7" data-testid="licenses">
      <div className="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div className="flex items-center gap-2 text-[10px] uppercase tracking-[0.22em] font-mono text-amber-500">
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
        <div className="mb-6 rounded-xl border border-amber-500/40 bg-amber-500/5 p-5" data-testid="license-created">
          <div className="flex items-start gap-2 text-sm text-amber-400 mb-3">
            <AlertTriangle size={16} className="mt-0.5 shrink-0" />
            <span>
              Copy the licence key for <b>{created.name}</b> now. It is shown only once; if you lose it, revoke the
              licence and create a new one.
            </span>
          </div>
          <CodeBlock title="licence key" code={created.key} />
          <div className="mt-4 text-[11px] uppercase tracking-[0.18em] text-zinc-500 font-mono mb-2">
            Installer check (for reference)
          </div>
          <CodeBlock
            title="bash"
            code={`curl -X POST ${verifyUrl()} \\\n  -H "Accept: application/json" \\\n  -H "Authorization: Bearer ${created.key}"`}
          />
          <button
            onClick={() => setCreated(null)}
            className="mt-4 text-sm text-zinc-400 hover:text-zinc-100"
            data-testid="license-dismiss"
          >
            I've saved it
          </button>
        </div>
      )}

      {atLimit ? (
        <div className="mb-6 rounded-md border border-zinc-800 bg-zinc-950 px-4 py-3 text-sm text-zinc-400" data-testid="license-limit">
          Your {data.plan_label} plan includes {limit} {limit === 1 ? "licence" : "licences"}. Revoke the existing one to
          create a new licence, or{" "}
          <Link to="/pricing" className="text-amber-500 hover:text-amber-400">
            upgrade your plan
          </Link>
          .
        </div>
      ) : (
        <form onSubmit={onCreate} className="flex flex-col sm:flex-row gap-3 mb-6">
          <input
            value={name}
            onChange={(e) => setName(e.target.value)}
            required
            maxLength={100}
            placeholder="Licence name, e.g. prod-console"
            data-testid="license-name"
            className="flex-1 bg-zinc-950 border border-zinc-800 rounded-md px-3 py-2.5 text-sm text-zinc-100 placeholder:text-zinc-600 focus:outline-none focus:border-amber-500/60"
          />
          <button
            type="submit"
            disabled={busy || !name.trim()}
            data-testid="license-create"
            className="inline-flex items-center justify-center gap-2 bg-amber-500 text-zinc-950 font-medium px-4 py-2.5 rounded-md hover:bg-amber-400 transition-colors disabled:opacity-50"
          >
            <Plus size={14} /> Create licence
          </button>
        </form>
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
                <th className="py-2 pr-4 font-medium">Created</th>
                <th className="py-2 pr-4 font-medium">Last verified</th>
                <th className="py-2" />
              </tr>
            </thead>
            <tbody>
              {licenses.map((lic) => (
                <tr key={lic.id} className="border-b border-zinc-900 text-zinc-300">
                  <td className="py-2.5 pr-4 font-mono">{lic.name}</td>
                  <td className="py-2.5 pr-4">{fmt(lic.created_at)}</td>
                  <td className="py-2.5 pr-4">{lic.last_used_at ? fmt(lic.last_used_at) : "Never"}</td>
                  <td className="py-2.5 text-right">
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
