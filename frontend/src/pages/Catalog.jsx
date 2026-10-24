import { useState } from 'react';
import { api } from '../api';
import { Confirm, Field, FormModal, Loading, Pill, Table, Top, peso, useLoad, useToast } from '../ui';

const blankType = { name: '', description: '', amenities: '', max_guests: 2, price_3h: '', price_6h: '', price_12h: '', price_overnight: '', cleaning_buffer_min: 15, is_active: 1 };

export function RoomTypes({ user }) {
  const list = useLoad(() => api('GET', '/types'));
  const [edit, setEdit] = useState(null); const [del, setDel] = useState(null); const toast = useToast();
  const admin = user.role === 'admin';
  return (
    <>
      <Top title="Room types & rates" sub="Prices guests see on the website.">{admin && <button className="btn" onClick={() => setEdit({ ...blankType })}>+ New room type</button>}</Top>
      <div className="card">
        <Loading state={list} />
        {list.data && <Table head={['Name', 'Guests', '3h', '6h', '12h', 'Overnight', 'Buffer', 'Visible', '']} empty="No room types yet."
          rows={list.data.data.map((t) => (
            <tr key={t.id}><td><b>{t.name}</b></td><td>{t.max_guests}</td><td>{peso(t.price_3h)}</td><td>{peso(t.price_6h)}</td><td>{peso(t.price_12h)}</td><td>{peso(t.price_overnight)}</td><td>{t.cleaning_buffer_min} min</td>
              <td><Pill v={Number(t.is_active) ? 'active' : 'off'} text={Number(t.is_active) ? 'Yes' : 'Hidden'} /></td>
              <td className="row">{admin && <><button className="btn small" onClick={() => setEdit(t)}>Edit</button><button className="btn small danger" onClick={() => setDel(t)}>Delete</button></>}</td></tr>
          ))} />}
      </div>
      {edit && <TypeForm t={edit} onClose={() => setEdit(null)} onSaved={list.reload} />}
      {del && <Confirm title={'Delete ' + del.name} text="A type that still has rooms or bookings cannot be deleted. Untick “Visible to guests” to hide it instead." onClose={() => setDel(null)}
        onYes={async () => { await api('DELETE', '/types/' + del.id); toast('Room type deleted.'); list.reload(); }} />}
    </>
  );
}

function TypeForm({ t, onClose, onSaved }) {
  const [f, setF] = useState(t); const toast = useToast();
  const set = (k) => (e) => setF({ ...f, [k]: e.target.value });
  const save = async () => {
    const body = { ...f, is_active: Number(f.is_active) ? 1 : 0 };
    if (t.id) await api('PUT', '/types/' + t.id, body); else await api('POST', '/types', body);
    toast('Room type saved.'); onSaved();
  };
  return (
    <FormModal title={t.id ? 'Edit room type' : 'New room type'} onClose={onClose} onSave={save}>
      <Field label="Name"><input value={f.name} onChange={set('name')} required minLength={2} maxLength={80} /></Field>
      <Field label="Description"><textarea rows="2" maxLength={500} value={f.description || ''} onChange={set('description')} /></Field>
      <Field label="Amenities (comma separated)"><input value={f.amenities || ''} onChange={set('amenities')} maxLength={250} /></Field>
      <div className="grid2">
        <Field label="Max guests"><input type="number" min="1" max="20" value={f.max_guests} onChange={set('max_guests')} required /></Field>
        <Field label="Cleaning buffer (minutes)"><input type="number" min="0" max="180" value={f.cleaning_buffer_min} onChange={set('cleaning_buffer_min')} required /></Field>
        <Field label="Price 3 hours (₱)"><input type="number" min="0" step="0.01" value={f.price_3h} onChange={set('price_3h')} required /></Field>
        <Field label="Price 6 hours (₱)"><input type="number" min="0" step="0.01" value={f.price_6h} onChange={set('price_6h')} required /></Field>
        <Field label="Price 12 hours (₱)"><input type="number" min="0" step="0.01" value={f.price_12h} onChange={set('price_12h')} required /></Field>
        <Field label="Price overnight (₱)"><input type="number" min="0" step="0.01" value={f.price_overnight} onChange={set('price_overnight')} required /></Field>
      </div>
      <label className="chk"><input type="checkbox" checked={!!Number(f.is_active)} onChange={(e) => setF({ ...f, is_active: e.target.checked ? 1 : 0 })} /> Visible to guests</label>
    </FormModal>
  );
}

export function Rooms({ user }) {
  const list = useLoad(() => api('GET', '/rooms')); const types = useLoad(() => api('GET', '/types'));
  const [edit, setEdit] = useState(null); const [del, setDel] = useState(null); const toast = useToast();
  const admin = user.role === 'admin';
  return (
    <>
      <Top title="Rooms" sub="Every physical room.">{admin && <button className="btn" onClick={() => setEdit({ room_no: '', room_type_id: types.data?.data?.[0]?.id || '', floor: 1, status: 'active' })}>+ New room</button>}</Top>
      <div className="card">
        <Loading state={list} />
        {list.data && <Table head={['Room', 'Type', 'Floor', 'Status', '']} empty="No rooms yet."
          rows={list.data.data.map((r) => (
            <tr key={r.id}><td><b>{r.room_no}</b></td><td>{r.type_name}</td><td>{r.floor}</td><td><Pill v={r.status} /></td>
              <td className="row">{admin && <><button className="btn small" onClick={() => setEdit(r)}>Edit</button><button className="btn small danger" onClick={() => setDel(r)}>Delete</button></>}</td></tr>
          ))} />}
      </div>
      {edit && <RoomForm r={edit} types={types.data?.data || []} onClose={() => setEdit(null)} onSaved={list.reload} />}
      {del && <Confirm title={'Delete room ' + del.room_no} text="A room that has bookings cannot be deleted. Set it to “maintenance” instead." onClose={() => setDel(null)}
        onYes={async () => { await api('DELETE', '/rooms/' + del.id); toast('Room deleted.'); list.reload(); }} />}
    </>
  );
}

function RoomForm({ r, types, onClose, onSaved }) {
  const [f, setF] = useState(r); const toast = useToast();
  const set = (k) => (e) => setF({ ...f, [k]: e.target.value });
  const save = async () => {
    const body = { room_no: f.room_no, room_type_id: Number(f.room_type_id), floor: Number(f.floor), status: f.status };
    if (r.id) await api('PUT', '/rooms/' + r.id, body); else await api('POST', '/rooms', body);
    toast('Room saved.'); onSaved();
  };
  return (
    <FormModal title={r.id ? 'Edit room' : 'New room'} onClose={onClose} onSave={save}>
      <div className="grid2">
        <Field label="Room number"><input value={f.room_no} onChange={set('room_no')} required maxLength={10} /></Field>
        <Field label="Room type"><select value={f.room_type_id} onChange={set('room_type_id')} required><option value="">Choose…</option>{types.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}</select></Field>
        <Field label="Floor"><input type="number" min="1" max="50" value={f.floor} onChange={set('floor')} required /></Field>
        <Field label="Status"><select value={f.status} onChange={set('status')}><option value="active">Active</option><option value="maintenance">Maintenance</option></select></Field>
      </div>
    </FormModal>
  );
}
