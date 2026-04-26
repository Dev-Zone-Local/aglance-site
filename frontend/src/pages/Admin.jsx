import { useEffect, useState } from "react";
import { NavLink, Routes, Route, Navigate } from "react-router-dom";
import { api, formatApiError } from "../lib/api";
import { useAuth } from "../lib/auth-context";
import { toast } from "sonner";
import { Plus, Trash2, Save, GripVertical, Layers, FileText, MessageSquareQuote, Mail, Download as DownloadIcon, BookOpen } from "lucide-react";

const NAV = [
  { to: "/admin/pricing", label: "Pricing", icon: Layers },
  { to: "/admin/docs", label: "Docs", icon: BookOpen },
  { to: "/admin/faqs", label: "FAQs", icon: MessageSquareQuote },
  { to: "/admin/contact", label: "Contact", icon: Mail },
  { to: "/admin/pages", label: "Pages", icon: FileText },
  { to: "/admin/downloads", label: "Downloads", icon: DownloadIcon },
];

export default function Admin() {
  const { user } = useAuth();
  return (
    <div data-testid="admin-page" className="max-w-7xl mx-auto px-5 sm:px-8 py-10 grid lg:grid-cols-[220px_1fr] gap-8">
      <aside className="lg:sticky lg:top-20 lg:self-start" data-testid="admin-sidebar">
        <div className="text-[11px] uppercase tracking-[0.22em] text-amber-500 font-mono mb-4 px-1">CMS</div>
        <div className="text-xs text-zinc-500 px-1 mb-4">{user?.email}</div>
        <nav className="space-y-1">
          {NAV.map((n) => (
            <NavLink
              key={n.to}
              to={n.to}
              data-testid={`admin-nav-${n.label.toLowerCase()}`}
              className={({ isActive }) =>
                `flex items-center gap-2.5 px-3 py-2 rounded-md text-sm transition-colors ${
                  isActive ? "bg-amber-500/10 text-amber-500" : "text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900"
                }`
              }
            >
              <n.icon size={14} />
              {n.label}
            </NavLink>
          ))}
        </nav>
      </aside>
      <div className="min-w-0">
        <Routes>
          <Route index element={<Navigate to="pricing" replace />} />
          <Route path="pricing" element={<AdminPricing />} />
          <Route path="docs" element={<AdminDocs />} />
          <Route path="faqs" element={<AdminFaqs />} />
          <Route path="contact" element={<AdminContact />} />
          <Route path="pages" element={<AdminPages />} />
          <Route path="downloads" element={<AdminDownloads />} />
        </Routes>
      </div>
    </div>
  );
}

function AdminHeader({ title, sub, onSave, saving }) {
  return (
    <div className="flex items-start justify-between mb-7">
      <div>
        <h2 className="text-2xl font-semibold tracking-tight text-zinc-100">{title}</h2>
        {sub && <p className="text-sm text-zinc-500 mt-1">{sub}</p>}
      </div>
      {onSave && (
        <button
          onClick={onSave}
          disabled={saving}
          data-testid="admin-save-btn"
          className="inline-flex items-center gap-2 bg-amber-500 text-zinc-950 px-4 py-2 rounded-md font-medium hover:bg-amber-400 disabled:opacity-60"
        >
          <Save size={14} /> {saving ? "Saving…" : "Save"}
        </button>
      )}
    </div>
  );
}

function Field({ label, value, onChange, textarea = false, placeholder, rows = 3, mono = false, testId }) {
  const cls = `w-full px-3 py-2 bg-zinc-950 border border-zinc-800 rounded-md text-zinc-100 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/30 ${mono ? "font-mono text-sm" : ""}`;
  return (
    <label className="block">
      <span className="text-[11px] uppercase tracking-[0.18em] text-zinc-500 font-mono">{label}</span>
      {textarea ? (
        <textarea data-testid={testId} value={value || ""} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} rows={rows} className={`${cls} mt-1`} />
      ) : (
        <input data-testid={testId} value={value || ""} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} className={`${cls} mt-1`} />
      )}
    </label>
  );
}

