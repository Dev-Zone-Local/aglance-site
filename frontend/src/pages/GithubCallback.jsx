import { useEffect, useRef, useState } from "react";
import { useNavigate, useSearchParams } from "react-router-dom";
import { api, formatApiError } from "../lib/api";
import { useAuth } from "../lib/auth-context";

export default function GithubCallback() {
  const [params] = useSearchParams();
  const nav = useNavigate();
  const { setUser } = useAuth();
  const [err, setErr] = useState(null);
  const ran = useRef(false);

  useEffect(() => {
    if (ran.current) return;
    ran.current = true;
    const code = params.get("code");
    const state = params.get("state");
    if (!code || !state) {
      setErr("Missing OAuth code/state");
      return;
    }
    api
      .post(`/auth/github/callback?code=${encodeURIComponent(code)}&state=${encodeURIComponent(state)}`)
      .then((r) => {
        setUser(r.data);
        nav(r.data.role === "admin" ? "/admin" : "/dashboard", { replace: true });
      })
      .catch((e) => setErr(formatApiError(e.response?.data?.detail) || e.message));
  }, [params, nav, setUser]);

  return (
    <div className="min-h-[60vh] flex items-center justify-center" data-testid="github-callback">
      <div className="text-center">
        {err ? (
          <>
            <div className="text-red-400 font-mono mb-3">GitHub sign-in failed</div>
            <div className="text-zinc-500 text-sm">{err}</div>
            <button onClick={() => nav("/login")} className="mt-6 px-4 py-2 bg-amber-500 text-zinc-950 rounded-md">Back to login</button>
          </>
        ) : (
          <div className="text-zinc-500 font-mono">Authenticating with GitHub…</div>
        )}
      </div>
    </div>
  );
}
