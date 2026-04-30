import { ArchDiagram } from "../components/ArchDiagram";
import { SectionHeading, FeatureCard } from "../components/FeatureCard";
import { RBACTable } from "../components/RBACTable";
import { CodeBlock } from "../components/Terminal";
import { StoryFlow } from "../components/StoryFlow";
import { Database, Workflow, ShieldCheck, GitBranch, Server, Network } from "lucide-react";

const ARCH_SYSTEM = "https://customer-assets.emergentagent.com/job_a89336b5-1df0-4e9a-bcfc-111dd4dd1c6a/artifacts/4r5l0e0a_AtGlance%20Management%20Tool%20System%20Architecture.png";

export default function Console() {
  return (
    <div data-testid="console-page" className="max-w-7xl mx-auto px-5 sm:px-8 py-16">
      <div className="text-[11px] uppercase tracking-[0.22em] text-amber-500 font-mono mb-4">Management Console</div>
      <h1 className="text-4xl sm:text-5xl lg:text-6xl font-semibold tracking-tighter text-zinc-50 leading-[1.05] mb-6 max-w-4xl">
        Self-hosted control plane.<br />Inside your boundary.
      </h1>
      <p className="text-lg text-zinc-400 max-w-3xl leading-relaxed">
        A Laravel application fronted by Kong, backed by MySQL 8 and Redis. Deploy it where your compliance team is comfortable — on-prem, private cloud, hybrid.
      </p>

      <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-5 mt-14">
        <FeatureCard testId="console-feat-rbac" icon={ShieldCheck} title="RBAC + Middleware" desc="auth.session, auth.pat, admin.role, super.admin.role — composable middleware enforced at the route layer." />
        <FeatureCard testId="console-feat-cb" icon={Database} title="DatabaseCircuitBreaker" desc="When MySQL is unhealthy, mutations are buffered as queued jobs. Workers replay on recovery, idempotent by design." />
        <FeatureCard testId="console-feat-kong" icon={Network} title="Kong DB-less" desc="kong.yml is declarative, version-controlled. Rate limits, ACLs, request transformations live next to your code." />
        <FeatureCard testId="console-feat-files" icon={GitBranch} title="Configuration history" desc="Every config upload becomes an immutable file in local FS or S3 with a row in the database." />
        <FeatureCard testId="console-feat-queue" icon={Workflow} title="Queue workers" desc="Retry/backoff, dead-letter inspection, replay buttons in the admin UI for buffered jobs." />
        <FeatureCard testId="console-feat-sso" icon={Server} title="SSO-ready" desc="Bring your own provider — GitHub, Azure AD, Okta, Auth0, Google — or stick to email/password + PAT." />
      </div>

      <div className="mt-20">
        <SectionHeading eyebrow="Architecture" title="The Console under the hood" />
        <div className="mt-8">
          <ArchDiagram testId="console-arch-diagram" src={ARCH_SYSTEM} alt="AtGlance system architecture" caption="Laravel · Kong · MySQL · Redis · File storage" />
        </div>
      </div>

      <div className="mt-20">
        <SectionHeading eyebrow="Resilience flow" title="What happens during a DB outage" />
        <div className="mt-8">
          <StoryFlow
            steps={[
              { title: "Detect", desc: "DatabaseCircuitBreaker probes MySQL. Threshold tripped → circuit open." },
              { title: "Buffer", desc: "Writes encoded as Job classes pushed to Redis queue 'buffer'." },
              { title: "Recover", desc: "Probes succeed again. Circuit half-open → full close." },
              { title: "Replay", desc: "Workers drain the buffer queue. Jobs are idempotent by design." },
            ]}
          />
        </div>
      </div>

      <div className="mt-20">
        <SectionHeading eyebrow="RBAC matrix" title="Three roles. One source of truth." />
        <div className="mt-8">
          <RBACTable />
        </div>
      </div>

      <div className="mt-20">
        <SectionHeading eyebrow="Deploy" title="Self-host it your way" />
        <div className="mt-8">
          <CodeBlock
            title="docker-compose.yml"
            code={`services:
  mysql:
    image: mysql:5.7
    container_name: atglance-mysql
    restart: unless-stopped
    ports:
      - "3306:3306"
    volumes:
      - mysql_data:/var/lib/mysql
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: atglance
      MYSQL_ROOT_HOST: '%'
    healthcheck:
      test: ["CMD-SHELL", "mysqladmin ping -h 127.0.0.1 -uroot -proot || exit 1"]
      interval: 10s
      timeout: 5s
      retries: 10
      start_period: 30s

  redis:
    image: redis:7-alpine
    restart: unless-stopped
    ports:
      - "6379:6379"
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 5s
      timeout: 3s
      retries: 10

  api:
    image: atglance/ee-console-app:0.1.0
    container_name: atglance-mangement-console
    restart: unless-stopped
    environment:
      APP_URL: https:/<domain_Name-or-subdomain_ Name>
    command:
      - sh
      - -lc
      - |
        if [ ! -f /app/vendor/autoload.php ]; then
          composer install --no-interaction --prefer-dist --optimize-autoloader;
        fi
        php artisan serve --host=0.0.0.0 --port=8000 --no-reload
    ports:
      - "8000:8000"
    volumes:
      - composer_vendor:/app/vendor
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_healthy

  queue-worker:
    image: atglance/ee-queue-worker:0.1.0
    restart: unless-stopped
    working_dir: /app
    environment:
      DB_CONNECTION: mysql
      DB_HOST: mysql
      DB_PORT: 3306
      DB_DATABASE: atglance
      DB_USERNAME: root
      DB_PASSWORD: root
      REDIS_HOST: redis
      REDIS_PORT: 6379
    command:
      - sh
      - -lc
      - |
        php artisan queue:work redis --tries=5 --backoff=30,60,120,300,600 --timeout=60 --sleep=3 --max-jobs=1000 --verbose
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_healthy
    volumes:
      - composer_vendor:/app/vendor
  kong:
    image: atglance/ee-kong-app:0.1.0
    restart: unless-stopped
    environment:
      KONG_DATABASE: "off"
      KONG_DECLARATIVE_CONFIG: /kong/declarative/kong.yml
      KONG_PROXY_LISTEN: 0.0.0.0:8002
      KONG_ADMIN_LISTEN: 0.0.0.0:8001
    ports:
      - "8002:8002"
      - "8001:8001"
    depends_on:
      - api

volumes:
  mysql_data:
  composer_vendor:`}
          />
        </div>
      </div>
    </div>
  );
}
