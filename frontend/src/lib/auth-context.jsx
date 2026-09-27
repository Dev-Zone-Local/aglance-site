import { createContext, useContext, useEffect, useState, useCallback } from "react";
import { api, ensureCsrf, ADMIN_URL } from "./api";

const AuthContext = createContext(null);

// Admins go to the Filament panel (full page load, served by Laravel); everyone else to the SPA dashboard.
export function goToAccountHome(user, navigate) {
  if (user?.role === "admin") {
    window.location.assign(ADMIN_URL);
  } else {
    navigate("/dashboard", { replace: true });
  }
}

// True when the admin has configured GitHub sign-in (Admin → Settings → GitHub login).
export function useGithubEnabled() {
  const [enabled, setEnabled] = useState(false);
  useEffect(() => {
    api.get("/auth/providers").then((r) => setEnabled(!!r.data?.github)).catch(() => {});
  }, []);
  return enabled;
}

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null); // null = unknown, false = anon, object = authed
  const [loading, setLoading] = useState(true);

  const refresh = useCallback(async () => {
    try {
      const { data } = await api.get("/auth/me");
      setUser(data);
      return data;
    } catch {
      setUser(false);
      return null;
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    refresh();
  }, [refresh]);

  const login = async (email, password) => {
    await ensureCsrf();
    const { data } = await api.post("/auth/login", { email, password });
    setUser(data);
    return data;
  };

  const register = async (email, password, name) => {
    await ensureCsrf();
    const { data } = await api.post("/auth/register", { email, password, name });
    setUser(data);
    return data;
  };

  const logout = async () => {
    try {
      await ensureCsrf();
      await api.post("/auth/logout");
    } catch {}
    setUser(false);
  };

  return (
    <AuthContext.Provider value={{ user, loading, login, register, logout, refresh, setUser }}>
      {children}
    </AuthContext.Provider>
  );
}

export const useAuth = () => useContext(AuthContext);