/* ---------- Pricing ---------- */
function AdminPricing() {
  const [plans, setPlans] = useState([]);
  const [saving, setSaving] = useState(false);
  useEffect(() => { api.get("/cms/pricing").then((r) => setPlans(r.data || [])); }, []);
  const update = (i, k, v) => setPlans((p) => p.map((x, idx) => idx === i ? { ...x, [k]: v } : x));
  const updateFeatures = (i, txt) => update(i, "features", txt.split("\n").map((s) => s.trim()).filter(Boolean));
  const add = () => setPlans([...plans, { name: "New plan", price: "$0", period: "forever", description: "", features: [], cta: "Get started", highlighted: false, order: plans.length }]);
  const remove = (i) => setPlans(plans.filter((_, idx) => idx !== i));
  const save = async () => {
    setSaving(true);
    try {
      const { data } = await api.put("/admin/pricing", plans);
      setPlans(data);
      toast.success("Pricing saved");
    } catch (e) { toast.error(formatApiError(e.response?.data?.detail) || "Save failed"); }
    finally { setSaving(false); }
  };
  return (
    <div data-testid="admin-pricing">
      <AdminHeader title="Pricing plans" sub="Plans shown on the public /pricing page." onSave={save} saving={saving} />
      <div className="space-y-4">
        {plans.map((p, i) => (
          <div key={i} className="rounded-xl border border-zinc-800 bg-[#101012] p-5">
            <div className="flex justify-between items-start mb-4">
              <div className="flex-1 grid sm:grid-cols-2 gap-4">
                <Field testId={`plan-${i}-name`} label="Name" value={p.name} onChange={(v) => update(i, "name", v)} />
                <Field testId={`plan-${i}-price`} label="Price" value={p.price} onChange={(v) => update(i, "price", v)} />
                <Field testId={`plan-${i}-period`} label="Period" value={p.period} onChange={(v) => update(i, "period", v)} />
                <Field testId={`plan-${i}-cta`} label="CTA label" value={p.cta} onChange={(v) => update(i, "cta", v)} />
              </div>
              <button onClick={() => remove(i)} className="ml-3 p-2 text-zinc-500 hover:text-red-400" data-testid={`plan-${i}-delete`}><Trash2 size={14} /></button>
            </div>
            <div className="mb-3">
              <Field testId={`plan-${i}-desc`} label="Description" value={p.description} onChange={(v) => update(i, "description", v)} textarea rows={2} />
            </div>
            <div className="mb-3">
              <Field testId={`plan-${i}-features`} label="Features (one per line)" value={(p.features || []).join("\n")} onChange={(v) => updateFeatures(i, v)} textarea rows={5} mono />
            </div>
            <label className="flex items-center gap-2 text-sm text-zinc-400">
              <input type="checkbox" checked={!!p.highlighted} onChange={(e) => update(i, "highlighted", e.target.checked)} />
              Highlight this plan
            </label>
          </div>
        ))}
        <button onClick={add} data-testid="add-plan-btn" className="inline-flex items-center gap-2 px-4 py-2 border border-zinc-700 rounded-md text-zinc-300 hover:bg-zinc-900">
          <Plus size={14} /> Add plan
        </button>
      </div>
    </div>
  );
}

/* ---------- FAQs ---------- */
function AdminFaqs() {
  const [items, setItems] = useState([]);
  const [saving, setSaving] = useState(false);
  useEffect(() => { api.get("/cms/faqs").then((r) => setItems(r.data || [])); }, []);
  const update = (i, k, v) => setItems((arr) => arr.map((x, idx) => idx === i ? { ...x, [k]: v } : x));
  const add = () => setItems([...items, { question: "New question", answer: "", category: "General", order: items.length }]);
  const remove = (i) => setItems(items.filter((_, idx) => idx !== i));
  const save = async () => {
    setSaving(true);
    try {
      const { data } = await api.put("/admin/faqs", items);
      setItems(data);
      toast.success("FAQs saved");
    } catch (e) { toast.error(formatApiError(e.response?.data?.detail) || "Save failed"); }
    finally { setSaving(false); }
  };
  return (
    <div data-testid="admin-faqs">
      <AdminHeader title="FAQ entries" sub="Shown on the public /faq page, grouped by category." onSave={save} saving={saving} />
      <div className="space-y-3">
        {items.map((f, i) => (
          <div key={i} className="rounded-xl border border-zinc-800 bg-[#101012] p-5">
            <div className="grid sm:grid-cols-2 gap-3 mb-3">
              <Field testId={`faq-${i}-q`} label="Question" value={f.question} onChange={(v) => update(i, "question", v)} />
              <Field testId={`faq-${i}-cat`} label="Category" value={f.category} onChange={(v) => update(i, "category", v)} />
            </div>
            <Field testId={`faq-${i}-a`} label="Answer" value={f.answer} onChange={(v) => update(i, "answer", v)} textarea rows={3} />
            <div className="flex justify-end mt-3">
              <button onClick={() => remove(i)} data-testid={`faq-${i}-delete`} className="text-zinc-500 hover:text-red-400 text-sm inline-flex items-center gap-1.5"><Trash2 size={14} /> Remove</button>
            </div>
          </div>
        ))}
        <button onClick={add} data-testid="add-faq-btn" className="inline-flex items-center gap-2 px-4 py-2 border border-zinc-700 rounded-md text-zinc-300 hover:bg-zinc-900">
          <Plus size={14} /> Add FAQ
        </button>
      </div>
    </div>
  );
}

