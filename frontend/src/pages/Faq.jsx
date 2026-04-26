import { useEffect, useState } from "react";
import { api } from "../lib/api";
import {
  Accordion, AccordionContent, AccordionItem, AccordionTrigger,
} from "../components/ui/accordion";

export default function Faq() {
  const [faqs, setFaqs] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.get("/cms/faqs")
      .then((r) => setFaqs(r.data || []))
      .finally(() => setLoading(false));
  }, []);

  const groups = faqs.reduce((acc, f) => {
    (acc[f.category] = acc[f.category] || []).push(f);
    return acc;
  }, {});

  return (
    <div data-testid="faq-page" className="max-w-4xl mx-auto px-5 sm:px-8 py-16">
      <div className="text-[11px] uppercase tracking-[0.22em] text-amber-500 font-mono mb-4">FAQ</div>
      <h1 className="text-4xl sm:text-5xl font-semibold tracking-tighter text-zinc-50 leading-[1.05] mb-6">
        Frequently asked.
      </h1>
      <p className="text-lg text-zinc-400 leading-relaxed mb-12">
        The short answers. For longer ones, see the <a href="/docs" className="text-amber-500">docs</a>.
      </p>

      {loading ? (
        <div className="text-zinc-500 font-mono">Loading…</div>
      ) : (
        <div className="space-y-10">
          {Object.entries(groups).map(([cat, items]) => (
            <div key={cat}>
              <div className="text-[11px] uppercase tracking-[0.22em] text-zinc-500 font-mono mb-3">{cat}</div>
              <Accordion type="single" collapsible className="rounded-xl border border-zinc-800 bg-[#101012] divide-y divide-zinc-800">
                {items.map((f) => (
                  <AccordionItem key={f.id} value={f.id} className="border-0 px-5">
                    <AccordionTrigger data-testid={`faq-q-${f.id}`} className="text-zinc-100 hover:no-underline text-left py-4">
                      {f.question}
                    </AccordionTrigger>
                    <AccordionContent data-testid={`faq-a-${f.id}`} className="text-zinc-400 pb-4 leading-relaxed">
                      {f.answer}
                    </AccordionContent>
                  </AccordionItem>
                ))}
              </Accordion>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
