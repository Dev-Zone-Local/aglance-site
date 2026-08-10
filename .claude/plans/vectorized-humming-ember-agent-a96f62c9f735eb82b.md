# Implementation Plan: Consolidate Services into All-in-One Docker Image

## Goal
Consolidate MongoDB, FastAPI backend, and React frontend into a single Docker image managed by `supervisord`.

## Current Architecture
- **Frontend**: React built with Node, served by Nginx (port 80).
- **Backend**: FastAPI on Python 3.11-slim (port 8001).
- **Database**: MongoDB 7 (port 27017).
- **Orchestration**: `docker-compose.yml` connecting services via a bridge network.

## Proposed All-in-One Architecture
- **Base Image**: `python:3.11-slim` (Debian Bookworm).
- **Process Manager**: `supervisord` to manage `mongod`, `uvicorn`, and `nginx`.
- **Frontend**: Static files served by Nginx, acting as a reverse proxy to the backend.
- **Backend**: Python application connecting to MongoDB via `localhost`.
- **Database**: MongoDB 7 installed directly in the container.

---

## Detailed Implementation Steps

### 1. Configuration Modifications

#### A. Frontend Nginx Configuration
- **File**: `frontend/nginx.conf`
- **Change**: Update the `proxy_pass` directive to point to `localhost` instead of the `backend` service name.
- **Update**: `proxy_pass http://backend:8001/api/;` $\rightarrow$ `proxy_pass http://localhost:8001/api/;`

#### B. Backend Environment Variables
- **Change**: Update the `MONGO_URL` to point to `localhost`.
- **Update**: `mongodb://mongo:27017` $\rightarrow$ `mongodb://localhost:27017`
- This will be handled via `supervisord.conf` environment settings or a default `ENV` in the Dockerfile.

### 2. Process Management Setup

Create a new `supervisord.conf` file in the root directory:

```ini
[supervisord]
nodaemon=true
user=root
logfile=/var/log/supervisor/supervisord.log
pidfile=/var/run/supervisord.pid

[program:mongodb]
command=/usr/bin/mongod --bind_ip_all --dbpath /data/db
autostart=true
autorestart=true
stderr_logfile=/var/log/mongodb.err.log
stdout_logfile=/var/log/mongodb.out.log

[program:backend]
command=/usr/local/bin/uvicorn server:app --host 0.0.0.0 --port 8001
directory=/app
autostart=true
autorestart=true
stderr_logfile=/var/log/backend.err.log
stdout_logfile=/var/log/backend.out.log
environment=MONGO_URL="mongodb://localhost:27017"

[program:nginx]
command=/usr/sbin/nginx -g 'daemon off;'
autostart=true
autorestart=true
stderr_logfile=/var/log/nginx.err.log
stdout_logfile=/var/log/nginx.out.log
```

### 3. Consolidated Dockerfile Design

The new Dockerfile will use a multi-stage build to keep the image size minimal.

```dockerfile
# ---- Stage 1: Build React Frontend ----
FROM node:20-alpine AS build-frontend
WORKDIR /app
# Copy only package files for better caching
COPY frontend/package.json frontend/yarn.lock* ./
RUN yarn install --network-timeout 600000
COPY frontend/ .
RUN yarn build

# ---- Stage 2: Final All-in-One Image ----
FROM python:3.11-slim

# Prevent Python from buffering stdout/stderr
ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_NO_CACHE_DIR=1 \
    PIP_DISABLE_PIP_VERSION_CHECK=1

WORKDIR /app

# 1. Install system dependencies (Nginx, Supervisor, and MongoDB prep)
RUN apt-get update && apt-get install -y --no-install-recommends \
    gnupg \
    curl \
    nginx \
    supervisor \
    build-essential \
    && rm -rf /var/lib/apt/lists/*

# 2. Install MongoDB 7 for Debian Bookworm
RUN curl -fsSL https://www.mongodb.org/static/pgp/server-7.0.asc | \
    gpg --dearmor -o /usr/share/keyrings/mongodb-server-7.0.gpg && \
    echo "deb [ signed-by=/usr/share/keyrings/mongodb-server-7.0.gpg ] http://repo.mongodb.org/apt/debian bookworm/mongodb-org/7.0 main" | \
    tee /etc/apt/sources.list.d/mongodb-org-7.0.list && \
    apt-get update && \
    apt-get install -y mongodb-org && \
    rm -rf /var/lib/apt/lists/*

# 3. Setup MongoDB data directory
RUN mkdir -p /data/db && chmod 777 /data/db

# 4. Install Python dependencies
COPY backend/requirements.txt ./
RUN pip install --upgrade pip && pip install -r requirements.txt

# 5. Copy backend source
COPY backend/ .

# 6. Copy frontend build assets from Stage 1
COPY --from=build-frontend /app/build /usr/share/nginx/html

# 7. Copy configuration files
COPY frontend/nginx.conf /etc/nginx/conf.d/default.conf
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Expose port 80 (Frontend/Proxy)
EXPOSE 80

# Start everything via supervisord
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
```

---

## Verification Strategy

### 1. Build and Run
```bash
docker build -t atglance-all-in-one -f Dockerfile .
docker run -d -p 8080:80 -v atglance_mongo_data:/data/db --name atglance-test atglance-all-in-one
```

### 2. Service Health Check
- **Logs**: Run `docker logs -f atglance-test`. Verify that `supervisord` starts and all three programs (`mongodb`, `backend`, `nginx`) are reported as `STARTED`.
- **Frontend**: Visit `http://localhost:8080` in a browser.
- **Backend API**: Visit `http://localhost:8080/api/` to verify the Nginx proxy is reaching the FastAPI backend.
- **Database**: Perform a login or registration action in the UI to ensure the backend is successfully communicating with MongoDB on `localhost:27017`.

### 3. Resilience Test
- Execute `docker exec atglance-test kill -9 <pid_of_backend>`.
- Check logs to verify that `supervisord` automatically restarts the backend process.

## Key Trade-offs & Decisions
- **Base Image**: Chose `python:3.11-slim` (Debian) over Alpine because MongoDB installation on Alpine is significantly more complex and lacks official support for version 7.
- **Process Manager**: `supervisord` was chosen as required to provide reliable process monitoring and restart capabilities within a single container.
- **Data Persistence**: Continued use of `/data/db` as the standard MongoDB data path, requiring a volume mount for persistence.
