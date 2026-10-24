import { useState } from 'react';
import { api } from '../api';
import { Confirm, Err, Field, FormModal, Loading, Modal, Pill, Table, Top, label, peso, useLoad, useToast, when } from '../ui';

export function Staff({ user }) {
  const list = useLoad(() => api('GET', '/staff'));
  const [edit, setEdit] = useState(null); const [del, setDel] = useState(null); const [made, setMade] = useState(null); const toast = useToast();
  return (
    <>
      <Top title="Staff & roles" sub="Front-desk accounts. New accounts get a temporary password."><button className="btn" onClick={() => setEdit({})}>+ New staff</button></Top>
      <div className="card">
        <Loading state={list} />
        {list.data && <Table head={['Name', 'Email', 'Mobile', 'Role', 'Active', 'Last sign-in', '']} empty="No staff."
          rows={list.data.data.map((u) => (
            <tr key={u.id}><td><b>{u.full_name}</b></td><td>{u.email}</td><td>{u.mobile}</td><td>{label(u.role)}</td><td><Pill v={Number(u.is_active) ? 'active' : 'off'} text={Number(u.is_active) ? 'Yes' : 'Disabled'} /></td><td>{when(u.last_login_at) || '—'}</td>
              <td className="row">{u.role === 'staff' && <>
                <button className="btn small" onClick={() => setEdit(u)}>Edit</button>
                <button className="btn small ghost" onClick={async () => { try { await api('PUT', '/staff/' + u.id, { is_active: Number(u.is_active) ? 0 : 1 }); list.reload(); } catch (e) { toast(e.message, 'bad'); } }}>{Number(u.is_active) ? 'Disable' : 'Enable'}</button>
                <button className="btn small danger" onClick={() => setDel(u)}>Delete</button></>}</td></tr>
          ))} />}
      </div>
      {edit && <StaffForm u={edit} onClose={() => setEdit(null)} onSaved={(temp) => { list.reload(); if (temp) setMade(temp); }} />}
      {made && <Modal title="Account created" onClose={() => setMade(null)}><p>Give this temporary password to the staff member. <b>It is shown only once.</b> They must change it at first sign-in.</p><p><code style={{ fontSize: 18 }}>{made}</code></p><button className="btn" onClick={() => setMade(null)}>Done</button></Modal>}
      {del && <Confirm title={'Delete ' + del.full_name} text="This removes the staff account. It cannot be undone." onClose={() => setDel(null)}
        onYes={async () => { await api('DELETE', '/staff/' + del.id); toast('Staff account deleted.'); list.reload(); }} />}
    </>
  );
}

function StaffForm({ u, onClose, onSaved }) {
  const [f, setF] = useState({ full_name: u.full_name || '', email: u.email || '', mobile: u.mobile || '' });
  const set = (k) => (e) => setF({ ...f, [k]: e.target.value });
  const save = async () => {
    if (u.id) { await api('PUT', '/staff/' + u.id, { full_name: f.full_name, mobile: f.mobile }); onSaved(); }
    else { const r = await api('POST', '/staff', f); onSaved(r.temporary_password); }
  };
  return (
    <FormModal title={u.id ? 'Edit staff' : 'New staff account'} onClose={onClose} onSave={save}>
      <Field label="Full name"><input value={f.full_name} onChange={set('full_name')} required /></Field>
      <Field label="Email"><input type="email" value={f.email} onChange={set('email')} required disabled={!!u.id} /></Field>
      <Field label="Mobile"><input value={f.mobile} onChange={set('mobile')} required /></Field>
    </FormModal>
  );
}

