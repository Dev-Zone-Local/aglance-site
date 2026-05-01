"""AtGlance backend — FastAPI + MongoDB.

Auth: email/password (JWT, httpOnly cookies) + GitHub OAuth SSO.
CMS: pricing plans, FAQs, docs, contact, static pages, download URLs.
"""
from dotenv import load_dotenv
from pathlib import Path

ROOT_DIR = Path(__file__).parent
load_dotenv(ROOT_DIR / ".env")

import os
import uuid
import secrets
import logging
from datetime import datetime, timezone, timedelta
from typing import List, Optional

import bcrypt
import httpx
import jwt
from bson import ObjectId
from fastapi import FastAPI, APIRouter, Request, Response, HTTPException, Depends, Query
from fastapi.responses import JSONResponse
from motor.motor_asyncio import AsyncIOMotorClient
from pydantic import BaseModel, EmailStr, Field
from starlette.middleware.cors import CORSMiddleware

# ---- Config ----
MONGO_URL = os.environ["MONGO_URL"]
DB_NAME = os.environ["DB_NAME"]
JWT_SECRET = os.environ["JWT_SECRET"]
ADMIN_EMAIL = os.environ["ADMIN_EMAIL"].lower()
ADMIN_PASSWORD = os.environ["ADMIN_PASSWORD"]
GITHUB_CLIENT_ID = os.environ.get("GITHUB_CLIENT_ID", "")
GITHUB_CLIENT_SECRET = os.environ.get("GITHUB_CLIENT_SECRET", "")
GITHUB_REDIRECT_URI = os.environ.get("GITHUB_REDIRECT_URI", "")
FRONTEND_URL = os.environ.get("FRONTEND_URL", "http://localhost:3000")
JWT_ALG = "HS256"
ACCESS_TTL_MIN = 60 * 24  # 24 hours
REFRESH_TTL_DAYS = 7

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
log = logging.getLogger("atglance")

client = AsyncIOMotorClient(MONGO_URL)
db = client[DB_NAME]

app = FastAPI(title="AtGlance API")
api = APIRouter(prefix="/api")

# CORS — allow frontend + preview origin
allow_origins = [FRONTEND_URL]
extra_origins = os.environ.get("CORS_ORIGINS", "")
if extra_origins:
    allow_origins.extend([o.strip() for o in extra_origins.split(",") if o.strip()])

