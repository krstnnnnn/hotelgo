import { useState } from 'react';
import { api } from '../api';
import { Confirm, Err, Field, FormModal, Loading, Modal, Pill, Table, Top, label, peso, stay, useLoad, useToast, when } from '../ui';

const STATUSES = ['pending_payment', 'confirmed', 'checked_in', 'checked_out', 'cancelled', 'expired'];

export default function Bookings({ user, refParam }) {
  const [status, setStatus] = useState(''); const [q, setQ] = useState(''); const [term, setTerm] = useState('');
  const [walkin, setWalkin] = useState(false);
  const list = useLoad(() => api('GET', '/bookings?' + new URLSearchParams({ status, q: term })), [status, term]);
  return (
    <>
      <Top title="Reservations" sub="Search, check in and out, fix details.">
        <button className="btn amber" onClick={() => setWalkin(true)}>+ Walk-in</button>
      </Top>
      <form className="card row" style={{ marginBottom: 16 }} onSubmit={(e) => { e.preventDefault(); setTerm(q); }}>
        <div className="grow"><input placeholder="Search name, mobile or reference" value={q} onChange={(e) => setQ(e.target.value)} aria-label="Search" /></div>
        <select style={{ width: 'auto' }} value={status} onChange={(e) => setStatus(e.target.value)} aria-label="Status">
          <option value="">All statuses</option>{STATUSES.map((s) => <option key={s} value={s}>{label(s)}</option>)}
        </select>
        <button className="btn">Search</button>
      </form>
      <div className="card">
        <Loading state={list} />
        {list.data && (
          <Table head={['Booking', 'Guest', 'Room', 'Stay', 'Starts', 'Total', 'Status']} empty="No bookings match."
            rows={list.data.data.map((b) => (
              <tr key={b.booking_ref} className="click" onClick={() => (location.hash = '#/bookings/' + b.booking_ref)}>
                <td><b>{b.booking_ref}</b></td><td>{b.guest_name}<br /><span className="muted">{b.guest_mobile}</span></td><td>{b.room_no || '—'} · {b.type_name}</td>
                <td>{stay(b)}</td><td>{when(b.start_at)}</td><td>{peso(b.total)}</td><td><Pill v={b.status} /></td>
              </tr>
            ))} />
        )}
      </div>
      {walkin && <Walkin onClose={() => setWalkin(false)} onDone={(ref) => { list.reload(); location.hash = '#/bookings/' + ref; }} />}
      {refParam && <Detail key={refParam} bookingRef={refParam} user={user} onChange={list.reload} onClose={() => (location.hash = '#/bookings')} />}
    </>
  );
}

function Walkin({ onClose, onDone }) {
  const types = useLoad(() => api('GET', '/room-types'));
  const [f, setF] = useState({ room_type_id: '', stay: 'hourly', hours: 3, guests: 1, name: '', mobile: '' });
  const toast = useToast();
  const set = (k) => (e) => setF({ ...f, [k]: e.target.value });
  const save = async () => {
    const r = await api('POST', '/bookings', { ...f, room_type_id: Number(f.room_type_id), hours: Number(f.hours), guests: Number(f.guests) });
    toast('Walk-in booked: room ' + r.data.room_no + '.'); onDone(r.data.booking_ref);
  };
  return (
    <FormModal title="Walk-in booking (cash)" onClose={onClose} onSave={save} save="Book room">
      <Loading state={types} />
      <div className="grid2">
        <Field label="Room type"><select value={f.room_type_id} onChange={set('room_type_id')} required><option value="">Choose…</option>{(types.data?.data || []).map((t) => <option key={t.id} value={t.id}>{t.name} (up to {t.max_guests})</option>)}</select></Field>
        <Field label="Stay"><select value={f.stay} onChange={set('stay')}><option value="hourly">Hourly</option><option value="overnight">Overnight</option></select></Field>
        {f.stay === 'hourly' && <Field label="Hours"><select value={f.hours} onChange={set('hours')}><option>3</option><option>6</option><option>12</option></select></Field>}
        <Field label="Guests"><input type="number" min="1" value={f.guests} onChange={set('guests')} required /></Field>
        <Field label="Guest name"><input value={f.name} onChange={set('name')} required minLength={2} /></Field>
        <Field label="Mobile number"><input value={f.mobile} onChange={set('mobile')} placeholder="09171234567" required /></Field>
      </div>
    </FormModal>
  );
}

