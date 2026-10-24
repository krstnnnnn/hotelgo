import { api } from '../api';
import { Loading, Pill, Table, Top, label, peso, useLoad, when } from '../ui';

export default function Dashboard() {
  const d = useLoad(() => api('GET', '/dashboard'));
  const x = d.data;
  return (
    <>
      <Top title="Dashboard" sub="Live view of every room."><button className="btn ghost" onClick={d.reload}>Refresh</button></Top>
      <Loading state={d} />
      {x && (
        <>
          <div className="cards">
            <div className="card stat"><b>{x.occupancy}%</b><span>Occupancy</span></div>
            <div className="card stat"><b>{x.count.available || 0}</b><span>Rooms ready</span></div>
            <div className="card stat"><b>{x.checkins_soon}</b><span>Check-ins soon</span></div>
            <div className="card stat"><b>{peso(x.revenue_today)}</b><span>Paid today</span></div>
          </div>
          <div className="card" style={{ marginBottom: 18 }}>
            <h2 style={{ marginBottom: 12 }}>Rooms</h2>
            <div className="board">
              {x.board.map((r) => (
                <div key={r.id} className={'room ' + r.state}>
                  <b>{r.room_no}</b><small>{r.type_name}</small><Pill v={r.state} /><small>{r.note}</small>
                </div>
              ))}
            </div>
          </div>
          <div className="card">
            <h2 style={{ marginBottom: 8 }}>Next arrivals</h2>
            <Table head={['Booking', 'Guest', 'Room', 'Starts', 'Status']} empty="No upcoming stays."
              rows={x.upcoming.map((b) => (
                <tr key={b.booking_ref} className="click" onClick={() => (location.hash = '#/bookings/' + b.booking_ref)}>
                  <td>{b.booking_ref}</td><td>{b.guest_name}</td><td>{b.room_no} · {b.type_name}</td><td>{when(b.start_at)}</td><td><Pill v={b.status} text={label(b.status)} /></td>
                </tr>
              ))} />
          </div>
        </>
      )}
    </>
  );
}
