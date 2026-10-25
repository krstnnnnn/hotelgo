import { StrictMode, useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { restore, signOut } from './api';
import { Toasts } from './ui';
import { Icon, Logo } from './icons';
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
  ['', 'Dashboard', 'staff', 'dashboard'], ['bookings', 'Reservations', 'staff', 'bookings'], ['guests', 'Guests', 'staff', 'guests'], ['availability', 'Availability', 'staff', 'availability'],
  ['types', 'Room types', 'staff', 'types'], ['rooms', 'Rooms', 'staff', 'rooms'],
  ['payments', 'Payments', 'admin', 'payments'], ['notifications', 'Notifications', 'admin', 'notifications'], ['reports', 'Reports', 'admin', 'reports'], ['staff', 'Staff', 'admin', 'staff'], ['settings', 'Settings', 'admin', 'settings'], ['audit', 'Audit log', 'admin', 'audit'],
  ['account', 'My account', 'staff', 'account'],
];
const initials = (n) => String(n || '?').split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase();

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
        <div className="brand"><Logo /><div>HOTEL<span>GO</span><small>Staff console</small></div></div>
        <nav className="nav" onClick={() => setOpen(false)}>
          {NAV.filter((n) => n[2] === 'staff' || admin).map(([p, t, , ic]) => (
            <span key={p}>{p === 'payments' && <small>Owner</small>}{p === 'account' && <small>Account</small>}<a href={'#/' + p} className={(page || '') === p ? 'on' : ''}><Icon n={ic} />{t}</a></span>
          ))}
        </nav>
        <div className="who">
          <div className="av">{initials(user.full_name)}</div>
          <div className="wn"><b>{user.full_name}</b><span>{user.role === 'admin' ? 'Owner' : 'Front desk'}</span></div>
          <button className="ib" title="Sign out" aria-label="Sign out" onClick={onOut}><Icon n="out" /></button>
        </div>
      </aside>
      <main className="main">
        <button className="btn small ghost menu-btn" style={{ marginBottom: 10 }} onClick={() => setOpen(!open)}><Icon n="menu" size={16} /> Menu</button>
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
