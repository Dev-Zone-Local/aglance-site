# AtGlance

AtGlance is a full-stack application with a FastAPI backend and React frontend, containerized with Docker and orchestrated using Docker Compose.

## Overview

- **Backend**: FastAPI server with MongoDB integration for data persistence
- **Frontend**: React application served via Nginx
- **Database**: MongoDB for data storage
- **Container Orchestration**: Docker Compose for local development and deployment

## Prerequisites

- Docker and Docker Compose installed on your system
- Node.js 20+ (for local frontend development)
- Python 3.10+ (for local backend development)

## Quick Start

### 1. Set up environment variables

Copy the example environment file and update with your values:

```bash
cp .env.docker.example .env
```

Edit `.env` and configure:
- `JWT_SECRET` - Secure secret for JWT token signing (required)
- `ADMIN_PASSWORD` - Password for admin account (required)
- `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET`, `GITHUB_REDIRECT_URI` - Optional, for GitHub OAuth
- Other optional settings (see `.env` file for defaults)

### 2. Build and run with Docker Compose

```bash
docker compose up -d --build
```

The application will be available at `http://localhost:8080`

### 3. Stop the application

```bash
docker compose down
```

## Project Structure

```
.
├── backend/              # FastAPI application
│   ├── Dockerfile
│   ├── requirements.txt
│   ├── server.py
│   └── tests/
├── frontend/             # React application
│   ├── Dockerfile
│   ├── nginx.conf
│   ├── package.json
│   ├── public/
│   ├── src/
│   │   ├── components/   # React components
│   │   ├── pages/        # Page components
│   │   ├── lib/          # Utilities and API client
│   │   └── hooks/        # Custom React hooks
│   └── plugins/          # Webpack plugins (health checks)
├── docker-compose.yml    # Docker Compose configuration
├── .env                  # Environment variables (create from .env.docker.example)
└── design_guidelines.json # Design system configuration
```

## Environment Variables

### Backend Configuration

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `MONGO_URL` | No | `mongodb://mongo:27017` | MongoDB connection string |
| `DB_NAME` | No | `atglance_db` | Database name |
| `JWT_SECRET` | **Yes** | - | Secret key for JWT signing |
| `ADMIN_EMAIL` | No | `admin@atglance.io` | Default admin email |
| `ADMIN_PASSWORD` | **Yes** | - | Default admin password |
| `GITHUB_CLIENT_ID` | No | - | GitHub OAuth client ID |
| `GITHUB_CLIENT_SECRET` | No | - | GitHub OAuth client secret |
| `GITHUB_REDIRECT_URI` | No | - | GitHub OAuth redirect URI |
| `FRONTEND_URL` | No | `http://localhost:8080` | Frontend URL for redirects |
| `CORS_ORIGINS` | No | - | CORS allowed origins |

### Frontend Configuration

| Variable | Default | Description |
|----------|---------|-------------|
| `REACT_APP_BACKEND_URL` | `/api` | Backend API URL (empty = same-origin proxy) |

## Service Details

### MongoDB
- **Image**: mongo:7
- **Port**: 27017 (internal)
- **Health Check**: Ping command every 15 seconds
- **Volume**: `mongo_data` persists database across restarts

### Backend
- **Build**: From `./backend/Dockerfile`
- **Port**: 8000 (internal, proxied through Nginx)
- **Dependencies**: Requires MongoDB to be healthy
- **Environment**: All variables from `.env` are passed to the container

### Frontend
- **Build**: From `./frontend/Dockerfile`
- **Port**: 80 (Nginx)
- **External Port**: Configurable via `FRONTEND_PORT` env variable (default: 8080)
- **Dependencies**: Requires backend to be running

## Local Development

### Backend Development

```bash
cd backend

# Install dependencies
pip install -r requirements.txt

# Run the server
python server.py
```

### Frontend Development

```bash
cd frontend

# Install dependencies
yarn install

# Start development server
yarn start
```

## Docker Build

### Building the Backend
```bash
docker build -f backend/Dockerfile -t atglance-backend:latest ./backend
```

### Building the Frontend
```bash
docker build -f frontend/Dockerfile -t atglance-frontend:latest ./frontend
```

## Troubleshooting

### Port Already in Use
If port 8080 is already in use, change it:
```bash
FRONTEND_PORT=3000 docker compose up -d
```

### Database Connection Issues
Check MongoDB is running:
```bash
docker logs atglance-mongo
```

### Backend Startup Issues
View backend logs:
```bash
docker logs atglance-backend
```

### Frontend Build Failures
Check Node.js version requirements:
```bash
node --version  # Should be 20+
yarn --version
```

## Testing

### Backend Tests
```bash
cd backend
pytest
```

### Running Tests in Docker
```bash
docker compose run backend pytest
```

## Health Checks

- **MongoDB**: Responds to mongosh ping command every 15 seconds
- **Frontend**: Exposed via `/health` endpoint from Nginx
- **Backend**: Check logs for startup confirmation

## Networking

All services communicate through the `atglance` Docker network:
- Frontend connects to backend via `http://atglance-backend:8000`
- Backend connects to MongoDB via `mongodb://mongo:27017`

## Additional Notes

- All containers automatically restart unless stopped (`restart: unless-stopped`)
- MongoDB data is persisted in the `mongo_data` volume
- Nginx acts as a reverse proxy, proxying `/api` requests to the backend
- The frontend is built as a static React app and served via Nginx

## Documentation

- See `design_guidelines.json` for design system specifications
- See `frontend/README.md` for frontend-specific documentation
- See `DOCKER.md` for detailed Docker configuration notes
