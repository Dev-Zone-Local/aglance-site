import { Link, NavLink, useNavigate } from "react-router-dom";
import { useEffect, useState } from "react";
import { Menu, X, LayoutDashboard, LogOut, Shield } from "lucide-react";
import { useAuth } from "../lib/auth-context";
import { ADMIN_URL } from "../lib/api";

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

const pill = ({ isActive }) =>
  `rounded-full px-4 max-[1560px]:px-[13px] max-[1180px]:px-2.5 max-[1180px]:text-[13px] py-[9px] text-sm font-medium whitespace-nowrap transition-colors duration-200 ${
    isActive ? "bg-ag-gradient text-ag-ink shadow-ag-pill" : "text-ag-muted hover:bg-ag-surface hover:text-ag-ink"
  }`;

export function Logo({ className = "" }) {
  return (
    <Link to="/" className={`flex items-center shrink-0 ${className}`} data-testid="logo-link" aria-label="AtGlance home">
      <img src="/branding/atglance-logo.png" alt="AtGlance" className="h-[34px] max-[560px]:h-[26px] w-auto max-w-[180px]" />
    </Link>
  );
}

export function Header() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const [open, setOpen] = useState(false);
  const signedIn = user && user !== false;

  // Close the mobile sheet when the window grows past the breakpoint.
  useEffect(() => {
    const onResize = () => window.innerWidth > 900 && setOpen(false);
    window.addEventListener("resize", onResize);
    return () => window.removeEventListener("resize", onResize);
  }, []);

  const onLogout = async () => {
    await logout();
    navigate("/");
  };

  const accountLink =
    signedIn &&
    (user.role === "admin" ? (
      // Filament panel is served by Laravel, so use a full page load.
      <a href={ADMIN_URL} data-testid="header-dashboard-link" className="inline-flex items-center gap-2 rounded-full bg-ag-surface px-[18px] py-2.5 text-sm font-medium text-ag-ink transition-colors hover:bg-ag-line">
        <Shield size={14} /> Admin
      </a>
    ) : (
      <Link to="/dashboard" data-testid="header-dashboard-link" className="inline-flex items-center gap-2 rounded-full bg-ag-surface px-[18px] py-2.5 text-sm font-medium text-ag-ink transition-colors hover:bg-ag-line">
        <LayoutDashboard size={14} /> Dashboard
      </Link>
    ));

  return (
    <header className="relative z-40 mb-6" data-testid="site-header">
      <div className="flex items-center gap-4">
        <Logo />

        <nav className="mx-auto hidden min-[901px]:flex items-center gap-1 rounded-full bg-white p-[5px] shadow-ag" aria-label="Main">
          {NAV.map((n) => (
            <NavLink key={n.to} to={n.to} className={pill} data-testid={`nav-${n.label.toLowerCase().replace(/\s+/g, "-")}`}>
              {n.label}
            </NavLink>
          ))}
        </nav>

        <div className="ml-auto min-[901px]:ml-0 flex items-center gap-2">
          {signedIn ? (
            <>
              <span className="hidden min-[901px]:inline-flex">{accountLink}</span>
              <button
                onClick={onLogout}
                data-testid="header-logout-btn"
                className="hidden min-[901px]:inline-flex h-[42px] w-[42px] items-center justify-center rounded-[14px] bg-white text-ag-subtle shadow-ag transition-colors hover:text-ag-ink"
                aria-label="Sign out"
                title="Sign out"
              >
                <LogOut size={15} />
              </button>
            </>
          ) : (
            <>
              <Link to="/login" data-testid="header-login-link" className="hidden min-[901px]:inline-flex rounded-full bg-ag-surface px-[18px] py-2.5 text-sm font-medium text-ag-ink transition-colors hover:bg-ag-line">
                Sign in
              </Link>
              <Link
                to="/register"
                data-testid="header-signup-btn"
                className="inline-flex whitespace-nowrap rounded-full bg-ag-gradient px-[18px] py-2.5 text-sm font-medium text-ag-ink transition hover:bg-ag-gradient-hover hover:shadow-ag-glow"
              >
                Sign up free
              </Link>
            </>
          )}
          <button
            onClick={() => setOpen(!open)}
            className="min-[901px]:hidden inline-flex h-[42px] w-[42px] items-center justify-center rounded-[14px] bg-white text-ag-ink shadow-ag"
            data-testid="mobile-nav-toggle"
            aria-label="Toggle navigation"
            aria-expanded={open}
          >
            {open ? <X size={18} /> : <Menu size={18} />}
          </button>
        </div>
      </div>

      {open && (
        <div className="min-[901px]:hidden absolute left-0 right-0 top-[54px] rounded-2xl bg-white p-2 shadow-ag-strong" data-testid="mobile-nav">
          {NAV.map((n) => (
            <NavLink
              key={n.to}
              to={n.to}
              onClick={() => setOpen(false)}
              className={({ isActive }) =>
                `block rounded-[10px] px-3 py-2.5 text-sm font-medium ${isActive ? "bg-ag-surface text-ag-ink" : "text-ag-subtle hover:bg-ag-surface hover:text-ag-ink"}`
              }
            >
              {n.label}
            </NavLink>
          ))}
          <div className="my-2 h-px bg-ag-line" />
          {signedIn ? (
            <div className="flex items-center gap-2 px-1 pb-1" onClick={() => setOpen(false)}>
              {accountLink}
              <button onClick={onLogout} className="rounded-full bg-ag-surface px-[18px] py-2.5 text-sm font-medium text-ag-ink hover:bg-ag-line">
                Sign out
              </button>
            </div>
          ) : (
            <Link to="/login" onClick={() => setOpen(false)} className="block rounded-[10px] px-3 py-2.5 text-sm font-medium text-ag-ink hover:bg-ag-surface">
              Sign in
            </Link>
          )}
        </div>
      )}
    </header>
  );
}
