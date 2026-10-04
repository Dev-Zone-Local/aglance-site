import { useEffect, useRef, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { ChevronDown, Globe, LayoutDashboard, LogOut, Shield, UserRound } from "lucide-react";
import { useAuth } from "../lib/auth-context";
import { ADMIN_URL } from "../lib/api";

// User chip + drop-down (design-doc §7.11): white chip, 36px ink avatar, 16px-radius menu.
export function UserMenu({ showDashboard = false }) {
  const { user, logout } = useAuth();
  const nav = useNavigate();
  const [open, setOpen] = useState(false);
  const ref = useRef(null);

  useEffect(() => {
    const onDoc = (e) => ref.current && !ref.current.contains(e.target) && setOpen(false);
    const onKey = (e) => e.key === "Escape" && setOpen(false);
    document.addEventListener("mousedown", onDoc);
    document.addEventListener("keydown", onKey);
    return () => {
      document.removeEventListener("mousedown", onDoc);
      document.removeEventListener("keydown", onKey);
    };
  }, []);

  if (!user) return null;
  const initial = (user.name || user.email || "?").trim().charAt(0).toUpperCase();

  const onLogout = async () => {
    setOpen(false);
    await logout();
    nav("/");
  };

  const item = "flex w-full items-center gap-2.5 rounded-[10px] px-3 py-2.5 text-sm text-ag-ink transition-colors hover:bg-ag-surface";

  return (
    <div className="relative" ref={ref}>
      <button
        onClick={() => setOpen(!open)}
        aria-haspopup="menu"
        aria-expanded={open}
        data-testid="user-menu"
        className="flex items-center gap-2.5 rounded-2xl bg-white py-1.5 pl-1.5 pr-3 shadow-ag transition hover:shadow-ag-strong"
      >
        {user.avatar_url ? (
          <img src={user.avatar_url} alt="" className="h-9 w-9 rounded-xl object-cover" />
        ) : (
          <span className="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-ag-ink text-sm font-medium text-ag-mint">{initial}</span>
        )}
        <span className="hidden text-left min-[1281px]:block">
          <span className="block max-w-[160px] truncate text-sm font-medium text-ag-ink">{user.name}</span>
          <span className="block max-w-[160px] truncate text-[11px] text-ag-muted">{user.email}</span>
        </span>
        <ChevronDown size={14} className={`text-ag-muted transition-transform ${open ? "rotate-180" : ""}`} />
      </button>

      {open && (
        <div role="menu" className="absolute right-0 top-[calc(100%+8px)] z-50 w-60 rounded-2xl bg-white p-2 shadow-ag-strong" data-testid="user-menu-list">
          <div className="px-3 pb-2 pt-1 min-[1281px]:hidden">
            <div className="truncate text-sm font-medium text-ag-ink">{user.name}</div>
            <div className="truncate text-[11px] text-ag-muted">{user.email}</div>
          </div>
          {showDashboard && (
            <Link to="/dashboard" className={item} onClick={() => setOpen(false)} role="menuitem">
              <LayoutDashboard size={15} className="text-ag-teal" /> Dashboard
            </Link>
          )}
          <Link to="/dashboard/profile" className={item} onClick={() => setOpen(false)} role="menuitem" data-testid="user-menu-profile">
            <UserRound size={15} className="text-ag-teal" /> Profile
          </Link>
          {user.role === "admin" && (
            <a href={ADMIN_URL} className={item} role="menuitem" data-testid="user-menu-admin">
              <Shield size={15} className="text-ag-teal" /> Admin panel
            </a>
          )}
          {!showDashboard && (
            <Link to="/" className={item} onClick={() => setOpen(false)} role="menuitem">
              <Globe size={15} className="text-ag-teal" /> Back to website
            </Link>
          )}
          <div className="my-1.5 h-px bg-ag-line" />
          <button onClick={onLogout} className={`${item} text-ag-danger`} role="menuitem" data-testid="user-menu-logout">
            <LogOut size={15} /> Sign out
          </button>
        </div>
      )}
    </div>
  );
}
