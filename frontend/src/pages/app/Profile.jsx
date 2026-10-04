import { useEffect, useState } from "react";
import { toast } from "sonner";
import { Bell, KeyRound, UserRound } from "lucide-react";
import { api, formatApiError } from "../../lib/api";
import { useAuth } from "../../lib/auth-context";
import { Badge, Button, Card, CardHeader, Input, Label, PageHeader } from "../../components/ag";

export default function Profile() {
  const { user, refresh } = useAuth();
  const [name, setName] = useState(user?.name || "");
  const [savingName, setSavingName] = useState(false);
  const [pw, setPw] = useState({ current_password: "", password: "", password_confirmation: "" });
  const [savingPw, setSavingPw] = useState(false);
  const [savingPref, setSavingPref] = useState(false);

  useEffect(() => setName(user?.name || ""), [user?.name]);

  const saveName = async (e) => {
    e.preventDefault();
    setSavingName(true);
    try {
      await api.put("/account/profile", { name });
      await refresh();
      toast.success("Profile saved");
    } catch (err) {
      toast.error(formatApiError(err.response?.data));
    } finally {
      setSavingName(false);
    }
  };

  const savePassword = async (e) => {
    e.preventDefault();
    if (pw.password !== pw.password_confirmation) {
      toast.error("Passwords do not match");
      return;
    }
    setSavingPw(true);
    try {
      const { data } = await api.put("/account/password", pw);
      toast.success(data.message);
      setPw({ current_password: "", password: "", password_confirmation: "" });
      await refresh();
    } catch (err) {
      toast.error(formatApiError(err.response?.data));
    } finally {
      setSavingPw(false);
    }
  };

  const toggleUpdates = async () => {
    setSavingPref(true);
    try {
      await api.put("/account/preferences", { notify_updates: !user?.notify_updates });
      await refresh();
    } catch (err) {
      toast.error(formatApiError(err.response?.data));
    } finally {
      setSavingPref(false);
    }
  };

  if (!user) return null;

  return (
    <div data-testid="profile-page">
      <PageHeader eyebrow="Account" title="Profile" />
      <div className="grid gap-4 lg:grid-cols-2">
        <Card className="p-6">
          <CardHeader icon={UserRound} title="Your details" />
          <form onSubmit={saveName} className="space-y-4">
            <div>
              <Label htmlFor="profile-name">Name</Label>
              <Input id="profile-name" value={name} onChange={(e) => setName(e.target.value)} required maxLength={255} />
            </div>
            <div>
              <Label>Email</Label>
              <div className="flex flex-wrap items-center gap-2 text-sm text-ag-ink">
                {user.email}
                {user.email_verified ? <Badge variant="success">Verified</Badge> : <Badge variant="warning">Not verified</Badge>}
              </div>
            </div>
            <div className="flex flex-wrap gap-2 text-xs text-ag-muted">
              <Badge>{user.plan === "enterprise" ? "Enterprise" : "Free"} plan</Badge>
              <Badge>Signed in with {user.auth_method === "github" ? "GitHub" : "email"}</Badge>
            </div>
            <Button type="submit" disabled={savingName || !name.trim() || name === user.name}>
              {savingName ? "Saving…" : "Save"}
            </Button>
          </form>
        </Card>

        <Card className="p-6">
          <CardHeader icon={KeyRound} title={user.has_password ? "Change password" : "Set a password"} />
          <form onSubmit={savePassword} className="space-y-4">
            {user.has_password && (
              <div>
                <Label htmlFor="pw-current">Current password</Label>
                <Input id="pw-current" type="password" autoComplete="current-password" required
                  value={pw.current_password} onChange={(e) => setPw({ ...pw, current_password: e.target.value })} />
              </div>
            )}
            <div>
              <Label htmlFor="pw-new">New password</Label>
              <Input id="pw-new" type="password" autoComplete="new-password" required minLength={6}
                value={pw.password} onChange={(e) => setPw({ ...pw, password: e.target.value })} />
            </div>
            <div>
              <Label htmlFor="pw-confirm">Confirm new password</Label>
              <Input id="pw-confirm" type="password" autoComplete="new-password" required minLength={6}
                value={pw.password_confirmation} onChange={(e) => setPw({ ...pw, password_confirmation: e.target.value })} />
            </div>
            <Button type="submit" disabled={savingPw}>{savingPw ? "Saving…" : "Update password"}</Button>
            <p className="text-xs text-ag-muted">Other devices are signed out after a password change.</p>
          </form>
        </Card>

        <Card className="p-6 lg:col-span-2">
          <CardHeader icon={Bell} title="Email notifications" />
          <label className="flex cursor-pointer items-center gap-3" data-testid="notify-updates">
            <input type="checkbox" className="h-4 w-4 accent-[#2CB7D9]" checked={!!user.notify_updates} disabled={savingPref} onChange={toggleUpdates} />
            <span className="text-sm text-ag-ink">Email me when a new CLI or Management Console version is released</span>
          </label>
        </Card>
      </div>
    </div>
  );
}