export function Payments() {
  const list = useLoad(() => api('GET', '/payments')); const [ref, setRef] = useState(null); const toast = useToast();
  const x = list.data;
  return (
    <>
      <Top title="Payments" sub="Payment records are kept for the books: they can be refunded, never deleted." />
      <Loading state={list} />
      {x && <>
        <div className="cards">
          <div className="card stat"><b>{peso(x.paid_today)}</b><span>Paid today</span></div>
          <div className="card stat"><b>{peso(x.refunded)}</b><span>Refunded (all time)</span></div>
          <div className="card stat"><b>{x.waiting}</b><span>Waiting for payment</span></div>
        </div>
        <div className="card"><Table head={['Reference', 'Booking', 'Guest', 'Method', 'Amount', 'Status', 'Paid', '']} empty="No payments yet."
          rows={x.data.map((p) => <tr key={p.id}><td>{p.txn_ref}</td><td>{p.booking_ref}</td><td>{p.guest_name}</td><td>{p.method}</td><td>{peso(p.amount)}</td><td><Pill v={p.status} /></td><td>{when(p.paid_at)}</td>
            <td>{['paid', 'refund_pending'].includes(p.status) && <button className="btn small danger" onClick={() => setRef(p)}>Refund</button>}</td></tr>)} /></div>
      </>}
      {ref && <Confirm title="Refund payment" yes="Refund" text={'Refund ' + peso(ref.amount) + ' for booking ' + ref.booking_ref + '? The booking will be cancelled.'} onClose={() => setRef(null)}
        onYes={async () => { const r = await api('POST', '/payments/' + ref.id + '/refund'); toast(r.message); list.reload(); }} />}
    </>
  );
}

export function Notifications() {
  const list = useLoad(() => api('GET', '/notifications')); const [del, setDel] = useState(null); const [view, setView] = useState(null); const toast = useToast();
  return (
    <>
      <Top title="Notifications" sub="Every SMS and email the system sent (or only logged in simulation mode)." />
      <div className="card"><Loading state={list} />
        {list.data && <Table head={['When', 'Channel', 'To', 'Status', '']} empty="No messages yet."
          rows={list.data.data.map((n) => <tr key={n.id}><td>{when(n.created_at)}</td><td>{n.channel}</td><td>{n.recipient}</td><td><Pill v={n.status} />{n.error && <div className="muted">{n.error}</div>}</td>
            <td className="row"><button className="btn small ghost" onClick={() => setView(n)}>Read</button>
              {n.status === 'failed' && <button className="btn small" onClick={async () => { try { const r = await api('POST', '/notifications/' + n.id + '/retry'); toast(r.message); list.reload(); } catch (e) { toast(e.message, 'bad'); } }}>Retry</button>}
              <button className="btn small danger" onClick={() => setDel(n)}>Delete</button></td></tr>)} />}
      </div>
      {view && <Modal title={view.subject || 'Message'} onClose={() => setView(null)}><p className="muted">{view.channel} to {view.recipient}</p><pre style={{ whiteSpace: 'pre-wrap', fontFamily: 'inherit' }}>{view.message.length > 3000 ? view.message.slice(0, 3000) + '…' : view.message}</pre></Modal>}
      {del && <Confirm title="Delete notification" text="Remove this message from the log?" onClose={() => setDel(null)}
        onYes={async () => { await api('DELETE', '/notifications/' + del.id); toast('Deleted.'); list.reload(); }} />}
    </>
  );
}

