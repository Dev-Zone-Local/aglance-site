import { Link } from "react-router-dom";

const LINKS = [
  { to: "/about", label: "About" },
  { to: "/contact", label: "Contact" },
  { to: "/faq", label: "FAQ" },
  { to: "/docs", label: "Docs" },
  { to: "/security", label: "Security" },
  { to: "/terms", label: "Terms" },
  { to: "/privacy", label: "Privacy" },
];

// Centred 12px muted footer; black links separated by " | " (design-doc §7.12).
export function Footer() {
  return (
    <footer className="mt-16 py-6 text-center text-xs text-ag-muted" data-testid="site-footer">
      <nav className="mb-2" aria-label="Footer">
        {LINKS.map((l, i) => (
          <span key={l.to}>
            {i > 0 && <span className="mx-2">|</span>}
            <Link to={l.to} className="text-ag-ink hover:text-ag-teal transition-colors">
              {l.label}
            </Link>
          </span>
        ))}
      </nav>
      <div>© 2026 AtGlance · Self-hosted operations for SREs, inside your boundary.</div>
    </footer>
  );
}
