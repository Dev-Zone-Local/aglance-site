export function FeatureCard({ icon: Icon, title, desc, badge, testId }) {
  return (
    <div
      className="lift relative rounded-xl border border-zinc-800 bg-[#101012] p-6 group"
      data-testid={testId}
    >
      {badge && (
        <span className="absolute top-4 right-4 text-[10px] uppercase tracking-[0.18em] text-amber-500 font-mono">
          {badge}
        </span>
      )}
      {Icon && (
        <div className="w-10 h-10 rounded-lg flex items-center justify-center bg-amber-500/10 border border-amber-500/30 mb-5">
          <Icon size={18} className="text-amber-500" />
        </div>
      )}
      <h3 className="text-lg font-semibold text-zinc-100 mb-2">{title}</h3>
      <p className="text-sm leading-relaxed text-zinc-400">{desc}</p>
    </div>
  );
}

export function SectionHeading({ eyebrow, title, sub, align = "left" }) {
  return (
    <div className={`max-w-3xl ${align === "center" ? "mx-auto text-center" : ""}`}>
      {eyebrow && (
        <div className="text-[11px] uppercase tracking-[0.22em] text-amber-500 font-mono mb-3">
          {eyebrow}
        </div>
      )}
      <h2 className="text-3xl sm:text-4xl font-semibold tracking-tight text-zinc-100 mb-4">
        {title}
      </h2>
      {sub && <p className="text-lg text-zinc-400 leading-relaxed">{sub}</p>}
    </div>
  );
}
