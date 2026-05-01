"""AtGlance backend API tests — pytest."""
import os
import time
import pytest
import requests

BASE_URL = os.environ.get("REACT_APP_BACKEND_URL", "https://atglance-self-hosted.preview.emergentagent.com").rstrip("/")
API = f"{BASE_URL}/api"

ADMIN_EMAIL = "admin@atglance.io"
ADMIN_PASSWORD = "Admin@12345"


@pytest.fixture(scope="session")
def admin_session():
    s = requests.Session()
    r = s.post(f"{API}/auth/login", json={"email": ADMIN_EMAIL, "password": ADMIN_PASSWORD}, timeout=15)
    assert r.status_code == 200, f"Admin login failed: {r.status_code} {r.text}"
    data = r.json()
    assert data["role"] == "admin"
    return s


@pytest.fixture(scope="session")
def user_session():
    s = requests.Session()
    email = f"testuser+{int(time.time())}@example.com"
    r = s.post(f"{API}/auth/register", json={"email": email, "password": "password123", "name": "Test User"}, timeout=15)
    assert r.status_code == 200, f"Register failed: {r.status_code} {r.text}"
    data = r.json()
    assert data["role"] == "user"
    s.email = email  # type: ignore
    return s


# ---- Health ----
class TestHealth:
    def test_root(self):
        r = requests.get(f"{API}/", timeout=10)
        assert r.status_code == 200
        d = r.json()
        assert d.get("ok") is True
        assert d.get("service") == "AtGlance API"


# ---- Auth ----
class TestAuth:
    def test_register_new_user_sets_cookies(self):
        s = requests.Session()
        email = f"reguser+{int(time.time()*1000)}@example.com"
        r = s.post(f"{API}/auth/register", json={"email": email, "password": "password123"}, timeout=15)
        assert r.status_code == 200, r.text
        data = r.json()
        assert data["email"] == email
        assert data["role"] == "user"
        assert "id" in data
        assert "access_token" in s.cookies
        assert "refresh_token" in s.cookies

    def test_register_duplicate_rejected(self):
        s = requests.Session()
        email = f"dupe+{int(time.time()*1000)}@example.com"
        r1 = s.post(f"{API}/auth/register", json={"email": email, "password": "password123"}, timeout=15)
        assert r1.status_code == 200
        r2 = requests.post(f"{API}/auth/register", json={"email": email, "password": "password123"}, timeout=15)
        assert r2.status_code == 400

    def test_admin_login_returns_admin_role(self, admin_session):
        r = admin_session.get(f"{API}/auth/me", timeout=15)
        assert r.status_code == 200
        d = r.json()
        assert d["role"] == "admin"
        assert d["email"] == ADMIN_EMAIL

    def test_login_invalid_credentials(self):
        r = requests.post(f"{API}/auth/login", json={"email": ADMIN_EMAIL, "password": "wrongpass"}, timeout=15)
        assert r.status_code == 401

    def test_me_unauthenticated_returns_401(self):
        r = requests.get(f"{API}/auth/me", timeout=15)
        assert r.status_code == 401

    def test_logout_clears_cookies(self):
        s = requests.Session()
        r = s.post(f"{API}/auth/login", json={"email": ADMIN_EMAIL, "password": ADMIN_PASSWORD}, timeout=15)
        assert r.status_code == 200
        assert "access_token" in s.cookies
        r2 = s.post(f"{API}/auth/logout", timeout=15)
        assert r2.status_code == 200
        # After logout, /me should fail
        r3 = s.get(f"{API}/auth/me", timeout=15)
        assert r3.status_code == 401

    def test_github_start_returns_auth_url(self):
        r = requests.get(f"{API}/auth/github/start", timeout=15)
        assert r.status_code == 200
        d = r.json()
        assert "auth_url" in d
        assert "state" in d
        assert d["auth_url"].startswith("https://github.com/login/oauth/authorize")
        assert "client_id=Ov23lijGSKYOBKxygfKj" in d["auth_url"]
        assert f"state={d['state']}" in d["auth_url"]


# ---- Public CMS ----
class TestPublicCMS:
    def test_pricing_no_id(self):
        r = requests.get(f"{API}/cms/pricing", timeout=15)
        assert r.status_code == 200
        items = r.json()
        assert isinstance(items, list)
        assert len(items) >= 1
        for it in items:
            assert "_id" not in it
            assert "name" in it and "price" in it

    def test_faqs_no_id(self):
        r = requests.get(f"{API}/cms/faqs", timeout=15)
        assert r.status_code == 200
        items = r.json()
        assert len(items) >= 1
        for it in items:
            assert "_id" not in it
            assert "question" in it and "answer" in it

    def test_docs_list_no_id(self):
        r = requests.get(f"{API}/cms/docs", timeout=15)
        assert r.status_code == 200
        items = r.json()
        assert len(items) >= 1
        for it in items:
            assert "_id" not in it
            assert "slug" in it and "title" in it

    def test_doc_detail(self):
        r = requests.get(f"{API}/cms/docs/quickstart", timeout=15)
        assert r.status_code == 200
        d = r.json()
        assert "_id" not in d
        assert d["slug"] == "quickstart"
        assert "content" in d and len(d["content"]) > 0

    def test_doc_not_found(self):
        r = requests.get(f"{API}/cms/docs/does-not-exist", timeout=15)
        assert r.status_code == 404

    def test_contact_no_id(self):
        r = requests.get(f"{API}/cms/contact", timeout=15)
        assert r.status_code == 200
        d = r.json()
        assert "_id" not in d
        assert "email" in d

    @pytest.mark.parametrize("slug", ["about", "terms", "privacy"])
    def test_static_pages(self, slug):
        r = requests.get(f"{API}/cms/pages/{slug}", timeout=15)
        assert r.status_code == 200, f"page {slug} -> {r.status_code}"
        d = r.json()
        assert "_id" not in d
        assert d["slug"] == slug
        assert "content" in d


