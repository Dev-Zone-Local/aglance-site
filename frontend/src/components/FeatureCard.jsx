import { Feature } from "./ag";

// Kept for existing pages; renders the design-system feature card (design-doc §7.6).
export function FeatureCard({ icon, title, desc, badge, testId }) {
  return (
    <Feature icon={icon} title={title} badge={badge} testId={testId}>
      {desc}
    </Feature>
  );
}

export function SectionHeading({ eyebrow, title, sub, align = "left" }) {
  return (
    <div className={`max-w-3xl ${align === "center" ? "mx-auto text-center" : ""}`}>
      {eyebrow && <div className="mb-2 text-xs font-medium text-ag-teal">{eyebrow}</div>}
      <h2 className="mb-3 text-[26px] sm:text-[30px] font-medium tracking-heading text-ag-ink">{title}</h2>
      {sub && <p className="text-[15px] leading-relaxed text-ag-subtle">{sub}</p>}
    </div>
  );
}