/* ---------- Docs ---------- */
function AdminDocs() {
  const [items, setItems] = useState([]);
  const [saving, setSaving] = useState(false);
  const [activeIdx, setActiveIdx] = useState(0);
  useEffect(() => { api.get("/admin/docs/full").then((r) => setItems(r.data || [])); }, []);
  const update = (i, k, v) => setItems((arr) => arr.map((x, idx) => idx === i ? { ...x, [k]: v } : x));
  const add = () => {
    const next = [...items, { slug: `new-${Date.now()}`, title: "New doc", section: "Getting Started", content: "# New doc\n", order: items.length }];
    setItems(next); setActiveIdx(next.length - 1);
  };
  const remove = (i) => {
    const next = items.filter((_, idx) => idx !== i);
    setItems(next);
    setActiveIdx(Math.max(0, Math.min(activeIdx, next.length - 1)));
  };
  const save = async () => {
    setSaving(true);
    try {
      const { data } = await api.put("/admin/docs", { items });
      setItems(data);
      toast.success("Docs saved");
    } catch (e) { toast.error(formatApiError(e.response?.data?.detail) || "Save failed"); }
    finally { setSaving(false); }
  };
  const a = items[activeIdx];
  return (
    <div data-testid="admin-docs">
      <AdminHeader title="Documentation" sub="Markdown content shown under /docs/[slug]." onSave={save} saving={saving} />
      <div className="grid lg:grid-cols-[260px_1fr] gap-5">
        <div className="rounded-xl border border-zinc-800 bg-[#101012] p-3">
          <ul className="space-y-1 mb-3">
            {items.map((d, i) => (
              <li key={i}>
                <button
                  onClick={() => setActiveIdx(i)}
                  data-testid={`doc-list-${i}`}
                  className={`w-full text-left px-3 py-2 rounded-md text-sm flex items-center justify-between ${activeIdx === i ? "bg-amber-500/10 text-amber-500" : "text-zinc-300 hover:bg-zinc-900"}`}
                >
                  <span className="truncate">{d.title}</span>
                  <span className="text-[10px] font-mono text-zinc-500 ml-2">/{d.slug}</span>
                </button>
              </li>
            ))}
          </ul>
          <button onClick={add} data-testid="add-doc-btn" className="w-full inline-flex items-center justify-center gap-2 px-3 py-2 border border-zinc-700 rounded-md text-zinc-300 hover:bg-zinc-900 text-sm">
            <Plus size={14} /> Add doc
          </button>
        </div>
        {a ? (
          <div className="rounded-xl border border-zinc-800 bg-[#101012] p-5">
            <div className="grid sm:grid-cols-3 gap-3 mb-4">
              <Field testId="doc-title" label="Title" value={a.title} onChange={(v) => update(activeIdx, "title", v)} />
              <Field testId="doc-slug" label="Slug" value={a.slug} onChange={(v) => update(activeIdx, "slug", v.replace(/[^a-z0-9-]/gi, "-").toLowerCase())} />
              <Field testId="doc-section" label="Section" value={a.section} onChange={(v) => update(activeIdx, "section", v)} />
            </div>
            <Field testId="doc-content" label="Content (Markdown)" value={a.content} onChange={(v) => update(activeIdx, "content", v)} textarea rows={20} mono />
            <div className="flex justify-end mt-3">
              <button onClick={() => remove(activeIdx)} data-testid="doc-delete" className="text-zinc-500 hover:text-red-400 text-sm inline-flex items-center gap-1.5"><Trash2 size={14} /> Delete this doc</button>
            </div>
          </div>
        ) : (
          <div className="rounded-xl border border-zinc-800 bg-[#101012] p-10 text-center text-zinc-500 text-sm">No docs. Add one.</div>
        )}
      </div>
    </div>
  );
}

/* ---------- Contact ---------- */
function AdminContact() {
  const [c, setC] = useState({});
  const [saving, setSaving] = useState(false);
  useEffect(() => { api.get("/cms/contact").then((r) => setC(r.data || {})); }, []);
  const save = async () => {
    setSaving(true);
    try {
      const { data } = await api.put("/admin/contact", c);
      setC(data); toast.success("Contact saved");
    } catch (e) { toast.error(formatApiError(e.response?.data?.detail) || "Save failed"); }
    finally { setSaving(false); }
  };
  return (
    <div data-testid="admin-contact">
      <AdminHeader title="Contact info" sub="Shown on the /contact page and in the footer." onSave={save} saving={saving} />
      <div className="rounded-xl border border-zinc-800 bg-[#101012] p-5 grid sm:grid-cols-2 gap-3">
        <Field testId="contact-email" label="General email" value={c.email} onChange={(v) => setC({ ...c, email: v })} />
        <Field testId="contact-sales" label="Sales email" value={c.sales_email} onChange={(v) => setC({ ...c, sales_email: v })} />
        <Field testId="contact-support" label="Support email" value={c.support_email} onChange={(v) => setC({ ...c, support_email: v })} />
        <Field testId="contact-github" label="GitHub URL" value={c.github} onChange={(v) => setC({ ...c, github: v })} />
        <Field testId="contact-twitter" label="Twitter URL" value={c.twitter} onChange={(v) => setC({ ...c, twitter: v })} />
        <Field testId="contact-address" label="Address / tagline" value={c.address} onChange={(v) => setC({ ...c, address: v })} />
      </div>
    </div>
  );
}

