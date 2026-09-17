# Secondary Scanner Service

`scanner_service/` contains a separate Python/FastAPI implementation for document parsing. It is not required by the active Node.js frontend runtime.

## Contents

- `main.py`: FastAPI application entry point
- `app/config.py`: service configuration
- `app/parsers/`: Python parsers for DOCX, images, PDFs, and spreadsheets
- `app/services/`: dispatching and chart suggestion services
- `Dockerfile` and `docker-compose.yml`: container deployment files
- `requirements.txt`: Python dependencies

## Run with Docker

```bash
docker compose up --build
```

## Run locally

```bash
pip install -r requirements.txt
uvicorn main:app --reload
```

Use this service only when integrating the Python parsing path. The main application currently uses `server.js`, `js/scanner.js`, and the browser parser scripts instead.
