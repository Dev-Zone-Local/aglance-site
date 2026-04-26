import { Link, NavLink, useNavigate } from "react-router-dom";
import { useAuth } from "../lib/auth-context";
import { ChevronDown, Terminal as TerminalIcon, Menu, X } from "lucide-react";
import { useState } from "react";

const NAV = [
  { to: "/product", label: "Product" },
  { to: "/cli", label: "CLI" },
  { to: "/console", label: "Console" },
  { to: "/architecture", label: "Architecture" },
  { to: "/use-cases", label: "Use cases" },
  { to: "/security", label: "Security" },
  { to: "/pricing", label: "Pricing" },
  { to: "/docs", label: "Docs" },
];

export function Header() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const [open, setOpen] = useState(false);

  return (
    <header className="sticky top-0 z-40 border-b border-zinc-900 bg-[#0a0a0a]/80 backdrop-blur supports-[backdrop-filter]:bg-[#0a0a0a]/60" data-testid="site-header">
      <div className="max-w-7xl mx-auto px-5 sm:px-8 h-16 flex items-center gap-6">
        <Link to="/" className="flex items-center gap-2.5 group" data-testid="logo-link">
          <div className="w-8 h-8 rounded-md bg-amber-500/10 border border-amber-500/40 flex items-center justify-center">
            <TerminalIcon size={15} className="text-amber-500" />
          </div>
          <span className="font-semibold tracking-tight text-zinc-100">AtGlance</span>
          <span className="hidden sm:inline text-[10px] font-mono uppercase tracking-[0.18em] text-zinc-500 ml-1">v1.0</span>
        </Link>
        <nav className="hidden lg:flex items-center gap-1 ml-4 flex-1">
          {NAV.map((n) => (
            <NavLink
              key={n.to}
              to={n.to}
              data-testid={`nav-${n.label.toLowerCase().replace(/\s+/g, "-")}`}
              className={({ isActive }) =>
                `px-3 py-1.5 text-[13px] rounded-md transition-colors ${
                  isActive ? "text-amber-500" : "text-zinc-400 hover:text-zinc-100"
                }`
              }
            >
              {n.label}
            </NavLink>
          ))}
        </nav>
        <div className="ml-auto flex items-center gap-2">
          {user && user !== false ? (
            <>
              <Link
                to={user.role === "admin" ? "/admin" : "/dashboard"}
                data-testid="header-dashboard-link"
                className="hidden sm:inline text-sm text-zinc-300 hover:text-amber-500 px-3 py-1.5"
              >
                {user.role === "admin" ? "Admin" : "Dashboard"}
              </Link>
              <button
                onClick={async () => { await logout(); navigate("/"); }}
                data-testid="header-logout-btn"
                className="text-sm text-zinc-400 hover:text-zinc-100 px-3 py-1.5"
              >
                Sign out
              </button>
            </>
          ) : (
            <>
              <Link to="/login" data-testid="header-login-link" className="hidden sm:inline text-sm text-zinc-300 hover:text-amber-500 px-3 py-1.5">
                Sign in
              </Link>
              <Link
                to="/register"
                data-testid="header-signup-btn"
                className="text-[13px] font-medium px-3.5 py-1.5 rounded-md bg-amber-500 text-zinc-950 hover:bg-amber-400 transition-colors"
              >
                Sign up — Free
              </Link>
            </>
          )}
          <button
            onClick={() => setOpen(!open)}
            className="lg:hidden ml-1 p-2 text-zinc-300"
            data-testid="mobile-nav-toggle"
            aria-label="Toggle navigation"
          >
            {open ? <X size={18} /> : <Menu size={18} />}
          </button>
        </div>
      </div>
      {open && (
        <div className="lg:hidden border-t border-zinc-900 bg-[#0a0a0a] px-5 py-4 space-y-1" data-testid="mobile-nav">
          {NAV.map((n) => (
            <Link
              key={n.to}
              to={n.to}
              onClick={() => setOpen(false)}
              className="block px-2 py-2 text-sm text-zinc-300 hover:text-amber-500"
            >
              {n.label}
            </Link>
          ))}
        </div>
      )}
    </header>
  );
}