/* ---------- Pages (Terms / Privacy / About) ---------- */
function AdminPages() {
  const [pages, setPages] = useState([]);
  const [active, setActive] = useState("about");
  const [saving, setSaving] = useState(false);
  useEffect(() => {
    api.get("/admin/pages").then((r) => {
      const arr = r.data || [];
      setPages(arr);
      if (arr.length && !arr.find((p) => p.slug === active)) setActive(arr[0].slug);
    });
  }, []); // eslint-disable-line
  const cur = pages.find((p) => p.slug === active);
  const update = (k, v) => setPages((arr) => arr.map((p) => (p.slug === active ? { ...p, [k]: v } : p)));
  const save = async () => {
    if (!cur) return;
    setSaving(true);
    try {
      await api.put(`/admin/pages/${active}`, cur);
      toast.success(`${cur.title || active} saved`);
    } catch (e) { toast.error(formatApiError(e.response?.data?.detail) || "Save failed"); }
    finally { setSaving(false); }
  };
  const ensure = (slug, title) => {
    if (!pages.find((p) => p.slug === slug)) {
      setPages([...pages, { slug, title, content: `# ${title}\n` }]);
    }
    setActive(slug);
  };
  return (
    <div data-testid="admin-pages">
      <AdminHeader title="Static pages" sub="About, Terms of Use, Privacy Policy." onSave={save} saving={saving} />
      <div className="flex gap-2 mb-5">
        {[["about", "About"], ["terms", "Terms"], ["privacy", "Privacy"]].map(([s, l]) => (
          <button
            key={s}
            onClick={() => ensure(s, l)}
            data-testid={`page-tab-${s}`}
            className={`px-3 py-1.5 rounded-md text-sm ${active === s ? "bg-amber-500 text-zinc-950" : "border border-zinc-800 text-zinc-300 hover:bg-zinc-900"}`}
          >
            {l}
          </button>
        ))}
      </div>
      {cur ? (
        <div className="rounded-xl border border-zinc-800 bg-[#101012] p-5">
          <Field testId="page-title" label="Page title" value={cur.title} onChange={(v) => update("title", v)} />
          <div className="mt-3">
            <Field testId="page-content" label="Content (Markdown)" value={cur.content} onChange={(v) => update("content", v)} textarea rows={20} mono />
          </div>
        </div>
      ) : (
        <div className="text-zinc-500 text-sm">No page selected.</div>
      )}
    </div>
  );
}

/* ---------- Downloads ---------- */
function AdminDownloads() {
  const [c, setC] = useState({});
  const [saving, setSaving] = useState(false);
  useEffect(() => {
    // Use the auth-gated endpoint (admin is logged in)
    api.get("/downloads").then((r) => setC(r.data || {}));
  }, []);
  const save = async () => {
    setSaving(true);
    try {
      const { data } = await api.put("/admin/downloads", c);
      setC(data); toast.success("Downloads saved");
    } catch (e) { toast.error(formatApiError(e.response?.data?.detail) || "Save failed"); }
    finally { setSaving(false); }
  };
  return (
    <div data-testid="admin-downloads">
      <AdminHeader title="Download artifacts" sub="URLs served to authenticated users on their dashboard." onSave={save} saving={saving} />
      <div className="rounded-xl border border-zinc-800 bg-[#101012] p-5 grid sm:grid-cols-2 gap-3">
        <Field testId="dl-cli-url" label="CLI download URL" value={c.cli_url} onChange={(v) => setC({ ...c, cli_url: v })} mono />
        <Field testId="dl-cli-version" label="CLI version" value={c.cli_version} onChange={(v) => setC({ ...c, cli_version: v })} />
        <Field testId="dl-console-url" label="Console download URL" value={c.console_url} onChange={(v) => setC({ ...c, console_url: v })} mono />
        <Field testId="dl-console-version" label="Console version" value={c.console_version} onChange={(v) => setC({ ...c, console_version: v })} />
        <div className="sm:col-span-2">
          <Field testId="dl-cli-install" label="Install command" value={c.cli_install_command} onChange={(v) => setC({ ...c, cli_install_command: v })} mono />
        </div>
      </div>
    </div>
  );
}
