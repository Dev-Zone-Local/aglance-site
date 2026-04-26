import { useEffect, useState } from "react";
import { Link, useParams, Navigate } from "react-router-dom";
import { api } from "../lib/api";
import { DocsSidebar } from "../components/DocsSidebar";
import { Markdown } from "../components/Markdown";
import { ArrowRight, BookOpen } from "lucide-react";

export function DocsLanding() {
  const [docs, setDocs] = useState([]);
  useEffect(() => {
    api.get("/cms/docs").then((r) => setDocs(r.data || []));
  }, []);
  const groups = docs.reduce((acc, d) => {
    (acc[d.section] = acc[d.section] || []).push(d);
    return acc;
  }, {});
  return (
    <div data-testid="docs-landing" className="max-w-7xl mx-auto px-5 sm:px-8 py-16 grid lg:grid-cols-[240px_1fr] gap-10">
      <DocsSidebar />
      <div>
        <div className="text-[11px] uppercase tracking-[0.22em] text-amber-500 font-mono mb-4">Documentation</div>
        <h1 className="text-4xl sm:text-5xl font-semibold tracking-tighter text-zinc-50 leading-[1.05] mb-4">
          Build, deploy, operate.
        </h1>
        <p className="text-lg text-zinc-400 max-w-2xl mb-12">
          Everything you need to install the CLI, deploy the Console, and run AtGlance in production.
        </p>
        <div className="space-y-10">
          {Object.entries(groups).map(([section, items]) => (
            <div key={section}>
              <div className="text-[11px] uppercase tracking-[0.22em] text-zinc-500 font-mono mb-3">{section}</div>
              <div className="grid sm:grid-cols-2 gap-4">
                {items.map((d) => (
                  <Link
                    key={d.slug}
                    to={`/docs/${d.slug}`}
                    data-testid={`docs-card-${d.slug}`}
                    className="lift rounded-xl border border-zinc-800 bg-[#101012] p-5 group"
                  >
                    <BookOpen size={16} className="text-amber-500 mb-3" />
                    <div className="font-medium text-zinc-100 mb-1">{d.title}</div>
                    <div className="text-xs text-zinc-500 inline-flex items-center gap-1.5 group-hover:gap-2.5 transition-all">
                      Read <ArrowRight size={12} />
                    </div>
                  </Link>
                ))}
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

export function DocPage() {
  const { slug } = useParams();
  const [doc, setDoc] = useState(null);
  const [error, setError] = useState(false);
  useEffect(() => {
    setError(false);
    api.get(`/cms/docs/${slug}`)
      .then((r) => setDoc(r.data))
      .catch(() => setError(true));
  }, [slug]);
  if (error) return <Navigate to="/docs" replace />;
  return (
    <div data-testid={`doc-page-${slug}`} className="max-w-7xl mx-auto px-5 sm:px-8 py-16 grid lg:grid-cols-[240px_1fr] gap-10">
      <DocsSidebar />
      <div className="min-w-0">
        {!doc ? (
          <div className="text-zinc-500 font-mono">Loading…</div>
        ) : (
          <article className="max-w-3xl">
            <div className="text-[11px] uppercase tracking-[0.22em] text-amber-500 font-mono mb-3">{doc.section}</div>
            <Markdown source={doc.content} />
          </article>
        )}
      </div>
    </div>
  );
}
