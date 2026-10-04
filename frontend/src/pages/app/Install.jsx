import { useEffect, useState } from "react";
import { Link, useParams, useSearchParams } from "react-router-dom";
import { ArrowLeft, ArrowRight, Check, Download, ExternalLink, ShieldCheck } from "lucide-react";
import { api } from "../../lib/api";
import { PRODUCTS, buildSteps, findProduct } from "../../lib/installCatalog";
import { Badge, Button, Card, PageHeader } from "../../components/ag";
import { CodeBlock } from "../../components/Terminal";
import { Markdown } from "../../components/Markdown";

function Stepper({ current }) {
  const steps = ["Choose product", "Choose where", "Install"];
  return (
    <ol className="mb-6 flex flex-wrap items-center gap-2" aria-label="Progress">
      {steps.map((s, i) => (
        <li key={s} className="flex items-center gap-2">
          <span
            className={`inline-flex h-7 min-w-7 items-center justify-center rounded-full px-2 text-xs font-medium ${
              i < current ? "bg-ag-ink text-ag-mint" : i === current ? "bg-ag-gradient text-ag-ink shadow-ag-pill" : "bg-white text-ag-muted shadow-ag"
            }`}
          >
            {i < current ? <Check size={13} /> : i + 1}
          </span>
          <span className={`text-sm ${i === current ? "font-medium text-ag-ink" : "text-ag-muted"}`}>{s}</span>
          {i < steps.length - 1 && <span className="mx-1 h-px w-6 bg-ag-line" />}
        </li>
      ))}
    </ol>
  );
}

function ChoiceCard({ to, icon: Icon, title, hint, badge, disabled, testId }) {
  const inner = (
    <Card hover={!disabled} className={`relative h-full p-6 ${disabled ? "opacity-60" : ""}`} data-testid={testId}>
      {badge}
      <span className={`mb-4 inline-flex h-11 w-11 items-center justify-center rounded-[12px] ${disabled ? "bg-ag-surface text-ag-muted" : "bg-ag-ink text-ag-mint"}`}>
        <Icon size={20} />
      </span>
      <h3 className="text-[17px] font-medium tracking-heading text-ag-ink">{title}</h3>
      <p className="mt-1 text-sm text-ag-subtle">{hint}</p>
      {!disabled && (
        <span className="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-ag-teal">
          Continue <ArrowRight size={14} />
        </span>
      )}
    </Card>
  );
  return disabled ? <div aria-disabled="true">{inner}</div> : <Link to={to}>{inner}</Link>;
}

