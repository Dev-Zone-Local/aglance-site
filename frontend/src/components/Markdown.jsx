/**
 * Tiny markdown renderer (no external deps).
 * Supports: headings (# ## ###), paragraphs, bold (**), italic (_), inline code (`),
 * fenced code blocks (```lang ... ```), unordered/ordered lists, links, hr, blockquote, tables.
 */
import { useMemo } from "react";

function escape(s) {
  return s
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");
}

function inline(s) {
  s = escape(s);
  s = s.replace(/`([^`]+)`/g, "<code>$1</code>");
  s = s.replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");
  s = s.replace(/\b_([^_]+)_\b/g, "<em>$1</em>");
  s = s.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noreferrer">$1</a>');
  return s;
}

function renderTable(lines) {
  const rows = lines.map((l) => l.replace(/^\||\|$/g, "").split("|").map((c) => c.trim()));
  if (rows.length < 2) return "";
  const head = rows[0];
  const body = rows.slice(2);
  const th = head.map((h) => `<th>${inline(h)}</th>`).join("");
  const trs = body.map((r) => `<tr>${r.map((c) => `<td>${inline(c)}</td>`).join("")}</tr>`).join("");
  return `<table><thead><tr>${th}</tr></thead><tbody>${trs}</tbody></table>`;
}

function mdToHtml(md = "") {
  const lines = md.split(/\r?\n/);
  const out = [];
  let i = 0;
  while (i < lines.length) {
    const l = lines[i];

    // Fenced code
    const fence = l.match(/^```(\w+)?\s*$/);
    if (fence) {
      const lang = fence[1] || "";
      const buf = [];
      i++;
      while (i < lines.length && !/^```\s*$/.test(lines[i])) { buf.push(lines[i]); i++; }
      i++;
      out.push(`<pre data-lang="${lang}"><code>${escape(buf.join("\n"))}</code></pre>`);
      continue;
    }

    // Heading
    const h = l.match(/^(#{1,6})\s+(.*)$/);
    if (h) {
      const lvl = h[1].length;
      out.push(`<h${lvl}>${inline(h[2])}</h${lvl}>`);
      i++; continue;
    }

    // hr
    if (/^---+\s*$/.test(l)) { out.push("<hr/>"); i++; continue; }

    // Blockquote
    if (l.startsWith("> ")) {
      const buf = [];
      while (i < lines.length && lines[i].startsWith("> ")) { buf.push(lines[i].slice(2)); i++; }
      out.push(`<blockquote>${inline(buf.join(" "))}</blockquote>`);
      continue;
    }

    // Table
    if (l.startsWith("|") && i + 1 < lines.length && /^\|[-:|\s]+\|/.test(lines[i + 1])) {
      const buf = [];
      while (i < lines.length && lines[i].startsWith("|")) { buf.push(lines[i]); i++; }
      out.push(renderTable(buf));
      continue;
    }

    // Lists
    if (/^[-*]\s+/.test(l)) {
      const buf = [];
      while (i < lines.length && /^[-*]\s+/.test(lines[i])) { buf.push(lines[i].replace(/^[-*]\s+/, "")); i++; }
      out.push(`<ul>${buf.map((x) => `<li>${inline(x)}</li>`).join("")}</ul>`);
      continue;
    }
    if (/^\d+\.\s+/.test(l)) {
      const buf = [];
      while (i < lines.length && /^\d+\.\s+/.test(lines[i])) { buf.push(lines[i].replace(/^\d+\.\s+/, "")); i++; }
      out.push(`<ol>${buf.map((x) => `<li>${inline(x)}</li>`).join("")}</ol>`);
      continue;
    }

    // Paragraph
    if (l.trim() === "") { i++; continue; }
    const buf = [];
    while (i < lines.length && lines[i].trim() !== "" && !/^(#{1,6}\s|```|>\s|\||[-*]\s|\d+\.\s|---)/.test(lines[i])) {
      buf.push(lines[i]); i++;
    }
    out.push(`<p>${inline(buf.join(" "))}</p>`);
  }
  return out.join("\n");
}

export function Markdown({ source = "", className = "" }) {
  const html = useMemo(() => mdToHtml(source), [source]);
  return <div className={`prose-atg ${className}`} dangerouslySetInnerHTML={{ __html: html }} />;
}
