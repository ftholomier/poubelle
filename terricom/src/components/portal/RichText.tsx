import type { ReactNode } from 'react';

/**
 * Texte mis en forme simplement par les professionnels : paragraphes, intertitres (## et ###),
 * listes (- ou *), gras (**texte**) et liens [texte](https://…). Aucun HTML n'est interprété.
 */
function inline(text: string, keyBase: string): ReactNode[] {
  const out: ReactNode[] = [];
  const re = /\*\*([^*]+)\*\*|\[([^\]]+)\]\(((?:https?:\/\/|mailto:|tel:)[^\s)]+)\)/g;
  let last = 0;
  let m: RegExpExecArray | null;
  let i = 0;
  while ((m = re.exec(text))) {
    if (m.index > last) out.push(text.slice(last, m.index));
    if (m[1]) out.push(<strong key={`${keyBase}-${i++}`}>{m[1]}</strong>);
    else
      out.push(
        <a
          key={`${keyBase}-${i++}`}
          href={m[3]}
          rel={m[3].startsWith('http') ? 'noopener noreferrer nofollow' : undefined}
          target={m[3].startsWith('http') ? '_blank' : undefined}
        >
          {m[2]}
        </a>,
      );
    last = m.index + m[0].length;
  }
  if (last < text.length) out.push(text.slice(last));
  return out;
}

export function RichText({ text, className, lang }: { text: string; className?: string; lang?: string }) {
  const blocks: ReactNode[] = [];
  const lines = text.replace(/\r\n?/g, '\n').split('\n');
  let para: string[] = [];
  let list: string[] = [];
  const flushPara = () => {
    if (para.length) blocks.push(<p key={`p${blocks.length}`}>{inline(para.join('\n'), `p${blocks.length}`)}</p>);
    para = [];
  };
  const flushList = () => {
    if (list.length)
      blocks.push(
        <ul key={`u${blocks.length}`}>
          {list.map((li, j) => (
            <li key={j}>{inline(li, `u${blocks.length}-${j}`)}</li>
          ))}
        </ul>,
      );
    list = [];
  };
  for (const raw of lines) {
    const line = raw.trimEnd();
    const h = /^(#{2,3})\s+(.+)$/.exec(line);
    const li = /^\s*[-*•]\s+(.+)$/.exec(line);
    if (!line.trim()) {
      flushPara();
      flushList();
    } else if (h) {
      flushPara();
      flushList();
      blocks.push(h[1].length === 2 ? <h2 key={`h${blocks.length}`}>{h[2]}</h2> : <h3 key={`h${blocks.length}`}>{h[2]}</h3>);
    } else if (li) {
      flushPara();
      list.push(li[1]);
    } else {
      flushList();
      para.push(line);
    }
  }
  flushPara();
  flushList();
  return (
    <div className={`rich-text${className ? ` ${className}` : ''}`} lang={lang}>
      {blocks}
    </div>
  );
}