function Detail({ bookingRef, user, onChange, onClose }) {
  const d = useLoad(() => api('GET', '/bookings/' + bookingRef), [bookingRef]);
  const toast = useToast();
  const [edit, setEdit] = useState(false); const [del, setDel] = useState(false); const [room, setRoom] = useState('');
  const b = d.data?.data;
  const act = async (doIt, extra = {}) => {
    try {
      const r = await api('POST', '/bookings/' + bookingRef + '/action', { do: doIt, ...extra });
      toast(r.message, r.level === 'warn' ? 'bad' : 'ok'); d.reload(); onChange();
    } catch (e) { toast(e.message, 'bad'); }
  };
  return (
    <Modal title={'Booking ' + bookingRef} onClose={onClose} wide>
      <Loading state={d} />
      {b && (
        <>
          <div className="row" style={{ marginBottom: 12 }}><Pill v={b.status} /><span className="muted">{b.source === 'walkin' ? 'Walk-in' : 'Online'}</span></div>
          <div className="grid2">
            <dl className="kv"><dt>Guest</dt><dd>{b.guest_name}</dd><dt>Mobile</dt><dd>{b.guest_mobile}</dd><dt>Email</dt><dd>{b.guest_email || '—'}</dd><dt>Guests</dt><dd>{b.guests}</dd><dt>ID</dt><dd>{b.id_type ? b.id_type + ' ···' + b.id_last4 : '—'}</dd></dl>
            <dl className="kv"><dt>Room</dt><dd>{b.room_no || '—'} · {b.type_name}</dd><dt>Stay</dt><dd>{stay(b)}</dd><dt>Starts</dt><dd>{when(b.start_at)}</dd><dt>Ends</dt><dd>{when(b.end_at)}</dd><dt>Total</dt><dd>{peso(b.total)}</dd></dl>
          </div>
          <h3 style={{ margin: '16px 0 8px' }}>Actions</h3>
          <div className="row">
            {b.status === 'confirmed' && <button className="btn small" onClick={() => act('checkin')}>Check in</button>}
            {b.status === 'checked_in' && <button className="btn small" onClick={() => act('checkout')}>Check out</button>}
            {['confirmed', 'checked_in'].includes(b.status) && <button className="btn small ghost" onClick={() => act('resend')}>Resend confirmation</button>}
            {['confirmed', 'pending_payment'].includes(b.status) && <button className="btn small danger" onClick={() => window.confirm('Cancel this booking? Paid money is refunded.') && act('cancel')}>Cancel booking</button>}
            <button className="btn small ghost" onClick={() => setEdit(true)}>Edit guest details</button>
            {user.role === 'admin' && <button className="btn small danger" onClick={() => setDel(true)}>Delete</button>}
          </div>
          {['confirmed', 'pending_payment'].includes(b.status) && d.data.free_rooms.length > 0 && (
            <div className="row" style={{ marginTop: 12 }}>
              <select style={{ width: 'auto' }} value={room} onChange={(e) => setRoom(e.target.value)} aria-label="Other free room"><option value="">Move to another room…</option>{d.data.free_rooms.map((r) => <option key={r.id} value={r.id}>Room {r.room_no}</option>)}</select>
              <button className="btn small ghost" disabled={!room} onClick={() => act('reassign', { room_id: Number(room) })}>Change room</button>
            </div>
          )}
          <h3 style={{ margin: '16px 0 8px' }}>Payments</h3>
          <Table head={['Reference', 'Method', 'Amount', 'Status']} empty="No payment yet." rows={d.data.payments.map((p) => <tr key={p.id}><td>{p.txn_ref}</td><td>{p.method}</td><td>{peso(p.amount)}</td><td><Pill v={p.status} /></td></tr>)} />
          <h3 style={{ margin: '16px 0 8px' }}>Messages sent</h3>
          <Table head={['When', 'Channel', 'To', 'Status']} empty="No messages." rows={d.data.notifications.map((n) => <tr key={n.id}><td>{when(n.created_at)}</td><td>{n.channel}</td><td>{n.recipient}</td><td><Pill v={n.status} /></td></tr>)} />
          {edit && (
            <EditGuest b={b} onClose={() => setEdit(false)} onSaved={() => { d.reload(); onChange(); }} />
          )}
          {del && <Confirm title="Delete booking" text="Only cancelled or expired bookings without a payment can be deleted. This cannot be undone." onClose={() => setDel(false)}
            onYes={async () => { await api('DELETE', '/bookings/' + bookingRef); toast('Booking deleted.'); onChange(); onClose(); }} />}
        </>
      )}
    </Modal>
  );
}

function EditGuest({ b, onClose, onSaved }) {
  const [f, setF] = useState({ guest_name: b.guest_name, guest_mobile: b.guest_mobile, guest_email: b.guest_email, guests: b.guests });
  const set = (k) => (e) => setF({ ...f, [k]: e.target.value });
  return (
    <FormModal title="Edit guest details" onClose={onClose} onSave={async () => { await api('PUT', '/bookings/' + b.booking_ref, { ...f, guests: Number(f.guests) }); onSaved(); }}>
      <div className="grid2">
        <Field label="Name"><input value={f.guest_name} onChange={set('guest_name')} required /></Field>
        <Field label="Mobile"><input value={f.guest_mobile} onChange={set('guest_mobile')} required /></Field>
        <Field label="Email"><input type="email" value={f.guest_email} onChange={set('guest_email')} /></Field>
        <Field label="Guests"><input type="number" min="1" value={f.guests} onChange={set('guests')} required /></Field>
      </div>
    </FormModal>
  );
}
