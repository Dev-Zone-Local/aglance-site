import { useEffect, useState } from "react";
import { toast } from "sonner";
import { KeyRound, Plus, Trash2, AlertTriangle } from "lucide-react";
import { api, API, formatApiError } from "../lib/api";
import { CodeBlock } from "./Terminal";

function fmt(iso) {
  return iso ? new Date(iso).toLocaleDateString(undefined, { year: "numeric", month: "short", day: "numeric" }) : "—";
}

// Absolute API URL the Console installer should call to confirm a token.
function verifyUrl() {
  return new URL(`${API}/install-tokens/verify`, window.location.origin).toString();
}

export default function InstallTokens() {
  const [tokens, setTokens] = useState(null);
  const [name, setName] = useState("");
  const [busy, setBusy] = useState(false);
  const [created, setCreated] = useState(null); // { name, token } — shown once

  const load = () =>
    api
      .get("/install-tokens")
      .then((r) => setTokens(r.data))
      .catch(() => setTokens([]));

  useEffect(() => {
    load();
  }, []);

  const onCreate = async (e) => {
    e.preventDefault();
    setBusy(true);
    try {
      const { data } = await api.post("/install-tokens", { name: name.trim() });
      setCreated(data);
      setName("");
      load();
    } catch (err) {
      toast.error(formatApiError(err.response?.data));
    } finally {
      setBusy(false);
    }
  };

  const onRevoke = async (t) => {
    if (!window.confirm(`Revoke "${t.name}"? Installers using it will stop working.`)) return;
    try {
      await api.delete(`/install-tokens/${t.id}`);
      toast.success("Token revoked");
      load();
    } catch (err) {
      toast.error(formatApiError(err.response?.data));
    }
  };

  return (
    <div className="mt-10 rounded-2xl border border-zinc-800 bg-[#101012] p-7" data-testid="install-tokens">
      <div className="flex items-center gap-2 text-[10px] uppercase tracking-[0.22em] font-mono text-amber-500 mb-4">
        <KeyRound size={14} /> Console install tokens
      </div>
      <h3 className="text-2xl font-semibold text-zinc-100 mb-1">Install the Management Console</h3>
      <p className="text-sm text-zinc-400 mb-6 max-w-2xl">
        The AtGlance Management Console installer asks for an install token to confirm the installation belongs to
        your account. Create one here and paste it when the installer prompts for it. Tokens stay valid until you
        revoke them.
      </p>

      {created && (
        <div className="mb-6 rounded-xl border border-amber-500/40 bg-amber-500/5 p-5" data-testid="install-token-created">
          <div className="flex items-start gap-2 text-sm text-amber-400 mb-3">
            <AlertTriangle size={16} className="mt-0.5 shrink-0" />
            <span>
              Copy the token for <b>{created.name}</b> now. It is shown only once; if you lose it, revoke it and create
              a new one.
            </span>
          </div>
          <CodeBlock title="install token" code={created.token} />
          <div className="mt-4 text-[11px] uppercase tracking-[0.18em] text-zinc-500 font-mono mb-2">
            Installer check (for reference)
          </div>
          <CodeBlock
            title="bash"
            code={`curl -X POST ${verifyUrl()} \\\n  -H "Accept: application/json" \\\n  -H "Authorization: Bearer ${created.token}"`}
          />
          <button
            onClick={() => setCreated(null)}
            className="mt-4 text-sm text-zinc-400 hover:text-zinc-100"
            data-testid="install-token-dismiss"
          >
            I've saved it
          </button>
        </div>
      )}

      <form onSubmit={onCreate} className="flex flex-col sm:flex-row gap-3 mb-6">
        <input
          value={name}
          onChange={(e) => setName(e.target.value)}
          required
          maxLength={100}
          placeholder="Token name, e.g. prod-console"
          data-testid="install-token-name"
          className="flex-1 bg-zinc-950 border border-zinc-800 rounded-md px-3 py-2.5 text-sm text-zinc-100 placeholder:text-zinc-600 focus:outline-none focus:border-amber-500/60"
        />
        <button
          type="submit"
          disabled={busy || !name.trim()}
          data-testid="install-token-create"
          className="inline-flex items-center justify-center gap-2 bg-amber-500 text-zinc-950 font-medium px-4 py-2.5 rounded-md hover:bg-amber-400 transition-colors disabled:opacity-50"
        >
          <Plus size={14} /> Create token
        </button>
      </form>

      {tokens === null ? (
        <div className="text-sm text-zinc-500 font-mono">Loading…</div>
      ) : tokens.length === 0 ? (
        <div className="text-sm text-zinc-500">No install tokens yet.</div>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-sm" data-testid="install-token-list">
            <thead>
              <tr className="text-left text-[11px] uppercase tracking-[0.16em] text-zinc-500 border-b border-zinc-800">
                <th className="py-2 pr-4 font-medium">Name</th>
                <th className="py-2 pr-4 font-medium">Created</th>
                <th className="py-2 pr-4 font-medium">Last used</th>
                <th className="py-2" />
              </tr>
            </thead>
            <tbody>
              {tokens.map((t) => (
                <tr key={t.id} className="border-b border-zinc-900 text-zinc-300">
                  <td className="py-2.5 pr-4 font-mono">{t.name}</td>
                  <td className="py-2.5 pr-4">{fmt(t.created_at)}</td>
                  <td className="py-2.5 pr-4">{t.last_used_at ? fmt(t.last_used_at) : "Never"}</td>
                  <td className="py-2.5 text-right">
                    <button
                      onClick={() => onRevoke(t)}
                      data-testid={`install-token-revoke-${t.id}`}
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
