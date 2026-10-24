import { useState } from 'react';
import { api, signIn } from '../api';
import { Err, Field, FormModal, Loading, Top, label, peso, useLoad, useToast } from '../ui';

export function Login({ onUser }) {
  const [email, setEmail] = useState(''); const [pw, setPw] = useState(''); const [err, setErr] = useState(''); const [busy, setBusy] = useState(false);
  const go = async (e) => {
    e.preventDefault(); setBusy(true); setErr('');
    try { onUser(await signIn(email, pw)); } catch (x) { setErr(x.message); setBusy(false); }
  };
  return (
    <div className="login">
      <form className="card" onSubmit={go}>
        <div className="brand">HOTEL<span>GO</span></div>
        <p className="muted" style={{ marginTop: 0 }}>Staff console. Sign in with your front-desk or owner account.</p>
        <Err text={err} />
        <Field label="Email"><input type="email" value={email} onChange={(e) => setEmail(e.target.value)} autoComplete="username" required autoFocus /></Field>
        <Field label="Password"><input type="password" value={pw} onChange={(e) => setPw(e.target.value)} autoComplete="current-password" required /></Field>
        <button className="btn" style={{ width: '100%' }} disabled={busy}>{busy ? 'Signing in…' : 'Sign in'}</button>
        <p style={{ textAlign: 'center', marginBottom: 0 }}><a href="#/availability">Check room availability</a></p>
      </form>
    </div>
  );
}

/* Shown to a new staff account (temporary password) and from the menu */
export function ChangePassword({ forced, onDone }) {
  const [cur, setCur] = useState(''); const [pw, setPw] = useState(''); const [pw2, setPw2] = useState('');
  const [err, setErr] = useState(''); const [busy, setBusy] = useState(false); const toast = useToast();
  const go = async (e) => {
    e.preventDefault(); setErr('');
    if (pw !== pw2) return setErr('The two new passwords are not the same.');
    setBusy(true);
    try { await api('POST', '/me/password', { current: cur, password: pw }); toast('Password updated.'); setCur(''); setPw(''); setPw2(''); onDone && onDone(); }
    catch (x) { setErr(x.message); }
    setBusy(false);
  };
  const form = (
    <form className="card" onSubmit={go} style={{ maxWidth: 440 }}>
      <h2>{forced ? 'Choose a new password' : 'Change password'}</h2>
      {forced && <p className="muted">Your account uses a temporary password. Please choose your own before you continue.</p>}
      <Err text={err} />
      <Field label="Current password"><input type="password" value={cur} onChange={(e) => setCur(e.target.value)} autoComplete="current-password" required /></Field>
      <Field label="New password (8+ characters, letters and numbers)"><input type="password" value={pw} onChange={(e) => setPw(e.target.value)} autoComplete="new-password" required /></Field>
      <Field label="New password again"><input type="password" value={pw2} onChange={(e) => setPw2(e.target.value)} autoComplete="new-password" required /></Field>
      <button className="btn" disabled={busy}>{busy ? 'Saving…' : 'Save password'}</button>
    </form>
  );
  return forced ? <div className="login">{form}</div> : <><Top title="My account" />{form}</>;
}

/* Public: anybody can see which room types are free for a time (no sign-in) */
export function Availability({ signedIn }) {
  const tomorrow = new Date(Date.now() + 86400000).toISOString().slice(0, 10);
  const [q, setQ] = useState({ stay: 'hourly', date: tomorrow, time: '19:00', hours: 3, guests: 2 });
  const [run, setRun] = useState(q);
  const res = useLoad(() => api('GET', '/availability?' + new URLSearchParams(run)), [JSON.stringify(run)]);
  const set = (k) => (e) => setQ({ ...q, [k]: e.target.value });
  const body = (
    <>
      <form className="card" onSubmit={(e) => { e.preventDefault(); setRun(q); }} style={{ marginBottom: 16 }}>
        <div className="grid2" style={{ gridTemplateColumns: 'repeat(auto-fit,minmax(150px,1fr))' }}>
          <Field label="Stay"><select value={q.stay} onChange={set('stay')}><option value="hourly">Hourly</option><option value="overnight">Overnight</option></select></Field>
          <Field label="Date"><input type="date" value={q.date} onChange={set('date')} min={new Date().toISOString().slice(0, 10)} /></Field>
          <Field label="Start time"><input type="time" value={q.time} onChange={set('time')} /></Field>
          {q.stay === 'hourly' && <Field label="Hours"><select value={q.hours} onChange={set('hours')}><option>3</option><option>6</option><option>12</option></select></Field>}
          <Field label="Guests"><input type="number" min="1" max="10" value={q.guests} onChange={set('guests')} /></Field>
        </div>
        <button className="btn">Search</button>
      </form>
      <Loading state={res} />
      {res.data && (
        <div className="cards">
          {res.data.data.length === 0 && <div className="card empty">No room type fits that many guests.</div>}
          {res.data.data.map((t) => (
            <div className="card" key={t.id}>
              <h3>{t.name}</h3>
              <p className="muted">{t.description}</p>
              <p><b>{peso(t.price)}</b><br /><span className="muted">{t.free > 0 ? t.free + ' room(s) free' : 'Fully booked for that time'}</span></p>
            </div>
          ))}
        </div>
      )}
    </>
  );
  return signedIn ? <><Top title="Availability" sub="What a guest would see for a chosen time." />{body}</>
    : <div className="login" style={{ alignItems: 'start' }}><div style={{ width: '100%', maxWidth: 900 }}><div className="row" style={{ justifyContent: 'space-between', color: '#fff', marginBottom: 12 }}><h1>Room availability</h1><a style={{ color: '#fff' }} href="#/">Staff sign in</a></div>{body}</div></div>;
}
export { label };
