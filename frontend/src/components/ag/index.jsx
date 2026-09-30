// AtGlance design-system primitives (design-doc.md §7), shared by all pages.
import { Link } from "react-router-dom";

const cx = (...c) => c.filter(Boolean).join(" ");

const BUTTON = {
  primary: "bg-ag-gradient text-ag-ink hover:bg-ag-gradient-hover hover:shadow-ag-glow",
  dark: "bg-ag-ink text-white hover:bg-ag-ink-hover",
  ghost: "bg-ag-surface text-ag-ink hover:bg-ag-line",
  danger: "bg-ag-danger-soft text-ag-danger hover:bg-ag-danger-hover",
  white: "bg-white text-ag-ink shadow-ag hover:shadow-ag-strong",
};

/** Pill button. `to` renders a router Link, `href` an anchor, otherwise a <button>. */
export function Button({ variant = "primary", size, to, href, className, children, ...props }) {
  const cls = cx(
    "inline-flex items-center justify-center gap-2 rounded-full font-medium whitespace-nowrap transition duration-200 disabled:opacity-50 disabled:pointer-events-none",
    size === "sm" ? "px-3.5 py-[7px] text-[13px]" : size === "lg" ? "px-6 py-3 text-[15px]" : "px-[18px] py-2.5 text-sm",
    BUTTON[variant],
    className,
  );
  if (to) return <Link to={to} className={cls} {...props}>{children}</Link>;
  if (href) return <a href={href} className={cls} {...props}>{children}</a>;
  return <button className={cls} {...props}>{children}</button>;
}

/** White card (default), `dark` ink card, or `flat` surface panel. No borders. */
export function Card({ dark, flat, hover, className, children, ...props }) {
  return (
    <div
      className={cx(
        "rounded-card p-5",
        dark ? "ag-hero text-white" : flat ? "bg-ag-surface" : "bg-white shadow-ag",
        hover && "lift",
        className,
      )}
      {...props}
    >
      {children}
    </div>
  );
}

/** Card header: 28px ink icon tile + 15px title. */
export function CardHeader({ icon: Icon, title, action }) {
  return (
    <div className="mb-4 flex items-center gap-2.5">
      {Icon && (
        <span className="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-ag-ink text-white">
          <Icon size={13} />
        </span>
      )}
      <h3 className="text-[15px] font-medium tracking-normal text-ag-ink">{title}</h3>
      {action && <div className="ml-auto">{action}</div>}
    </div>
  );
}

/** Dark ink hero with sky (top-right) and mint (bottom-left) glows. */
export function Hero({ eyebrow, title, children, actions, className, aside }) {
  return (
    <section className={cx("ag-hero px-6 py-8 sm:px-10 sm:py-12", className)}>
      <div className={cx(aside && "grid gap-10 lg:grid-cols-[1.1fr_1fr] lg:items-center")}>
        <div>
          {eyebrow && <div className="mb-4 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-ag-mint">{eyebrow}</div>}
          <h1 className="mb-4 text-[30px] sm:text-[40px] font-medium leading-tight tracking-heading text-white">{title}</h1>
          {children && <div className="max-w-[640px] text-base leading-[1.7] text-ag-ink-soft">{children}</div>}
          {actions && <div className="mt-8 flex flex-wrap gap-3">{actions}</div>}
        </div>
        {aside && <div className="min-w-0">{aside}</div>}
      </div>
    </section>
  );
}

/** Three-column feature grid (2 columns below 900px, 1 below 560px). */
export function FeatureGrid({ className, children }) {
  return <div className={cx("grid grid-cols-3 max-[900px]:grid-cols-2 max-[560px]:grid-cols-1 gap-4", className)}>{children}</div>;
}

export function Feature({ icon: Icon, title, children, badge, testId }) {
  return (
    <div className="lift relative rounded-card bg-white p-[22px] shadow-ag" data-testid={testId}>
      {badge && <Badge className="absolute right-4 top-4">{badge}</Badge>}
      {Icon && (
        <span className="mb-3.5 inline-flex h-9 w-9 items-center justify-center rounded-[10px] bg-ag-ink text-ag-mint">
          <Icon size={16} />
        </span>
      )}
      <h3 className="mb-1.5 text-[15px] font-semibold tracking-normal text-ag-ink">{title}</h3>
      <p className="text-[13px] leading-[1.6] text-ag-subtle">{children}</p>
    </div>
  );
}

const BADGE = {
  default: "bg-ag-surface text-ag-subtle",
  success: "bg-ag-success-soft text-ag-success",
  danger: "bg-ag-danger-soft text-ag-danger",
  warning: "bg-ag-warning-soft text-ag-warning",
  info: "bg-ag-info-soft text-ag-info",
};

export function Badge({ variant = "default", className, children, ...props }) {
  return (
    <span className={cx("inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg px-2.5 py-1 text-xs font-medium", BADGE[variant], className)} {...props}>
      {children}
    </span>
  );
}

const ALERT = {
  success: "bg-ag-success-soft text-ag-success-text",
  danger: "bg-ag-danger-soft text-ag-danger-text",
  warning: "bg-ag-warning-soft text-ag-warning-text",
  info: "bg-ag-info-soft text-ag-info",
};

export function Alert({ variant = "info", icon: Icon, className, children, ...props }) {
  return (
    <div className={cx("flex items-start gap-2.5 rounded-input px-4 py-3 text-sm", ALERT[variant], className)} role="status" {...props}>
      {Icon && <Icon size={16} className="mt-0.5 shrink-0" />}
      <div className="min-w-0 flex-1">{children}</div>
    </div>
  );
}

export const inputClass =
  "w-full rounded-input border border-transparent bg-ag-surface px-3.5 py-[11px] text-sm text-ag-ink placeholder:text-ag-muted transition focus:border-ag-mint focus:bg-white focus:shadow-ag-focus focus:outline-none";

export function Input({ className, ...props }) {
  return <input className={cx(inputClass, className)} {...props} />;
}

export function Label({ className, children, ...props }) {
  return (
    <label className={cx("mb-1.5 block text-[13px] font-medium text-ag-subtle", className)} {...props}>
      {children}
    </label>
  );
}

/** Page title block used at the top of inner pages. */
export function PageHeader({ eyebrow, title, children }) {
  return (
    <div className="mb-6">
      {eyebrow && <div className="mb-2 text-xs font-medium text-ag-teal">{eyebrow}</div>}
      <h1 className="text-[30px] font-medium tracking-heading text-ag-ink">{title}</h1>
      {children && <p className="mt-2 max-w-2xl text-[15px] leading-relaxed text-ag-subtle">{children}</p>}
    </div>
  );
}
