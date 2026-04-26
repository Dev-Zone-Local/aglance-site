# ---- Stage 1: build the React app ----
FROM node:20-alpine AS build

WORKDIR /app

# Build-time backend URL. Default empty so the app uses same-origin "/api"
# (nginx proxies /api to the backend container).
ARG REACT_APP_BACKEND_URL=""
ENV REACT_APP_BACKEND_URL=$REACT_APP_BACKEND_URL
ENV CI=false

COPY package.json yarn.lock ./
RUN yarn install --frozen-lockfile --network-timeout 600000

COPY . .

RUN yarn build

# ---- Stage 2: serve with nginx ----
FROM nginx:1.27-alpine AS runtime

# Replace default config
COPY nginx.conf /etc/nginx/conf.d/default.conf

# Copy CRA build output
COPY --from=build /app/build /usr/share/nginx/html

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD wget -qO- http://127.0.0.1/ >/dev/null 2>&1 || exit 1

CMD ["nginx", "-g", "daemon off;"]
