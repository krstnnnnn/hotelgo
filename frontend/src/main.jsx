import { StrictMode, useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { restore, signOut } from './api';
import { Toasts } from './ui';
import { Availability, ChangePassword, Login } from './pages/Auth';
import Dashboard from './pages/Dashboard';
import Bookings from './pages/Bookings';
import Guests from './pages/Guests';
import { RoomTypes, Rooms } from './pages/Catalog';
import { Audit, Notifications, Payments, Reports, Settings, Staff } from './pages/Owner';

/* tiny hash router: #/bookings/HG-ABC123 -> ['bookings', 'HG-ABC123'] */
function useRoute() {
  const read = () => location.hash.replace(/^#\/?/, '').split('/').filter(Boolean);
  const [r, setR] = useState(read);
  useEffect(() => { const f = () => setR(read()); window.addEventListener('hashchange', f); return () => window.removeEventListener('hashchange', f); }, []);
  return r;
}

const NAV = [
  ['', 'Dashboard', 'staff'], ['bookings', 'Reservations', 'staff'], ['guests', 'Guests', 'staff'], ['availability', 'Availability', 'staff'],
  ['types', 'Room types', 'staff'], ['rooms', 'Rooms', 'staff'],
  ['payments', 'Payments', 'admin'], ['notifications', 'Notifications', 'admin'], ['reports', 'Reports', 'admin'], ['staff', 'Staff', 'admin'], ['settings', 'Settings', 'admin'], ['audit', 'Audit log', 'admin'],
  ['account', 'My account', 'staff'],
];

function Shell({ user, route, onOut }) {
  const [open, setOpen] = useState(false);
  const [page, arg] = route;
  const admin = user.role === 'admin';
  const view = () => {
    switch (page || '') {
      case '': return <Dashboard />;
      case 'bookings': return <Bookings user={user} refParam={arg} />;
      case 'guests': return <Guests user={user} />;
      case 'availability': return <Availability signedIn />;
      case 'types': return <RoomTypes user={user} />;
      case 'rooms': return <Rooms user={user} />;
      case 'account': return <ChangePassword />;
      case 'payments': return admin ? <Payments /> : <NoAccess />;
      case 'notifications': return admin ? <Notifications /> : <NoAccess />;
      case 'reports': return admin ? <Reports /> : <NoAccess />;
      case 'staff': return admin ? <Staff user={user} /> : <NoAccess />;
      case 'settings': return admin ? <Settings /> : <NoAccess />;
      case 'audit': return admin ? <Audit /> : <NoAccess />;
      default: return <div className="card empty">Page not found.</div>;
    }
  };
  return (
    <div className="shell">
      <aside className={'side' + (open ? ' open' : '')}>
        <div className="brand">HOTEL<span>GO</span></div>
        <nav className="nav" onClick={() => setOpen(false)}>
          {NAV.filter((n) => n[2] === 'staff' || admin).map(([p, t], i) => (
            <span key={p}>{p === 'payments' && <small>Owner</small>}<a href={'#/' + p} className={(page || '') === p ? 'on' : ''}>{t}</a></span>
          ))}
        </nav>
        <div className="who"><b>{user.full_name}</b>{user.role === 'admin' ? 'Owner' : 'Front desk'}<br /><button className="btn small ghost" style={{ marginTop: 8, color: '#fff', borderColor: '#7fa0b0' }} onClick={onOut}>Sign out</button> <a href="../admin" style={{ color: '#9fd0d6', marginLeft: 8, fontSize: 13 }}>Classic site</a></div>
      </aside>
      <main className="main">
        <button className="btn small ghost menu-btn" style={{ marginBottom: 10 }} onClick={() => setOpen(!open)}>☰ Menu</button>
        {view()}
      </main>
    </div>
  );
}
const NoAccess = () => <div className="card empty">Only the owner can open this page.</div>;

function App() {
  const route = useRoute();
  const [user, setUser] = useState(undefined);       // undefined = still checking, null = signed out
  useEffect(() => { restore().then(setUser); }, []);
  if (user === undefined) return <div className="login"><div className="card">Loading…</div></div>;
  if (!user) return route[0] === 'availability' ? <Availability /> : <Login onUser={setUser} />;
  if (Number(user.must_change_password)) return <ChangePassword forced onDone={() => setUser({ ...user, must_change_password: 0 })} />;
  return <Shell user={user} route={route} onOut={async () => { await signOut(); setUser(null); location.hash = '#/'; }} />;
}

createRoot(document.getElementById('root')).render(<StrictMode><Toasts><App /></Toasts></StrictMode>);
