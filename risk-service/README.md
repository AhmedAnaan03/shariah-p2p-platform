# risk-service/: Flask risk-scoring service

A separate Python service that will train the credit-risk model (XGBoost, with SMOTE from
imbalanced-learn) and explain its scores with SHAP. The Laravel web app calls it over REST.
Right now it only has a health check. Dataset notes go in `DATA.md` (PB-12).

## Requirements

| Tool | Version | macOS install |
|---|---|---|
| Python | 3.12 | [python.org](https://www.python.org/downloads/) or `brew install python@3.12` |
| OpenMP runtime | any | `brew install libomp` (XGBoost needs it on macOS and will not import without it) |

## First-time setup

```sh
cd risk-service
python3.12 -m venv .venv
source .venv/bin/activate
pip install -r requirements-dev.txt     # runtime libraries plus pytest
```

`requirements.txt` holds the runtime libraries, pinned to exact versions.
`requirements-dev.txt` adds the test tools on top.

## Running locally

```sh
flask --app app run --port 5001
curl http://localhost:5001/health
```

```json
{"model_version":null,"service":"risk-service","service_version":"0.1.0","status":"ok"}
```

Port 5001 is used because macOS's AirPlay Receiver already listens on Flask's default 5000.

`model_version` is `null` until a trained model exists (PB-22). It can be set by hand with
`MODEL_VERSION=xgb-test flask --app app run --port 5001`.

## Endpoints

| Method | Path | Response |
|---|---|---|
| GET | `/health` | `200` with `status`, `service`, `service_version`, `model_version` |

Errors (for example `404` for an unknown path, `405` for a wrong method) come back as JSON:
`{"error": "Not Found", "message": "..."}`.

## Tests

```sh
pytest
```

## Layout

| Path | Contents |
|---|---|
| `app/__init__.py` | `create_app()` factory: config, routes, JSON error handler |
| `app/health.py` | `GET /health` blueprint |
| `tests/` | pytest tests; `conftest.py` gives each test a fresh app and test client |
| `pytest.ini` | Test folder and import path |
