import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { api } from "../lib/api";
import { Check, ArrowRight } from "lucide-react";

export default function Pricing() {
  const [plans, setPlans] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.get("/cms/pricing")
      .then((r) => setPlans(r.data || []))
      .finally(() => setLoading(false));
  }, []);

  return (
    <div data-testid="pricing-page" className="max-w-7xl mx-auto px-5 sm:px-8 py-16">
      <div className="text-xs font-medium text-ag-teal mb-4 text-center">Pricing</div>
      <h1 className="text-4xl sm:text-5xl lg:text-6xl font-medium tracking-heading text-zinc-50 leading-[1.05] mb-6 max-w-4xl mx-auto text-center">
        Free. Self-hosted. Forever.
      </h1>
      <p className="text-lg text-zinc-400 max-w-2xl leading-relaxed mx-auto text-center">
        AtGlance is free to use inside your own boundary. Enterprise plans add SLA-backed support and architecture reviews — talk to us when you need them.
      </p>

      {loading ? (
        <div className="mt-16 text-center text-zinc-500 font-mono">Loading plans…</div>
      ) : (
        <div className="grid md:grid-cols-2 gap-6 mt-16 max-w-5xl mx-auto">
          {plans.map((p) => (
            <div
              key={p.id}
              data-testid={`pricing-plan-${p.name.toLowerCase()}`}
              className={`relative rounded-2xl p-7 ${p.highlighted
                ? "border border-amber-500/40 bg-white gold-glow"
                : "bg-white shadow-ag"
              }`}
            >
              {p.highlighted && (
                <div className="absolute -top-3 left-7 text-[10px] uppercase tracking-[0.22em] font-mono px-2.5 py-1 rounded-full bg-ag-gradient text-ag-ink">
                  Most popular
                </div>
              )}
              <div className="text-xs font-medium text-ag-teal mb-3">{p.name}</div>
              <div className="flex items-baseline gap-2 mb-3">
                <span className="text-5xl font-medium tracking-heading text-zinc-100">{p.price}</span>
                {p.period && <span className="text-sm text-zinc-500">{p.period}</span>}
              </div>
              <p className="text-sm text-zinc-400 leading-relaxed mb-6">{p.description}</p>
              <ul className="space-y-3 mb-7">
                {p.features?.map((f, i) => (
                  <li key={i} className="flex items-start gap-2.5 text-sm text-zinc-300">
                    <Check size={16} className="text-amber-500 flex-shrink-0 mt-0.5" />
                    <span>{f}</span>
                  </li>
                ))}
              </ul>
              <Link
                to={p.highlighted ? "/register" : "/contact"}
                data-testid={`pricing-cta-${p.name.toLowerCase()}`}
                className={`inline-flex items-center justify-center gap-2 w-full px-5 py-3 rounded-md font-medium transition-colors ${
                  p.highlighted
                    ? "bg-ag-gradient text-ag-ink hover:bg-ag-gradient-hover hover:shadow-ag-glow"
                    : "border border-zinc-700 text-zinc-200 hover:bg-zinc-900"
                }`}
              >
                {p.cta} <ArrowRight size={14} />
              </Link>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
