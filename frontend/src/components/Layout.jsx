import { Outlet } from "react-router-dom";
import { Header } from "./Header";
import { Footer } from "./Footer";

// All pages sit inside one frosted-glass frame over the glowing page background (design-doc §3).
export function Layout() {
  return (
    <div className="ag-frame flex flex-col">
      <Header />
      <main className="flex-1">
        <Outlet />
      </main>
      <Footer />
    </div>
  );
}