# ---- Downloads (gated) ----
class TestDownloads:
    def test_downloads_requires_auth(self):
        r = requests.get(f"{API}/downloads", timeout=15)
        assert r.status_code == 401

    def test_downloads_with_user_session(self, user_session):
        r = user_session.get(f"{API}/downloads", timeout=15)
        assert r.status_code == 200
        d = r.json()
        assert "cli_url" in d and "console_url" in d


# ---- Admin RBAC ----
class TestAdminRBAC:
    @pytest.mark.parametrize("path,payload", [
        ("/admin/pricing", []),
        ("/admin/faqs", []),
        ("/admin/docs", {"items": []}),
        ("/admin/contact", {"email": "x@y.z", "sales_email": "s@y.z", "support_email": "p@y.z", "address": "x", "github": "", "twitter": ""}),
        ("/admin/pages/about", {"slug": "about", "title": "A", "content": "x"}),
        ("/admin/downloads", {"cli_url": "u", "console_url": "u"}),
    ])
    def test_admin_writes_reject_anonymous(self, path, payload):
        r = requests.put(f"{API}{path}", json=payload, timeout=15)
        assert r.status_code == 401

    @pytest.mark.parametrize("path,payload", [
        ("/admin/pricing", []),
        ("/admin/faqs", []),
        ("/admin/docs", {"items": []}),
        ("/admin/contact", {"email": "x@y.z", "sales_email": "s@y.z", "support_email": "p@y.z", "address": "x", "github": "", "twitter": ""}),
        ("/admin/pages/about", {"slug": "about", "title": "A", "content": "x"}),
        ("/admin/downloads", {"cli_url": "u", "console_url": "u"}),
    ])
    def test_admin_writes_reject_user(self, user_session, path, payload):
        r = user_session.put(f"{API}{path}", json=payload, timeout=15)
        assert r.status_code == 403


# ---- Admin writes persistence ----
class TestAdminWrites:
    def test_pricing_update_and_persist(self, admin_session):
        # GET current
        cur = requests.get(f"{API}/cms/pricing", timeout=15).json()
        # Modify first plan
        new_plans = []
        for i, p in enumerate(cur):
            np = {k: v for k, v in p.items() if k != "_id"}
            if i == 0:
                np["name"] = "Free TEST"
            new_plans.append(np)
        r = admin_session.put(f"{API}/admin/pricing", json=new_plans, timeout=15)
        assert r.status_code == 200, r.text
        # Verify
        verify = requests.get(f"{API}/cms/pricing", timeout=15).json()
        assert verify[0]["name"] == "Free TEST"
        # Restore
        restored = []
        for i, p in enumerate(verify):
            np = {k: v for k, v in p.items() if k != "_id"}
            if i == 0:
                np["name"] = "Free"
            restored.append(np)
        r2 = admin_session.put(f"{API}/admin/pricing", json=restored, timeout=15)
        assert r2.status_code == 200

    def test_faqs_update(self, admin_session):
        cur = requests.get(f"{API}/cms/faqs", timeout=15).json()
        items = [{k: v for k, v in f.items() if k != "_id"} for f in cur]
        r = admin_session.put(f"{API}/admin/faqs", json=items, timeout=15)
        assert r.status_code == 200
        out = r.json()
        assert len(out) == len(items)

    def test_docs_bulk_update(self, admin_session):
        # Use admin endpoint to fetch full content
        r = admin_session.get(f"{API}/admin/docs/full", timeout=15)
        assert r.status_code == 200
        cur = r.json()
        items = [{k: v for k, v in d.items() if k != "_id"} for d in cur]
        r2 = admin_session.put(f"{API}/admin/docs", json={"items": items}, timeout=15)
        assert r2.status_code == 200, r2.text
        # Verify
        v = requests.get(f"{API}/cms/docs", timeout=15).json()
        assert len(v) == len(items)

    def test_contact_update(self, admin_session):
        info = {
            "email": "info@atglance.live", "sales_email": "sales@atglance.live",
            "support_email": "support@atglance.live",
            "address": "Everything inside your boundaries.",
            "github": "https://github.com/atglance-app", "twitter": "#",
        }
        r = admin_session.put(f"{API}/admin/contact", json=info, timeout=15)
        assert r.status_code == 200
        v = requests.get(f"{API}/cms/contact", timeout=15).json()
        assert v["email"] == "Info@atglance.live"

    def test_pages_update(self, admin_session):
        body = {"slug": "about", "title": "About AtGlance", "content": "# About\n\nUpdated."}
        r = admin_session.put(f"{API}/admin/pages/about", json=body, timeout=15)
        assert r.status_code == 200
        v = requests.get(f"{API}/cms/pages/about", timeout=15).json()
        assert "Updated" in v["content"]

    def test_downloads_update(self, admin_session, user_session):
        cfg = {
            "cli_url": "http://app.atglance.live/atglance_cli/",
            "cli_version": "1.0.1", "cli_checksum": "",
            "console_url": "http://app.atglance.live/mangement_console/",
            "console_version": "1.0.1", "console_checksum": "",
            "cli_install_command": "curl -sSL https://atglance.live/install.sh | sudo bash",
        }
        r = admin_session.put(f"{API}/admin/downloads", json=cfg, timeout=15)
        assert r.status_code == 200
        v = user_session.get(f"{API}/downloads", timeout=15).json()
        assert v["cli_version"] == "1.0.1"
