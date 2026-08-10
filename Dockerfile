# ---- Stage 1: Build React Frontend ----
FROM node:20-alpine AS build-frontend
WORKDIR /app
COPY frontend/package.json frontend/yarn.lock* ./
RUN yarn install --network-timeout 600000
COPY frontend/ .
RUN yarn build

# ---- Stage 2: Final All-in-One Image ----
FROM mongo:7

# Install system dependencies and Python 3.11 via deadsnakes PPA
RUN apt-get update && apt-get install -y --no-install-recommends \
    software-properties-common \
    gnupg \
    curl \
    nginx \
    supervisor \
    && DEBIAN_FRONTEND=noninteractive add-apt-repository ppa:deadsnakes/ppa -y \
    && apt-get update && apt-get install -y --no-install-recommends \
    python3.11 \
    python3.11-dev \
    python3.11-distutils \
    && rm -rf /var/lib/apt/lists/* \
    && rm -rf /etc/nginx/sites-enabled/* \
    && rm -rf /etc/nginx/conf.d/*

# Install pip for Python 3.11
RUN curl -sS https://bootstrap.pypa.io/get-pip.py | python3.11

# Setup Python alias
RUN ln -sf /usr/bin/python3.11 /usr/bin/python
RUN ln -sf /usr/bin/python3.11 /usr/bin/python3

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1

WORKDIR /app

# Install Python dependencies
COPY backend/requirements.txt ./
RUN python3.11 -m pip install --upgrade pip && python3.11 -m pip install -r requirements.txt

# Copy backend source
COPY backend/ .

# Copy frontend build assets from Stage 1
COPY --from=build-frontend /app/build /usr/share/nginx/html

# Copy configuration files
COPY frontend/nginx.conf /etc/nginx/conf.d/default.conf
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Expose port 80
EXPOSE 80

# Start everything via supervisord
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
