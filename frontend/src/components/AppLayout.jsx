import { useState } from "react";
import { NavLink, Outlet } from "react-router-dom";
import { Download, KeyRound, LayoutDashboard, Menu, X } from "lucide-react";
import { Logo } from "./Header";
import { Footer } from "./Footer";
import { UserMenu } from "./UserMenu";

// Signed-in app area: only account-relevant navigation, plus the user menu.
const NAV = [
  { to: "/dashboard", label: "Overview", icon: LayoutDashboard, end: true },
  { to: "/dashboard/install", label: "Install", icon: Download },
  { to: "/dashboard/licences", label: "Licences", icon: KeyRound },
];

const pill = ({ isActive }) =>
  `inline-flex items-center gap-2 rounded-full px-4 py-[9px] text-sm font-medium whitespace-nowrap transition-colors duration-200 ${
    isActive ? "bg-ag-gradient text-ag-ink shadow-ag-pill" : "text-ag-muted hover:bg-ag-surface hover:text-ag-ink"
  }`;

export function AppLayout() {
  const [open, setOpen] = useState(false);

  return (
    <div className="ag-frame flex flex-col">
      <header className="relative z-40 mb-6" data-testid="app-header">
        <div className="flex items-center gap-4">
          <Logo />
          <nav className="mx-auto hidden min-[761px]:flex items-center gap-1 rounded-full bg-white p-[5px] shadow-ag" aria-label="Account">
            {NAV.map((n) => (
              <NavLink key={n.to} to={n.to} end={n.end} className={pill} data-testid={`app-nav-${n.label.toLowerCase()}`}>
                <n.icon size={14} /> {n.label}
              </NavLink>
            ))}
          </nav>
          <div className="ml-auto min-[761px]:ml-0 flex items-center gap-2">
            <UserMenu />
            <button
              onClick={() => setOpen(!open)}
              className="min-[761px]:hidden inline-flex h-[42px] w-[42px] items-center justify-center rounded-[14px] bg-white text-ag-ink shadow-ag"
              aria-label="Toggle navigation"
              aria-expanded={open}
            >
              {open ? <X size={18} /> : <Menu size={18} />}
            </button>
          </div>
        </div>
        {open && (
          <div className="min-[761px]:hidden absolute left-0 right-0 top-[58px] rounded-2xl bg-white p-2 shadow-ag-strong">
            {NAV.map((n) => (
              <NavLink
                key={n.to}
                to={n.to}
                end={n.end}
                onClick={() => setOpen(false)}
                className={({ isActive }) =>
                  `flex items-center gap-2.5 rounded-[10px] px-3 py-2.5 text-sm font-medium ${isActive ? "bg-ag-surface text-ag-ink" : "text-ag-subtle hover:bg-ag-surface"}`
                }
              >
                <n.icon size={15} /> {n.label}
              </NavLink>
            ))}
          </div>
        )}
      </header>
      <main className="flex-1">
        <Outlet />
      </main>
      <Footer />
    </div>
  );
}
