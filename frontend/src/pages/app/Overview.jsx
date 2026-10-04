import { useEffect, useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import { toast } from "sonner";
import { ArrowRight, BookOpen, Cpu, KeyRound, Server } from "lucide-react";
import { api } from "../../lib/api";
import { useAuth } from "../../lib/auth-context";
import { Badge, Card, Hero } from "../../components/ag";
import EmailVerifyBanner from "../../components/EmailVerifyBanner";

const STATUS = {
  unverified: ["default", "Pending"],
  under_review: ["warning", "Under review"],
  ready: ["info", "Ready"],
  in_use: ["success", "In Use"],
};

function Stat({ icon: Icon, label, value, sub, to, cta, testId }) {
  return (
    <Link to={to} className="group" data-testid={testId}>
      <Card hover className="h-full p-6">
        <div className="mb-4 flex items-center gap-2.5">
          <span className="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-ag-ink text-white">
            <Icon size={13} />
          </span>
          <span className="text-[13px] font-medium text-ag-subtle">{label}</span>
        </div>
        <div className="text-[36px] font-normal leading-[1.1] tracking-heading text-ag-ink">{value}</div>
        {sub && <div className="mt-1.5 text-xs text-ag-muted">{sub}</div>}
        <span className="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-ag-teal transition-all group-hover:gap-2.5">
          {cta} <ArrowRight size={14} />
        </span>
      </Card>
    </Link>
  );
}

export default function Overview() {
  const { user, refresh } = useAuth();
  const [dl, setDl] = useState(null);
  const [lic, setLic] = useState(null);
  const [params, setParams] = useSearchParams();

  useEffect(() => {
    api.get("/downloads").then((r) => setDl(r.data)).catch(() => {});
    api.get("/licenses").then((r) => setLic(r.data)).catch(() => {});
  }, []);

  // Result of the unsubscribe link in release emails.
  useEffect(() => {
    const result = params.get("unsubscribed");
    if (!result) return;
    if (result === "1") toast.success("You will no longer get emails about new releases.");
    else toast.error("That unsubscribe link is not valid. Change it in your profile instead.");
    params.delete("unsubscribed");
    setParams(params, { replace: true });
    refresh();
  }, [params, setParams, refresh]);

  const licences = lic?.licenses || [];
  const limit = lic?.limit ?? null;

  return (
    <div className="space-y-6" data-testid="dashboard-page">
      <EmailVerifyBanner />

      <Hero
        title={`Welcome back${user?.name ? `, ${user.name}` : ""}.`}
        actions={
          <>
            <Link to="/dashboard/install" className="inline-flex items-center gap-2 rounded-full bg-ag-gradient px-6 py-3 text-[15px] font-medium text-ag-ink transition hover:bg-ag-gradient-hover hover:shadow-ag-glow">
              Install AtGlance <ArrowRight size={16} />
            </Link>
            <Link to="/dashboard/licences" className="inline-flex items-center gap-2 rounded-full bg-white px-6 py-3 text-[15px] font-medium text-ag-ink">
              Manage licences
            </Link>
          </>
        }
      >
        Run AtGlance inside your boundary: install the CLI on your hosts and the Management Console for your organization.
      </Hero>

      <div className="grid gap-4 md:grid-cols-3">
        <Stat
          icon={Cpu}
          label="AtGlance CLI"
          value={dl?.cli?.latest ? `v${dl.cli.latest.version}` : "—"}
          sub="Latest version"
          to="/dashboard/install/cli"
          cta="Install the CLI"
          testId="overview-cli"
        />
        <Stat
          icon={Server}
          label="Management Console"
          value={dl?.console?.latest ? `v${dl.console.latest.version}` : "—"}
          sub="Latest version"
          to="/dashboard/install/console"
          cta="Install the Console"
          testId="overview-console"
        />
        <Stat
          icon={KeyRound}
          label="Licences"
          value={lic ? `${licences.length}${limit !== null ? ` / ${limit}` : ""}` : "—"}
          sub={lic ? `${lic.plan_label} plan` : null}
          to="/dashboard/licences"
          cta={licences.length ? "View licences" : "Create a licence"}
          testId="overview-licences"
        />
      </div>

      {licences.length > 0 && (
        <Card className="p-6">
          <div className="mb-3 text-[15px] font-medium text-ag-ink">Your licences</div>
          <ul className="divide-y divide-ag-line">
            {licences.slice(0, 3).map((l) => {
              const [variant, label] = STATUS[l.status] || STATUS.unverified;
              return (
                <li key={l.id} className="flex flex-wrap items-center gap-3 py-3 text-sm">
                  <span className="font-mono text-ag-ink">{l.name}</span>
                  <Badge variant={variant}>{label}</Badge>
                  {l.console?.org_name && <span className="text-ag-subtle">{l.console.org_name}</span>}
                </li>
              );
            })}
          </ul>
        </Card>
      )}

      <Link to="/docs/quickstart" className="group block">
        <Card flat className="flex items-center gap-3 p-5">
          <BookOpen size={16} className="text-ag-teal" />
          <span className="text-sm text-ag-ink">New to AtGlance? Read the 5-minute quickstart.</span>
          <ArrowRight size={14} className="ml-auto text-ag-teal transition-transform group-hover:translate-x-1" />
        </Card>
      </Link>
    </div>
  );
}
