import { Check, X } from "lucide-react";

const ROWS = [
  ["Tenancy & billing", true, false, false],
  ["Manage organisations", true, true, false],
  ["Manage users & roles", true, true, false],
  ["Register / deregister systems", true, true, false],
  ["View configuration backups", true, true, true],
  ["Trigger reactivation", true, true, false],
  ["Read assigned workspaces only", false, false, true],
  ["Generate PAT tokens", true, true, true],
];

export function RBACTable() {
  return (
    <div className="rounded-2xl border border-zinc-800 overflow-hidden" data-testid="rbac-table">
      <table className="w-full text-sm">
        <thead>
          <tr className="bg-zinc-950 border-b border-zinc-800">
            <th className="text-left px-5 py-3 font-medium text-zinc-300 text-xs uppercase tracking-[0.16em]">Capability</th>
            <th className="text-center px-3 py-3 font-medium text-amber-500 text-xs uppercase tracking-[0.16em]">superadmin</th>
            <th className="text-center px-3 py-3 font-medium text-zinc-300 text-xs uppercase tracking-[0.16em]">admin</th>
            <th className="text-center px-3 py-3 font-medium text-zinc-300 text-xs uppercase tracking-[0.16em]">user</th>
          </tr>
        </thead>
        <tbody>
          {ROWS.map(([label, s, a, u], i) => (
            <tr key={i} className="border-b border-zinc-900 last:border-0 hover:bg-zinc-950/50">
              <td className="px-5 py-3 text-zinc-300">{label}</td>
              <td className="text-center">{s ? <Check size={16} className="inline text-amber-500" /> : <X size={16} className="inline text-zinc-700" />}</td>
              <td className="text-center">{a ? <Check size={16} className="inline text-amber-500" /> : <X size={16} className="inline text-zinc-700" />}</td>
              <td className="text-center">{u ? <Check size={16} className="inline text-amber-500" /> : <X size={16} className="inline text-zinc-700" />}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
