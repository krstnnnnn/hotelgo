import { useState } from 'react';
import { api } from '../api';
import { Confirm, Field, FormModal, Loading, Modal, Pill, Table, Top, peso, stay, useLoad, useToast, when } from '../ui';

export default function Guests({ user }) {
  const [q, setQ] = useState(''); const [term, setTerm] = useState(''); const [open, setOpen] = useState(null);
  const list = useLoad(() => api('GET', '/guests?' + new URLSearchParams({ q: term })), [term]);
  return (
    <>
      <Top title="Guest records" sub="Personal data: every time you open a guest it is written to the audit log." />
      <form className="card row" style={{ marginBottom: 16 }} onSubmit={(e) => { e.preventDefault(); setTerm(q); }}>
        <div className="grow"><input placeholder="Search by name or exact email" value={q} onChange={(e) => setQ(e.target.value)} aria-label="Search" /></div>
        <button className="btn">Search</button>
      </form>
      <div className="card">
        <Loading state={list} />
        {list.data && <Table head={['Name', 'Email', 'Mobile', 'Stays', 'Last visit']} empty="No guests found."
          rows={list.data.data.map((g) => <tr key={g.id} className="click" onClick={() => setOpen(g.id)}><td><b>{g.full_name}</b></td><td>{g.email}</td><td>{g.mobile}</td><td>{g.stays}</td><td>{when(g.last_visit) || '—'}</td></tr>)} />}
      </div>
      {open && <Guest id={open} user={user} onClose={() => setOpen(null)} onChange={list.reload} />}
    </>
  );
}

function Guest({ id, user, onClose, onChange }) {
  const d = useLoad(() => api('GET', '/guests/' + id), [id]);
  const toast = useToast(); const [edit, setEdit] = useState(false); const [del, setDel] = useState(false);
  const g = d.data?.data;
  return (
    <Modal title={g ? g.full_name : 'Guest'} onClose={onClose} wide>
      <Loading state={d} />
      {g && (
        <>
          <dl className="kv"><dt>Email</dt><dd>{g.email}</dd><dt>Mobile</dt><dd>{g.mobile}</dd><dt>Joined</dt><dd>{when(g.created_at)}</dd><dt>Staff note</dt><dd>{g.staff_note || '—'}</dd></dl>
          <div className="row" style={{ margin: '14px 0' }}>
            <button className="btn small" onClick={() => setEdit(true)}>Edit</button>
            {user.role === 'admin' && <button className="btn small danger" onClick={() => setDel(true)}>Delete guest</button>}
          </div>
          <h3 style={{ marginBottom: 8 }}>Bookings</h3>
          <Table head={['Booking', 'Room', 'Stay', 'Starts', 'Total', 'Status']} empty="No bookings."
            rows={d.data.bookings.map((b) => <tr key={b.booking_ref} className="click" onClick={() => (location.hash = '#/bookings/' + b.booking_ref)}><td>{b.booking_ref}</td><td>{b.room_no || '—'} · {b.type_name}</td><td>{stay(b)}</td><td>{when(b.start_at)}</td><td>{peso(b.total)}</td><td><Pill v={b.status} /></td></tr>)} />
          {edit && <EditGuest g={g} onClose={() => setEdit(false)} onSaved={() => { d.reload(); onChange(); }} />}
          {del && <Confirm title="Delete guest" text="A guest who has bookings cannot be deleted (their history must stay). This cannot be undone." onClose={() => setDel(false)}
            onYes={async () => { await api('DELETE', '/guests/' + id); toast('Guest deleted.'); onChange(); onClose(); }} />}
        </>
      )}
    </Modal>
  );
}

function EditGuest({ g, onClose, onSaved }) {
  const [f, setF] = useState({ full_name: g.full_name, email: g.email, mobile: g.mobile, staff_note: g.staff_note || '' });
  const set = (k) => (e) => setF({ ...f, [k]: e.target.value });
  return (
    <FormModal title="Edit guest" onClose={onClose} onSave={async () => { await api('PUT', '/guests/' + g.id, f); onSaved(); }}>
      <div className="grid2">
        <Field label="Name"><input value={f.full_name} onChange={set('full_name')} required /></Field>
        <Field label="Email"><input type="email" value={f.email} onChange={set('email')} required /></Field>
        <Field label="Mobile"><input value={f.mobile} onChange={set('mobile')} required /></Field>
      </div>
      <Field label="Staff note (max 500 characters)"><textarea rows="3" maxLength={500} value={f.staff_note} onChange={set('staff_note')} /></Field>
    </FormModal>
  );
}