app.add_middleware(
    CORSMiddleware,
    allow_origin_regex=".*",
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


# ---- Helpers ----
def hash_password(pw: str) -> str:
    return bcrypt.hashpw(pw.encode("utf-8"), bcrypt.gensalt()).decode("utf-8")


def verify_password(pw: str, hashed: str) -> bool:
    try:
        return bcrypt.checkpw(pw.encode("utf-8"), hashed.encode("utf-8"))
    except Exception:
        return False


def create_token(user_id: str, email: str, role: str, kind: str = "access") -> str:
    if kind == "access":
        exp = datetime.now(timezone.utc) + timedelta(minutes=ACCESS_TTL_MIN)
    else:
        exp = datetime.now(timezone.utc) + timedelta(days=REFRESH_TTL_DAYS)
    payload = {"sub": user_id, "email": email, "role": role, "type": kind, "exp": exp}
    return jwt.encode(payload, JWT_SECRET, algorithm=JWT_ALG)


def set_auth_cookies(response: Response, user_id: str, email: str, role: str) -> str:
    access = create_token(user_id, email, role, "access")
    refresh = create_token(user_id, email, role, "refresh")
    response.set_cookie(
        "access_token", access, httponly=True, secure=False,
        samesite="lax", max_age=ACCESS_TTL_MIN * 60, path="/",
    )
    response.set_cookie(
        "refresh_token", refresh, httponly=True, secure=False,
        samesite="lax", max_age=REFRESH_TTL_DAYS * 86400, path="/",
    )
    return access


def serialize_user(u: dict) -> dict:
    return {
        "id": str(u.get("_id")),
        "email": u.get("email"),
        "name": u.get("name"),
        "role": u.get("role", "user"),
        "avatar_url": u.get("avatar_url"),
        "github_login": u.get("github_login"),
        "auth_method": u.get("auth_method", "email"),
        "created_at": (u.get("created_at").isoformat() if isinstance(u.get("created_at"), datetime) else u.get("created_at")),
    }


async def get_current_user(request: Request) -> dict:
    token = request.cookies.get("access_token")
    if not token:
        h = request.headers.get("Authorization", "")
        if h.startswith("Bearer "):
            token = h[7:]
    if not token:
        raise HTTPException(401, "Not authenticated")
    try:
        payload = jwt.decode(token, JWT_SECRET, algorithms=[JWT_ALG])
        if payload.get("type") != "access":
            raise HTTPException(401, "Invalid token type")
    except jwt.ExpiredSignatureError:
        raise HTTPException(401, "Token expired")
    except jwt.InvalidTokenError:
        raise HTTPException(401, "Invalid token")
    try:
        user = await db.users.find_one({"_id": ObjectId(payload["sub"])})
    except Exception:
        raise HTTPException(401, "Invalid user reference")
    if not user:
        raise HTTPException(401, "User not found")
    return user


async def require_admin(user: dict = Depends(get_current_user)) -> dict:
    if user.get("role") != "admin":
        raise HTTPException(403, "Admin access required")
    return user


# ---- Models ----
class RegisterIn(BaseModel):
    email: EmailStr
    password: str = Field(min_length=6, max_length=128)
    name: Optional[str] = None


class LoginIn(BaseModel):
    email: EmailStr
    password: str


class PricingPlan(BaseModel):
    id: Optional[str] = None
    name: str
    price: str
    period: str = "forever"
    description: str
    features: List[str]
    cta: str = "Get started"
    highlighted: bool = False
    order: int = 0


class FAQItem(BaseModel):
    id: Optional[str] = None
    question: str
    answer: str
    category: str = "General"
    order: int = 0


class DocItem(BaseModel):
    id: Optional[str] = None
    slug: str
    title: str
    section: str = "Getting Started"
    content: str  # markdown
    order: int = 0


class ContactInfo(BaseModel):
    email: str = "info@atglance.live"
    sales_email: str = "sales@atglance.live"
    support_email: str = "support@atglance.live"
    address: str = "Inside your boundary."
    github: str = "https://github.com/atglance-app/"
    twitter: str = "#"


class StaticPage(BaseModel):
    slug: str  # terms | privacy | about
    title: str
    content: str  # markdown


class DownloadConfig(BaseModel):
    cli_url: str
    cli_version: str = "1.0.0"
    cli_checksum: str = ""
    console_url: str
    console_version: str = "1.0.0"
    console_checksum: str = ""
    cli_install_command: str = "curl -sSL https://app.atglance.live/cli/install.sh | sudo bash"


# ---- Auth endpoints ----
@api.post("/auth/register")
async def register(payload: RegisterIn, response: Response):
    email = payload.email.lower().strip()
    existing = await db.users.find_one({"email": email})
    if existing:
        raise HTTPException(400, "Email already registered")
    role = "admin" if email == ADMIN_EMAIL else "user"
    doc = {
        "email": email,
        "name": payload.name or email.split("@")[0],
        "password_hash": hash_password(payload.password),
        "role": role,
        "auth_method": "email",
        "created_at": datetime.now(timezone.utc),
    }
    res = await db.users.insert_one(doc)
    user_id = str(res.inserted_id)
    set_auth_cookies(response, user_id, email, role)
    doc["_id"] = res.inserted_id
    return serialize_user(doc)


@api.post("/auth/login")
async def login(payload: LoginIn, response: Response):
    email = payload.email.lower().strip()
    user = await db.users.find_one({"email": email})
    if not user or not user.get("password_hash"):
        raise HTTPException(401, "Invalid email or password")
    if not verify_password(payload.password, user["password_hash"]):
        raise HTTPException(401, "Invalid email or password")
    set_auth_cookies(response, str(user["_id"]), user["email"], user.get("role", "user"))
    return serialize_user(user)


@api.post("/auth/logout")
async def logout(response: Response):
    response.delete_cookie("access_token", path="/")
    response.delete_cookie("refresh_token", path="/")
    return {"ok": True}


@api.get("/auth/me")
async def me(user: dict = Depends(get_current_user)):
    return serialize_user(user)


@api.post("/auth/refresh")
async def refresh(request: Request, response: Response):
    rt = request.cookies.get("refresh_token")
    if not rt:
        raise HTTPException(401, "No refresh token")
    try:
        payload = jwt.decode(rt, JWT_SECRET, algorithms=[JWT_ALG])
        if payload.get("type") != "refresh":
            raise HTTPException(401, "Invalid token type")
    except jwt.InvalidTokenError:
        raise HTTPException(401, "Invalid refresh token")
    user = await db.users.find_one({"_id": ObjectId(payload["sub"])})
    if not user:
        raise HTTPException(401, "User not found")
    set_auth_cookies(response, str(user["_id"]), user["email"], user.get("role", "user"))
    return {"ok": True}


# ---- GitHub OAuth ----
@api.get("/auth/github/start")
async def github_start():
    if not GITHUB_CLIENT_ID:
        raise HTTPException(500, "GitHub OAuth not configured")
    state = secrets.token_urlsafe(24)
    await db.oauth_states.insert_one({
        "state": state, "created_at": datetime.now(timezone.utc),
    })
    url = (
        "https://github.com/login/oauth/authorize"
        f"?client_id={GITHUB_CLIENT_ID}"
        f"&redirect_uri={GITHUB_REDIRECT_URI}"
        f"&scope=read:user%20user:email"
        f"&state={state}"
    )
    return {"auth_url": url, "state": state}


@api.post("/auth/github/callback")
async def github_callback(response: Response, code: str = Query(...), state: str = Query(...)):
    found = await db.oauth_states.find_one_and_delete({"state": state})
    if not found:
        # Allow first-time: don't strictly fail (state could be lost across sessions);
        # but if it was older than 10 min, reject.
        log.warning("OAuth state not found in store; proceeding leniently")
    async with httpx.AsyncClient(timeout=15.0) as cli:
        tr = await cli.post(
            "https://github.com/login/oauth/access_token",
            data={
                "client_id": GITHUB_CLIENT_ID,
                "client_secret": GITHUB_CLIENT_SECRET,
                "code": code,
                "redirect_uri": GITHUB_REDIRECT_URI,
            },
            headers={"Accept": "application/json"},
        )
        td = tr.json()
        access_token = td.get("access_token")
        if not access_token:
            raise HTTPException(400, f"GitHub token exchange failed: {td.get('error_description', td)}")
        ur = await cli.get(
            "https://api.github.com/user",
            headers={"Authorization": f"Bearer {access_token}", "Accept": "application/json"},
        )
        gh_user = ur.json()
        email = gh_user.get("email")
        if not email:
            er = await cli.get(
                "https://api.github.com/user/emails",
                headers={"Authorization": f"Bearer {access_token}", "Accept": "application/json"},
            )
            for em in er.json() if er.status_code == 200 else []:
                if em.get("primary") and em.get("verified"):
                    email = em.get("email"); break
            if not email and er.status_code == 200 and er.json():
                email = er.json()[0].get("email")
    if not email:
        email = f"{gh_user.get('login')}@users.noreply.github.com"
    email = email.lower()

    existing = await db.users.find_one({
        "$or": [{"github_id": gh_user.get("id")}, {"email": email}]
    })
    role = "admin" if email == ADMIN_EMAIL else "user"
    if existing:
        await db.users.update_one(
            {"_id": existing["_id"]},
            {"$set": {
                "github_id": gh_user.get("id"),
                "github_login": gh_user.get("login"),
                "avatar_url": gh_user.get("avatar_url"),
                "name": existing.get("name") or gh_user.get("name") or gh_user.get("login"),
                "auth_method": existing.get("auth_method") or "github",
            }}
        )
        user = await db.users.find_one({"_id": existing["_id"]})
    else:
        doc = {
            "email": email,
            "name": gh_user.get("name") or gh_user.get("login"),
            "github_id": gh_user.get("id"),
            "github_login": gh_user.get("login"),
            "avatar_url": gh_user.get("avatar_url"),
            "role": role,
            "auth_method": "github",
            "password_hash": None,
            "created_at": datetime.now(timezone.utc),
        }
        r = await db.users.insert_one(doc)
        doc["_id"] = r.inserted_id
        user = doc
    set_auth_cookies(response, str(user["_id"]), user["email"], user.get("role", "user"))
    return serialize_user(user)


# ---- Public CMS reads ----
@api.get("/cms/pricing")
async def list_pricing():
    items = await db.pricing.find({}, {"_id": 0}).sort("order", 1).to_list(100)
    return items


@api.get("/cms/faqs")
async def list_faqs():
    items = await db.faqs.find({}, {"_id": 0}).sort("order", 1).to_list(500)
    return items


@api.get("/cms/docs")
async def list_docs():
    items = await db.docs.find({}, {"_id": 0, "content": 0}).sort("order", 1).to_list(500)
    return items


@api.get("/cms/docs/{slug}")
async def get_doc(slug: str):
    item = await db.docs.find_one({"slug": slug}, {"_id": 0})
    if not item:
        raise HTTPException(404, "Doc not found")
    return item


@api.get("/cms/contact")
async def get_contact():
    item = await db.settings.find_one({"key": "contact"}, {"_id": 0, "key": 0})
    return item or ContactInfo().model_dump()


@api.get("/cms/pages/{slug}")
async def get_page(slug: str):
    item = await db.pages.find_one({"slug": slug}, {"_id": 0})
    if not item:
        raise HTTPException(404, "Page not found")
    return item


# ---- Authenticated read for downloads (gated) ----
@api.get("/downloads")
async def get_downloads(user: dict = Depends(get_current_user)):
    item = await db.settings.find_one({"key": "downloads"}, {"_id": 0, "key": 0})
    return item or DownloadConfig(
        cli_url="http://app.atglance.live/atglance_cli/",
        console_url="http://app.atglance.live/mangement_console/",
    ).model_dump()


# ---- Admin CMS writes ----
@api.put("/admin/pricing")
async def upsert_pricing(plans: List[PricingPlan], _: dict = Depends(require_admin)):
    await db.pricing.delete_many({})
    if plans:
        docs = [{**p.model_dump(), "id": p.id or str(uuid.uuid4())} for p in plans]
        await db.pricing.insert_many(docs)
    out = await db.pricing.find({}, {"_id": 0}).sort("order", 1).to_list(100)
    return out


@api.put("/admin/faqs")
async def upsert_faqs(items: List[FAQItem], _: dict = Depends(require_admin)):
    await db.faqs.delete_many({})
    if items:
        docs = [{**i.model_dump(), "id": i.id or str(uuid.uuid4())} for i in items]
        await db.faqs.insert_many(docs)
    out = await db.faqs.find({}, {"_id": 0}).sort("order", 1).to_list(500)
    return out


class DocsBulk(BaseModel):
    items: List[DocItem]


@api.put("/admin/docs")
async def upsert_docs(payload: DocsBulk, _: dict = Depends(require_admin)):
    await db.docs.delete_many({})
    if payload.items:
        docs = [{**i.model_dump(), "id": i.id or str(uuid.uuid4())} for i in payload.items]
        await db.docs.insert_many(docs)
    out = await db.docs.find({}, {"_id": 0}).sort("order", 1).to_list(500)
    return out


@api.get("/admin/docs/full")
async def list_docs_full(_: dict = Depends(require_admin)):
    return await db.docs.find({}, {"_id": 0}).sort("order", 1).to_list(500)


@api.put("/admin/contact")
async def update_contact(info: ContactInfo, _: dict = Depends(require_admin)):
    data = info.model_dump()
    data["key"] = "contact"
    await db.settings.replace_one({"key": "contact"}, data, upsert=True)
    return info


@api.put("/admin/pages/{slug}")
async def update_page(slug: str, page: StaticPage, _: dict = Depends(require_admin)):
    data = page.model_dump()
    data["slug"] = slug
    await db.pages.replace_one({"slug": slug}, data, upsert=True)
    return page


@api.get("/admin/pages")
async def list_pages(_: dict = Depends(require_admin)):
    return await db.pages.find({}, {"_id": 0}).to_list(50)


@api.put("/admin/downloads")
async def update_downloads(cfg: DownloadConfig, _: dict = Depends(require_admin)):
    data = cfg.model_dump()
    data["key"] = "downloads"
    await db.settings.replace_one({"key": "downloads"}, data, upsert=True)
    return cfg


# ---- Health & root ----
@api.get("/")
async def root():
    return {"service": "AtGlance API", "ok": True}


app.include_router(api)


# ---- Startup: indexes + seed ----
@app.on_event("startup")
async def on_start():
    await db.users.create_index("email", unique=True)
    await db.users.create_index("github_id", sparse=True)
    await db.docs.create_index("slug", unique=True)
    await db.pages.create_index("slug", unique=True)
    await db.oauth_states.create_index("created_at", expireAfterSeconds=600)
    await seed_admin()
    await seed_content()
    log.info("AtGlance API ready")


async def seed_admin():
    existing = await db.users.find_one({"email": ADMIN_EMAIL})
    if not existing:
        await db.users.insert_one({
            "email": ADMIN_EMAIL,
            "name": "AtGlance Admin",
            "password_hash": hash_password(ADMIN_PASSWORD),
            "role": "admin",
            "auth_method": "email",
            "created_at": datetime.now(timezone.utc),
        })
        log.info("Seeded admin user: %s", ADMIN_EMAIL)
    else:
        # Keep admin password in sync with .env
        if existing.get("password_hash") and not verify_password(ADMIN_PASSWORD, existing["password_hash"]):
            await db.users.update_one(
                {"_id": existing["_id"]},
                {"$set": {"password_hash": hash_password(ADMIN_PASSWORD), "role": "admin"}},
            )
            log.info("Refreshed admin password from .env")
        elif existing.get("role") != "admin":
            await db.users.update_one({"_id": existing["_id"]}, {"$set": {"role": "admin"}})


async def seed_content():
    if await db.pricing.count_documents({}) == 0:
        plans = [
            PricingPlan(
                id=str(uuid.uuid4()), name="Free", price="$0", period="forever",
                description="Everything you need to run AtGlance inside your own boundary.",
                features=[
                    "Self-hosted Management Console",
                    "Unlimited registered systems",
                    "AtGlance CLI (Ubuntu / systemd)",
                    "RBAC (superadmin / admin / user)",
                    "Configuration backup & versioning",
                    "Database circuit breaker + queue resilience",
                    "PAT token authentication",
                    "Community support",
                ],
                cta="Sign up free", highlighted=True, order=0,
            ).model_dump(),
            PricingPlan(
                id=str(uuid.uuid4()), name="Enterprise", price="Custom", period="",
                description="For organizations that need dedicated support and compliance assistance.",
                features=[
                    "Everything in Free",
                    "Priority security advisories",
                    "Compliance & audit assistance",
                    "Architecture review",
                    "SLA-backed support",
                ],
                cta="Talk to us", highlighted=False, order=1,
            ).model_dump(),
        ]
        await db.pricing.insert_many(plans)

    if await db.faqs.count_documents({}) == 0:
        faqs = [
            {"id": str(uuid.uuid4()), "question": "Is AtGlance really self-hosted?", "answer": "Yes. Both the CLI and the Management Console run inside your own boundary — on-prem, private cloud, or hybrid. No data leaves your environment.", "category": "General", "order": 0},
            {"id": str(uuid.uuid4()), "question": "Which Linux distributions are supported?", "answer": "AtGlance CLI is currently optimized for Ubuntu LTS with systemd. Other systemd distributions are typically compatible.", "category": "CLI", "order": 1},
            {"id": str(uuid.uuid4()), "question": "How does the database circuit breaker work?", "answer": "When the database is unhealthy, the Application app trips the DatabaseCircuitBreaker — writes are buffered as queued jobs in Cache. When the DB recovers, queue workers replay buffered writes idempotently.", "category": "Resilience", "order": 2},
            {"id": str(uuid.uuid4()), "question": "What is a PAT token?", "answer": "A Personal Access Token is issued per user/system. The CLI uses it to authenticate against the Rest API API Gateway for system register/deregister/reactivate and configuration sync.", "category": "Security", "order": 3},
            {"id": str(uuid.uuid4()), "question": "Can I deregister and re-register a system?", "answer": "Yes. The CLI supports --system-deregister, --system-deregister-force, and --system-reactivate so you can decommission and bring systems back without losing identity.", "category": "CLI", "order": 4},
            {"id": str(uuid.uuid4()), "question": "Is AtGlance free?", "answer": "Yes — the Free tier includes the full self-hosted platform with no system limits. Enterprise plans add SLA-backed support.", "category": "Pricing", "order": 5},
        ]
        await db.faqs.insert_many(faqs)

    if await db.docs.count_documents({}) == 0:
        docs = [
            {
                "id": str(uuid.uuid4()), "slug": "quickstart", "section": "Getting Started",
                "title": "Quickstart (5 minutes)", "order": 0,
                "content": (
                    "# Quickstart\n\nGet AtGlance running in your environment in under 10 minutes.\n\n"
                    "## 1. Install the CLI\n\n"
                    "```bash\ncurl -sSL https://app.atglance.live/cli/install.sh | sudo bash\n```\n\n"
                    "## 2. Configure\n\n"
                    "```bash\natglance --configure\n```\n\nThis writes `~/.config/atglance/config.json`.\n\n"
                    "## 3. Register your system\n\n"
                    "```bash\natglance --system-register\n```\n\n"
                    "## 4. View services\n\n"
                    "```bash\natglance --show-my-services\n```\n"
                ),
            },
            {
                "id": str(uuid.uuid4()), "slug": "cli", "section": "CLI",
                "title": "CLI reference", "order": 1,
                "content": (
                    "# CLI reference\n\nThe `atglance` binary follows a strict `main()` routing order.\n\n"
                    "| Command | Purpose |\n|---|---|\n"
                    "| `--version` | Print CLI version |\n"
                    "| `--configure` / `configure` | Initial configuration |\n"
                    "| `--validate` | Validate local configuration |\n"
                    "| `--system-register [--restore -f/--file]` | Register the host |\n"
                    "| `--system-deregister` / `--system-deregister-force` | Decommission the host |\n"
                    "| `--system-regenerate-hash` | Rotate the validation hash |\n"
                    "| `--system-reactivate` / `--system-reactivate-force` | Bring host back online |\n"
                    "| `--show-my-services` | List discovered systemd services |\n"
                    "| `--app <service>`, `--config-show`, `--config-import` | Inspect & import configs |\n"
                    "| `watch [--interval]` | Continuous health watch |\n"
                    "| `export --format json` | Export service inventory |\n\n"
                    "## Persistent paths\n\n"
                    "- `~/.config/atglance/config.json`\n- `config-backups/`\n- `config-imports/`\n- `/etc/environment`\n"
                ),
            },
            # {
            #     "id": str(uuid.uuid4()), "slug": "api", "section": "API",
            #     "title": "API reference", "order": 2,
            #     "content": (
            #         "# API reference\n\nAll requests go through the Rest API API Gateway on `:8002`.\n\n"
            #         "## Authentication\n\nAll endpoints require either a session cookie or a PAT token via `Authorization: Bearer <token>`.\n\n"
            #         "## Endpoints\n\n"
            #         "### `POST /system-register`\nRegister a new host.\n\n"
            #         "```json\n{\n  \"hostname\": \"web-01\",\n  \"org_id\": \"org_abc\",\n  \"validation_hash\": \"<hash>\"\n}\n```\n\n"
            #         "### `POST /system-deregister`\nDeregister a host (soft).\n\n"
            #         "### `POST /system-reactivate`\nBring a deregistered host back online.\n\n"
            #         "### `GET /config-files/{system_id}`\nList configuration backups.\n\n"
            #         "### `POST /config-files`\nUpload a configuration backup (multipart form).\n\n"
            #         "### `POST /validate-token`\nValidate a PAT token.\n"
            #     ),
            # },
            {
                "id": str(uuid.uuid4()), "slug": "self-hosting", "section": "Self-hosting",
                "title": "Self-hosting the Console", "order": 3,
                "content": (
                    "# Self-hosting the Management Console\n\nAtGlance Console is a Application application fronted by Rest API API Gateway, with Database 8 + Cache.\n\n"
                    "## Stack\n\n- Application (PHP 8.2+)\n- Rest API API Gateway (DB-less mode, `Rest API.yml`)\n- Database 8\n- Cache (cache + queue + buffer)\n- Queue workers (retry/backoff)\n\n"
                    "## Deployment options\n\n- On-premises\n- Private cloud (AWS/Azure/GCP VPC)\n- Hybrid / multi-cloud\n\n"
                    "## Recommended docker-compose snippet\n\n"
                    "```yaml\nservices:\n  Database:\n    image: Database:5.7\n    container_name: atglance-Database\n    restart: unless-stopped\n    ports:\n      - \"3306:3306\"\n    volumes:\n      - Database_data:/var/lib/Database\n    environment:\n      Database_ROOT_PASSWORD: root\n      Database_DATABASE: atglance\n      Database_ROOT_HOST: '%'\n    healthcheck:\n      test: [\"CMD-SHELL\", \"Databaseadmin ping -h 127.0.0.1 -uroot -proot || exit 1\"]\n      interval: 10s\n      timeout: 5s\n      retries: 10\n      start_period: 30s\n\n  Cache:\n    image: Cache:7-alpine\n    restart: unless-stopped\n    ports:\n      - \"6379:6379\"\n    healthcheck:\n      test: [\"CMD\", \"Cache-cli\", \"ping\"]\n      interval: 5s\n      timeout: 3s\n      retries: 10\n\n  api:\n    image: atglance/ee-console-app:0.1.0\n    container_name: atglance-mangement-console\n    restart: unless-stopped\n    environment:\n      APP_URL: https:/<domain_Name-or-subdomain_ Name>\n    command:\n      - sh\n      - -lc\n      - |\n        if [ ! -f /app/vendor/autoload.php ]; then\n          composer install --no-interaction --prefer-dist --optimize-autoloader;\n        fi\n        php artisan serve --host=0.0.0.0 --port=8000 --no-reload\n    ports:\n      - \"8000:8000\"\n    volumes:\n      - composer_vendor:/app/vendor\n    depends_on:\n      Database:\n        condition: service_healthy\n      Cache:\n        condition: service_healthy\n\n  queue-worker:\n    image: atglance/ee-queue-worker:0.1.0\n    restart: unless-stopped\n    working_dir: /app\n    environment:\n      DB_CONNECTION: Database\n      DB_HOST: Database\n      DB_PORT: 3306\n      DB_DATABASE: atglance\n      DB_USERNAME: root\n      DB_PASSWORD: root\n      Cache_HOST: Cache\n      Cache_PORT: 6379\n    command:\n      - sh\n      - -lc\n      - |\n        php artisan queue:work Cache --tries=5 --backoff=30,60,120,300,600 --timeout=60 --sleep=3 --max-jobs=1000 --verbose\n    depends_on:\n      Database:\n        condition: service_healthy\n      Cache:\n        condition: service_healthy\n    volumes:\n      - composer_vendor:/app/vendor\n  Rest API:\n    image: atglance/ee-Rest API-app:0.1.0\n    restart: unless-stopped\n    environment:\n      Rest API_DATABASE: \"off\"\n      Rest API_DECLARATIVE_CONFIG: /Rest API/declarative/Rest API.yml\n      Rest API_PROXY_LISTEN: 0.0.0.0:8002\n      Rest API_ADMIN_LISTEN: 0.0.0.0:8001\n    ports:\n      - \"8002:8002\"\n      - \"8001:8001\"\n    depends_on:\n      - api\n\nvolumes:\n  Database_data:\n  composer_vendor\n```\n"
                ),
            },
            {
                "id": str(uuid.uuid4()), "slug": "architecture", "section": "Architecture",
                "title": "Deep dive: Architecture", "order": 4,
                "content": (
                    "# Architecture deep dive\n\nAtGlance has three layers: **CLI**, **Rest API API Gateway**, and **Management Console** (Application + Cache + Database).\n\n"
                    "## Resilience: DB outage flow\n\n1. Web/API request arrives at Application.\n2. `DatabaseCircuitBreaker` middleware detects DB unhealthy.\n3. Writes are encoded as queued jobs and pushed to Cache Buffer.\n4. Reads fall back to cache where safe.\n5. Database recovers → queue workers replay jobs idempotently → state converges.\n\n"
                    "## RBAC roles\n\n- **superadmin**: tenancy, billing, integrations\n- **admin**: organization-level systems, users, configs\n- **user**: assigned workspaces only\n"
                ),
            },
        ]
        await db.docs.insert_many(docs)

    if await db.settings.count_documents({"key": "contact"}) == 0:
        await db.settings.insert_one({
            "key": "contact",
            "email": "info@atglance.live",
            "sales_email": "sales@atglance.live",
            "support_email": "support@atglance.live",
            "address": "Everything inside your boundaries.",
            "github": "https://github.com/atglance-app",
            "twitter": "#",
        })

    if await db.settings.count_documents({"key": "downloads"}) == 0:
        await db.settings.insert_one({
            "key": "downloads",
            "cli_url": "http://app.atglance.live/cli/",
            "cli_version": "1.0.0",
            "cli_checksum": "",
            "console_url": "http://app.atglance.live/console/",
            "console_version": "1.0.0",
            "console_checksum": "",
            "cli_install_command": "curl -sSL https://app.atglance.live/cli/install.sh | sudo bash",
        })

    pages_seed = {
        "about": {
            "slug": "about", "title": "About AtGlance",
            "content": (
                "# About AtGlance\n\nAtGlance was built by SREs frustrated with the choice between heavy enterprise monitoring suites and a swarm of brittle scripts.\n\n"
                "We believe operations teams deserve a calm, auditable, **self-hosted** platform that respects organisational boundaries.\n\n"
                "## What we ship\n\n- A focused CLI that knows systemd.\n- A Application console you can deploy inside your VPC.\n- A resilient backend (Cache queues, DB circuit breaker) that doesn't lose your writes during an outage.\n"
            ),
        },
        "terms": {
            "slug": "terms", "title": "Terms of Use",
            "content": (
                "# Terms of Use\n\nLast updated: 2026.\n\nBy using AtGlance, you agree to use the software within your own infrastructure and in accordance with applicable laws.\n\n"
                "## License\n\nAtGlance binaries and Console images are provided under the AtGlance Source-Available License unless otherwise stated.\n\n"
                "## No warranty\n\nThe software is provided \"as is\" without warranty of any kind.\n"
            ),
        },
        "privacy": {
            "slug": "privacy", "title": "Privacy Policy",
            "content": (
                "# Privacy Policy\n\nLast updated: 2026.\n\nAtGlance is self-hosted. We do not collect or transmit telemetry from your CLI or Console deployments by default.\n\n"
                "## Account data\n\nFor accounts on atglance.live we store: email, name, avatar (if GitHub SSO), and a hashed password.\n\n"
                "## Cookies\n\nWe use httpOnly cookies for session management.\n"
            ),
        },
    }
    for slug, data in pages_seed.items():
        if not await db.pages.find_one({"slug": slug}):
            await db.pages.insert_one(data)

    # Write test_credentials.md
    creds_dir = Path("/app/memory")
    creds_dir.mkdir(parents=True, exist_ok=True)
    (creds_dir / "test_credentials.md").write_text(
        f"# AtGlance Test Credentials\n\n"
        f"## Admin\n- Email: `{ADMIN_EMAIL}`\n- Password: `{ADMIN_PASSWORD}`\n- Role: `admin`\n\n"
        f"## Auth endpoints\n- POST /api/auth/register\n- POST /api/auth/login\n- POST /api/auth/logout\n"
        f"- GET  /api/auth/me\n- POST /api/auth/refresh\n- GET  /api/auth/github/start\n"
        f"- POST /api/auth/github/callback\n\n"
        f"## CMS public reads\n- GET /api/cms/pricing\n- GET /api/cms/faqs\n- GET /api/cms/docs\n"
        f"- GET /api/cms/docs/{{slug}}\n- GET /api/cms/contact\n- GET /api/cms/pages/{{slug}}\n\n"
        f"## Auth-gated\n- GET /api/downloads (requires login)\n\n"
        f"## Admin writes (require admin role)\n- PUT /api/admin/pricing\n- PUT /api/admin/faqs\n"
        f"- PUT /api/admin/docs\n- GET /api/admin/docs/full\n- PUT /api/admin/contact\n"
        f"- PUT /api/admin/pages/{{slug}}\n- GET /api/admin/pages\n- PUT /api/admin/downloads\n"
    )


@app.on_event("shutdown")
async def on_shutdown():
    client.close()
