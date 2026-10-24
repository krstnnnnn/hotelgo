import { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react';

export const peso = (n) => '₱' + Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
export const when = (s) => {
  if (!s) return '';
  const d = new Date(String(s).replace(' ', 'T'));
  return isNaN(d) ? s : d.toLocaleString('en-PH', { day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit' });
};
export const label = (s) => String(s || '').replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase());
export const stay = (b) => (b.stay_type === 'overnight' ? 'Overnight' : b.hours + ' hours');

export function Pill({ v, text }) { return <span className={'pill ' + v}>{text || label(v)}</span>; }

/* run an async loader; gives data / error / loading and a reload() */
export function useLoad(fn, deps = []) {
  const [state, set] = useState({ data: null, error: '', loading: true });
  const [n, setN] = useState(0);
  const fnRef = useRef(fn); fnRef.current = fn;
  useEffect(() => {
    let alive = true;
    set((s) => ({ ...s, loading: true, error: '' }));
    fnRef.current().then((data) => alive && set({ data, error: '', loading: false }), (e) => alive && set({ data: null, error: e.message, loading: false }));
    return () => { alive = false; };
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [...deps, n]);
  return { ...state, reload: useCallback(() => setN((x) => x + 1), []) };
}

/* little pop-up messages */
const ToastCtx = createContext(() => {});
export const useToast = () => useContext(ToastCtx);
export function Toasts({ children }) {
  const [list, setList] = useState([]);
  const push = useCallback((text, kind = 'ok') => {
    const id = Math.random();
    setList((l) => [...l, { id, text, kind }]);
    setTimeout(() => setList((l) => l.filter((t) => t.id !== id)), 4500);
  }, []);
  return (
    <ToastCtx.Provider value={push}>
      {children}
      <div className="toasts" role="status" aria-live="polite">{list.map((t) => <div key={t.id} className={'toast ' + t.kind}>{t.text}</div>)}</div>
    </ToastCtx.Provider>
  );
}

export function Modal({ title, onClose, wide, children }) {
  useEffect(() => {
    const k = (e) => e.key === 'Escape' && onClose();
    window.addEventListener('keydown', k);
    return () => window.removeEventListener('keydown', k);
  }, [onClose]);
  return (
    <div className="overlay" onMouseDown={(e) => e.target === e.currentTarget && onClose()}>
      <div className={'modal' + (wide ? ' wide' : '')} role="dialog" aria-modal="true" aria-label={title}>
        <header><h2>{title}</h2><button className="x" onClick={onClose} aria-label="Close">×</button></header>
        {children}
      </div>
    </div>
  );
}

export function Field({ label: text, children }) { return <label className="f"><span>{text}</span>{children}</label>; }

export function Err({ text }) { return text ? <div className="err" role="alert">{text}</div> : null; }

export function Loading({ state }) {
  if (state.loading && !state.data) return <div className="empty">Loading…</div>;
  if (state.error) return <Err text={state.error} />;
  return null;
}

/* "Are you sure?" pop-up. onYes returns a promise; errors are shown inside the pop-up */
export function Confirm({ title, text, yes = 'Delete', danger = true, onYes, onClose }) {
  const [busy, setBusy] = useState(false); const [err, setErr] = useState('');
  const go = async () => {
    setBusy(true); setErr('');
    try { await onYes(); onClose(); } catch (e) { setErr(e.message); setBusy(false); }
  };
  return (
    <Modal title={title} onClose={onClose}>
      <p>{text}</p><Err text={err} />
      <div className="row"><button className={'btn' + (danger ? ' danger' : '')} disabled={busy} onClick={go}>{busy ? 'Please wait…' : yes}</button><button className="btn ghost" onClick={onClose}>Cancel</button></div>
    </Modal>
  );
}

/* a form inside a pop-up: calls onSave(), shows the error, closes when it works */
export function FormModal({ title, onClose, onSave, save = 'Save', wide, children }) {
  const [busy, setBusy] = useState(false); const [err, setErr] = useState('');
  const submit = async (e) => {
    e.preventDefault(); setBusy(true); setErr('');
    try { await onSave(); onClose(); } catch (x) { setErr(x.message); setBusy(false); }
  };
  return (
    <Modal title={title} onClose={onClose} wide={wide}>
      <form onSubmit={submit}>
        <Err text={err} />
        {children}
        <div className="row"><button className="btn" disabled={busy}>{busy ? 'Saving…' : save}</button><button type="button" className="btn ghost" onClick={onClose}>Cancel</button></div>
      </form>
    </Modal>
  );
}

export function Table({ head, rows, empty = 'Nothing here yet.' }) {
  return (
    <div className="scroll">
      <table>
        <thead><tr>{head.map((h) => <th key={h}>{h}</th>)}</tr></thead>
        <tbody>{rows}</tbody>
      </table>
      {React_isEmpty(rows) && <div className="empty">{empty}</div>}
    </div>
  );
}
const React_isEmpty = (rows) => Array.isArray(rows) && rows.length === 0;

export function Top({ title, sub, children }) {
  return <div className="top"><div><h1>{title}</h1>{sub && <p>{sub}</p>}</div><div className="row">{children}</div></div>;
}
