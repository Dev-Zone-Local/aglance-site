import { useEffect, useState } from "react";
import { useParams } from "react-router-dom";
import { api } from "../lib/api";
import { Markdown } from "../components/Markdown";

export function StaticPage() {
  const { slug = "about" } = useParams();
  const [page, setPage] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    setLoading(true); setError(null);
    api.get(`/cms/pages/${slug}`)
      .then((r) => setPage(r.data))
      .catch(() => setError("Page not found"))
      .finally(() => setLoading(false));
  }, [slug]);

  return (
    <div data-testid={`static-${slug}`} className="max-w-3xl mx-auto px-5 sm:px-8 py-16">
      {loading ? (
        <div className="text-zinc-500 font-mono">Loading…</div>
      ) : error ? (
        <div className="text-zinc-500">{error}</div>
      ) : (
        <Markdown source={page?.content || ""} />
      )}
    </div>
  );
}

export function PageBySlug({ slug }) {
  // explicit slug (used by Terms/Privacy/About direct routes)
  const [page, setPage] = useState(null);
  const [loading, setLoading] = useState(true);
  useEffect(() => {
    setLoading(true);
    api.get(`/cms/pages/${slug}`)
      .then((r) => setPage(r.data))
      .catch(() => setPage(null))
      .finally(() => setLoading(false));
  }, [slug]);
  return (
    <div data-testid={`static-${slug}`} className="max-w-3xl mx-auto px-5 sm:px-8 py-16">
      {loading ? (
        <div className="text-zinc-500 font-mono">Loading…</div>
      ) : page ? (
        <Markdown source={page.content || ""} />
      ) : (
        <div className="text-zinc-500">Not found.</div>
      )}
    </div>
  );
}

export default StaticPage;
