import { Github, KeyRound, ShieldCheck, Server } from "lucide-react";

// Guest layout from the console (design-doc §6): dark hero on the left, 400px sticky card on the
// right. Below 900px the card moves above the hero.
export function AuthShell({ title, subtitle, children, testId }) {
  return (
    <div
      className="grid gap-6 min-[901px]:grid-cols-[1fr_400px] min-[901px]:items-start"
      data-testid={testId}
    >
      <section className="ag-hero order-2 min-[901px]:order-1 px-6 py-8 sm:px-10 sm:py-12">
        <h2 className="mb-4 text-[30px] sm:text-[40px] font-medium leading-tight tracking-heading text-white">
          Your operations platform,
          <br />
          inside your boundary.
        </h2>
        <p className="max-w-[560px] text-base leading-[1.7] text-ag-ink-soft">
          One account for the AtGlance CLI downloads and your self-hosted Management Console licences.
        </p>
        <div className="mt-8 grid gap-3 sm:grid-cols-3">
          {[
            { icon: Server, title: "Self-hosted", text: "Console runs in your VPC or on-prem." },
            { icon: KeyRound, title: "Licences", text: "Create a licence key for your Console." },
            { icon: ShieldCheck, title: "No telemetry", text: "Nothing leaves your boundary." },
          ].map((f) => (
            <div key={f.title} className="rounded-2xl bg-white/[0.06] p-4">
              <span className="mb-3 inline-flex h-7 w-7 items-center justify-center rounded-lg bg-white/[0.12] text-ag-mint">
                <f.icon size={13} />
              </span>
              <div className="text-sm font-medium text-white">{f.title}</div>
              <div className="mt-1 text-[13px] leading-relaxed text-ag-ink-soft">{f.text}</div>
            </div>
          ))}
        </div>
      </section>

      <div className="order-1 min-[901px]:order-2 min-[901px]:sticky min-[901px]:top-6 rounded-card bg-white p-7 shadow-ag">
        <h1 className="text-[26px] font-medium tracking-heading text-ag-ink">{title}</h1>
        {subtitle && <p className="mb-6 mt-1 text-sm text-ag-subtle">{subtitle}</p>}
        {children}
      </div>
    </div>
  );
}

export function GithubButton({ onClick, children, testId = "github-sso-btn" }) {
  return (
    <button
      type="button"
      onClick={onClick}
      data-testid={testId}
      className="inline-flex w-full items-center justify-center gap-2.5 rounded-full bg-ag-ink px-[18px] py-2.5 text-sm font-medium text-white transition-colors hover:bg-ag-ink-hover"
    >
      <Github size={16} /> {children}
    </button>
  );
}

export function OrDivider() {
  return (
    <div className="my-5 flex items-center gap-3">
      <div className="h-px flex-1 bg-ag-line" />
      <span className="text-xs text-ag-muted">or</span>
      <div className="h-px flex-1 bg-ag-line" />
    </div>
  );
}
