export function ArchDiagram({ src, alt, caption, testId }) {
  return (
    <figure className="relative rounded-2xl overflow-hidden border border-zinc-800 bg-[#0c0c0e]" data-testid={testId}>
      <img src={src} alt={alt} className="w-full h-auto block" loading="lazy" />
      {caption && (
        <figcaption className="px-5 py-3 border-t border-zinc-800 bg-zinc-950/60 text-xs text-zinc-500 font-mono uppercase tracking-[0.18em]">
          {caption}
        </figcaption>
      )}
    </figure>
  );
}
