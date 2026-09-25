/**
 * Journalisation structurée JSON (une ligne par événement) : collectée par
 * la pile d'observabilité du cluster (Loki, Elasticsearch…). Lisible en développement.
 */
type Level = 'debug' | 'info' | 'warn' | 'error';

const LEVELS: Record<Level, number> = { debug: 10, info: 20, warn: 30, error: 40 };
const minLevel = LEVELS[(process.env.LOG_LEVEL as Level) ?? (process.env.NODE_ENV === 'production' ? 'info' : 'debug')] ?? 20;
const pretty = process.env.NODE_ENV !== 'production';

function serializeError(err: unknown) {
  if (err instanceof Error) return { name: err.name, message: err.message, stack: err.stack };
  return err;
}

function write(level: Level, msg: string, fields?: Record<string, unknown>) {
  if (LEVELS[level] < minLevel) return;
  const entry: Record<string, unknown> = { time: new Date().toISOString(), level, msg, service: 'terricom' };
  if (fields) {
    for (const [k, v] of Object.entries(fields)) entry[k] = k === 'err' ? serializeError(v) : v;
  }
  const line = pretty
    ? `${entry.time} ${level.toUpperCase().padEnd(5)} ${msg}${fields ? ' ' + JSON.stringify(fields, (k, v) => (k === 'err' ? serializeError(v) : v)) : ''}`
    : JSON.stringify(entry);
  if (level === 'error' || level === 'warn') process.stderr.write(line + '\n');
  else process.stdout.write(line + '\n');
}

export const logger = {
  debug: (msg: string, fields?: Record<string, unknown>) => write('debug', msg, fields),
  info: (msg: string, fields?: Record<string, unknown>) => write('info', msg, fields),
  warn: (msg: string, fields?: Record<string, unknown>) => write('warn', msg, fields),
  error: (msg: string, fields?: Record<string, unknown>) => write('error', msg, fields),
};
