import "@/App.css";
import { BrowserRouter, Routes, Route } from "react-router-dom";
import { Toaster } from "sonner";
import { AuthProvider } from "@/lib/auth-context";
import { Layout } from "@/components/Layout";
import { ProtectedRoute } from "@/components/ProtectedRoute";

import Home from "@/pages/Home";
import Product from "@/pages/Product";
import Cli from "@/pages/Cli";
import Console from "@/pages/Console";
import Architecture from "@/pages/Architecture";
import UseCases from "@/pages/UseCases";
import Security from "@/pages/Security";
import Pricing from "@/pages/Pricing";
import Faq from "@/pages/Faq";
import Contact from "@/pages/Contact";
import { PageBySlug } from "@/pages/StaticPage";
import { DocsLanding, DocPage } from "@/pages/Docs";
import Login from "@/pages/Login";
import Register from "@/pages/Register";
import GithubCallback from "@/pages/GithubCallback";
import Dashboard from "@/pages/Dashboard";
import Admin from "@/pages/Admin";

function NotFound() {
  return (
    <div className="min-h-[60vh] flex items-center justify-center text-zinc-500" data-testid="not-found">
      <div className="text-center">
        <div className="text-7xl font-mono text-amber-500 mb-3">404</div>
        <div className="font-mono text-sm">page not found</div>
      </div>
    </div>
  );
}

export default function App() {
  return (
    <div className="App">
      <AuthProvider>
        <BrowserRouter>
          <Toaster theme="dark" position="top-right" richColors closeButton />
          <Routes>
            <Route element={<Layout />}>
              <Route index element={<Home />} />
              <Route path="/product" element={<Product />} />
              <Route path="/cli" element={<Cli />} />
              <Route path="/console" element={<Console />} />
              <Route path="/architecture" element={<Architecture />} />
              <Route path="/use-cases" element={<UseCases />} />
              <Route path="/security" element={<Security />} />
              <Route path="/pricing" element={<Pricing />} />
              <Route path="/faq" element={<Faq />} />
              <Route path="/contact" element={<Contact />} />
              <Route path="/about" element={<PageBySlug slug="about" />} />
              <Route path="/terms" element={<PageBySlug slug="terms" />} />
              <Route path="/privacy" element={<PageBySlug slug="privacy" />} />
              <Route path="/docs" element={<DocsLanding />} />
              <Route path="/docs/:slug" element={<DocPage />} />
              <Route path="/login" element={<Login />} />
              <Route path="/register" element={<Register />} />
              <Route path="/auth/sso/github/callback" element={<GithubCallback />} />
              <Route path="/dashboard" element={<ProtectedRoute><Dashboard /></ProtectedRoute>} />
              <Route path="/admin/*" element={<ProtectedRoute adminOnly><Admin /></ProtectedRoute>} />
              <Route path="*" element={<NotFound />} />
            </Route>
          </Routes>
        </BrowserRouter>
      </AuthProvider>
    </div>
  );
}