export function Reports() {
  const today = new Date().toISOString().slice(0, 10); const week = new Date(Date.now() - 6 * 86400000).toISOString().slice(0, 10);
  const [r, setR] = useState({ from: week, to: today }); const [run, setRun] = useState(r);
  const d = useLoad(() => api('GET', '/reports?' + new URLSearchParams(run)), [JSON.stringify(run)]);
  const x = d.data;
  const days = x ? Object.entries(x.byDay) : []; const max = Math.max(1, ...days.map(([, v]) => v.hourly + v.overnight));
  return (
    <>
      <Top title="Reports" sub="Revenue from paid bookings." />
      <form className="card row" style={{ marginBottom: 16 }} onSubmit={(e) => { e.preventDefault(); setRun(r); }}>
        <Field label="From"><input type="date" value={r.from} onChange={(e) => setR({ ...r, from: e.target.value })} /></Field>
        <Field label="To"><input type="date" value={r.to} onChange={(e) => setR({ ...r, to: e.target.value })} /></Field>
        <button className="btn" style={{ marginBottom: 12 }}>Show</button>
      </form>
      <Loading state={d} />
      {x && <>
        <div className="cards">
          <div className="card stat"><b>{peso(x.total)}</b><span>Revenue</span></div>
          <div className="card stat"><b>{x.count}</b><span>Paid bookings</span></div>
          <div className="card stat"><b>{x.avgHours}</b><span>Average hours (hourly stays)</span></div>
        </div>
        <div className="card" style={{ marginBottom: 16 }}>
          <h2 style={{ marginBottom: 10 }}>Revenue per day</h2>
          <div className="bars" aria-label="Revenue per day">{days.map(([day, v]) => { const t = v.hourly + v.overnight; return <div key={day} title={day + ': ' + peso(t)}><i style={{ height: (t / max) * 100 + '%' }} />{day.slice(5)}</div>; })}</div>
        </div>
        <div className="grid2" style={{ gap: 16 }}>
          <div className="card"><h2 style={{ marginBottom: 8 }}>By stay</h2><Table head={['Stay', 'Revenue']} rows={Object.entries(x.byStay).map(([k, v]) => <tr key={k}><td>{label(k)}</td><td>{peso(v)}</td></tr>)} /></div>
          <div className="card"><h2 style={{ marginBottom: 8 }}>By room type</h2><Table head={['Room type', 'Revenue']} empty="No sales." rows={Object.entries(x.byType).map(([k, v]) => <tr key={k}><td>{k}</td><td>{peso(v)}</td></tr>)} /></div>
        </div>
      </>}
    </>
  );
}

const FIELDS = [['motel_name', 'Motel name'], ['motel_address', 'Address'], ['motel_lat', 'Map latitude'], ['motel_lng', 'Map longitude'], ['booking_fee', 'Booking fee (₱)'], ['hold_minutes', 'Room hold (minutes)'], ['cancel_hours', 'Free cancellation until (hours before)'], ['overnight_start', 'Overnight starts (HH:MM)'], ['overnight_end', 'Overnight ends (HH:MM)']];
export function Settings() {
  const d = useLoad(() => api('GET', '/settings')); const toast = useToast();
  const [f, setF] = useState(null); const [err, setErr] = useState(''); const [busy, setBusy] = useState(false);
  const v = f || d.data?.data;
  const save = async (e) => {
    e.preventDefault(); setBusy(true); setErr('');
    try { const r = await api('PUT', '/settings', v); setF(r.data); toast('Settings saved.'); } catch (x) { setErr(x.message); }
    setBusy(false);
  };
  return (
    <>
      <Top title="Settings" sub="Motel details and booking rules." />
      <Loading state={d} />
      {v && <>
        <form className="card" onSubmit={save} style={{ marginBottom: 16 }}>
          <Err text={err} />
          <div className="grid2">{FIELDS.map(([k, t]) => <Field key={k} label={t}><input value={v[k] ?? ''} onChange={(e) => setF({ ...v, [k]: e.target.value })} required /></Field>)}</div>
          <button className="btn" disabled={busy}>{busy ? 'Saving…' : 'Save settings'}</button>
        </form>
        <div className="card"><h2 style={{ marginBottom: 8 }}>Connected services</h2>
          <Table head={['Service', 'State', 'Note']} rows={d.data.integrations.map((i) => <tr key={i.name}><td><b>{i.name}</b></td><td><Pill v={i.connected ? 'active' : 'off'} text={i.connected ? 'Connected' : 'Not connected'} /></td><td className="muted">{i.note}</td></tr>)} />
          <p className="muted" style={{ marginBottom: 0 }}>Keys are set in the server environment (Render → Environment), never on this page.</p></div>
      </>}
    </>
  );
}

export function Audit() {
  const d = useLoad(() => api('GET', '/audit'));
  return (
    <>
      <Top title="Audit log" sub="Who did what, newest first. Entries cannot be edited or deleted." />
      <div className="card"><Loading state={d} />{d.data && <Table head={['When', 'Who', 'Action', 'Detail', 'IP']} empty="Nothing logged yet."
        rows={d.data.data.map((a) => <tr key={a.id}><td>{when(a.created_at)}</td><td>{a.full_name || '—'}</td><td>{a.action}</td><td className="muted">{a.detail}</td><td className="muted">{a.ip}</td></tr>)} />}</div>
    </>
  );
}
