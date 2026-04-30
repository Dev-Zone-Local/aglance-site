import { NavLink } from "react-router-dom";

const SECTIONS = [
  { title: "Getting Started", items: [{ slug: "quickstart", label: "Quickstart" }] },
  { title: "CLI", items: [{ slug: "cli", label: "CLI reference" }] },
  // { title: "API", items: [{ slug: "api", label: "API reference" }] },
  { title: "Self-hosting", items: [{ slug: "self-hosting", label: "Self-hosting the Console" }] },
  { title: "Architecture", items: [{ slug: "architecture", label: "Architecture deep dive" }] },
];

export function DocsSidebar() {
  return (
    <aside className="lg:sticky lg:top-20 lg:max-h-[calc(100vh-6rem)] overflow-y-auto" data-testid="docs-sidebar">
      <div className="text-[11px] uppercase tracking-[0.22em] text-amber-500 font-mono mb-4 px-1">Documentation</div>
      <nav className="space-y-6">
        {SECTIONS.map((sec) => (
          <div key={sec.title}>
            <div className="text-[11px] uppercase tracking-[0.18em] text-zinc-500 font-mono px-1 mb-2">
              {sec.title}
            </div>
            <ul className="space-y-1">
              {sec.items.map((it) => (
                <li key={it.slug}>
                  <NavLink
                    to={`/docs/${it.slug}`}
                    data-testid={`docs-link-${it.slug}`}
                    className={({ isActive }) =>
                      `block px-3 py-1.5 rounded-md text-[13px] transition-colors ${
                        isActive
                          ? "bg-amber-500/10 text-amber-500 border-l-2 border-amber-500"
                          : "text-zinc-400 hover:text-zinc-100 border-l-2 border-transparent"
                      }`
                    }
                  >
                    {it.label}
                  </NavLink>
                </li>
              ))}
            </ul>
          </div>
        ))}
      </nav>
    </aside>
  );
}