export default function Install() {
  const { product: productId, target: targetId } = useParams();
  const [params, setParams] = useSearchParams();
  const mode = params.get("mode") === "update" ? "update" : "install";
  const [dl, setDl] = useState(null);

  useEffect(() => {
    api.get("/downloads").then((r) => setDl(r.data)).catch(() => setDl({}));
  }, []);

  const product = findProduct(productId);
  const target = product?.targets.find((t) => t.id === targetId);

  // Step 1: product
  if (!product) {
    return (
      <div data-testid="install-page">
        <PageHeader eyebrow="Install" title="What do you want to install?">
          Pick a product. You can come back here any time to update or install on another machine.
        </PageHeader>
        <Stepper current={0} />
        <div className="grid gap-4 md:grid-cols-2">
          {PRODUCTS.map((p) => {
            const v = dl?.[p.id]?.latest?.version;
            return (
              <ChoiceCard
                key={p.id}
                to={`/dashboard/install/${p.id}`}
                icon={p.icon}
                title={p.name}
                hint={p.tagline}
                testId={`install-product-${p.id}`}
                badge={v && <Badge variant="success" className="absolute right-5 top-5">v{v}</Badge>}
              />
            );
          })}
        </div>
      </div>
    );
  }

  // Step 2: target
  if (!target || target.status !== "ready") {
    return (
      <div data-testid="install-page">
        <Link to="/dashboard/install" className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-ag-teal">
          <ArrowLeft size={14} /> All products
        </Link>
        <PageHeader eyebrow={product.name} title="Where do you want to run it?" />
        <Stepper current={1} />
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {product.targets.map((t) => (
            <ChoiceCard
              key={t.id}
              to={`/dashboard/install/${product.id}/${t.id}`}
              icon={t.icon}
              title={t.name}
              hint={t.hint}
              disabled={t.status !== "ready"}
              testId={`install-target-${t.id}`}
              badge={t.status !== "ready" && <Badge className="absolute right-5 top-5">Coming soon</Badge>}
            />
          ))}
        </div>
      </div>
    );
  }

  // Step 3: guided steps
  const steps = buildSteps(product.id, target.id, dl, mode);
  const version = dl?.[product.id]?.latest?.version;

  return (
    <div data-testid="install-page">
      <Link to={`/dashboard/install/${product.id}`} className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-ag-teal">
        <ArrowLeft size={14} /> Change platform
      </Link>
      <PageHeader
        eyebrow={`${product.name}${version ? ` · v${version}` : ""}`}
        title={mode === "update" ? `Update on ${target.name}` : `Install on ${target.name}`}
      />
      <Stepper current={2} />

      <div className="mb-5 inline-flex rounded-full bg-white p-[5px] shadow-ag" role="tablist" aria-label="Install or update">
        {[
          ["install", "New install"],
          ["update", "Update existing"],
        ].map(([key, text]) => (
          <button
            key={key}
            role="tab"
            aria-selected={mode === key}
            data-testid={`install-mode-${key}`}
            onClick={() => setParams(key === "update" ? { mode: "update" } : {}, { replace: true })}
            className={`rounded-full px-[18px] py-[9px] text-sm font-medium transition ${
              mode === key ? "bg-ag-gradient text-ag-ink shadow-ag-pill" : "text-ag-muted hover:bg-ag-surface hover:text-ag-ink"
            }`}
          >
            {text}
          </button>
        ))}
      </div>

      {dl === null ? (
        <Card className="text-sm text-ag-muted">Loading…</Card>
      ) : steps.length === 0 ? (
        <Card className="text-sm text-ag-subtle">Instructions for this platform are coming soon.</Card>
      ) : (
        <ol className="space-y-4">
          {steps.map((s, i) => (
            <li key={s.key} className="rise" style={{ animationDelay: `${i * 40}ms` }}>
              <Card className="p-6" data-testid={`install-step-${s.key}`}>
                <div className="flex gap-4">
                  <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-ag-ink text-sm font-medium text-ag-mint">
                    {i + 1}
                  </span>
                  <div className="min-w-0 flex-1 space-y-3">
                    <h3 className="pt-1.5 text-[17px] font-medium tracking-heading text-ag-ink">{s.title}</h3>
                    {s.text && <p className="text-sm leading-relaxed text-ag-subtle">{s.text}</p>}
                    {s.checksum && (
                      <div>
                        <div className="mb-1 flex items-center gap-1.5 text-xs font-medium text-ag-subtle">
                          <ShieldCheck size={13} className="text-ag-success" /> SHA-256
                        </div>
                        <code className="block break-all rounded-input bg-ag-surface px-3.5 py-2.5 font-mono text-xs text-ag-ink">{s.checksum}</code>
                      </div>
                    )}
                    {s.code && <CodeBlock title="bash" code={s.code} />}
                    {s.markdown && <Markdown source={s.markdown} />}
                    {(s.action || s.link) && (
                      <div className="flex flex-wrap gap-2">
                        {s.action && (
                          <Button href={s.action.href} target="_blank" rel="noreferrer" variant={s.action.variant || "primary"}>
                            <Download size={14} /> {s.action.label} <ExternalLink size={12} />
                          </Button>
                        )}
                        {s.link && (
                          <Button to={s.link.to} variant="ghost">
                            {s.link.label} <ArrowRight size={14} />
                          </Button>
                        )}
                      </div>
                    )}
                    {(s.key === "download" || s.key === "update-script") && !s.action && (
                      <p className="text-xs text-ag-muted">Download link not published yet.</p>
                    )}
                  </div>
                </div>
              </Card>
            </li>
          ))}
        </ol>
      )}
    </div>
  );
}
