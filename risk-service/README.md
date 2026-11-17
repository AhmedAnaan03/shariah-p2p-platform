# risk-service/: Flask risk-scoring service

A separate Python service that will train the credit-risk model (XGBoost, with SMOTE from
imbalanced-learn) and explain its scores with SHAP. The Laravel web app calls it over REST.
Right now it has a health check and the prepared training data (PB-12); the dataset, its
licence and how to download it are in [`DATA.md`](DATA.md).

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
`requirements-dev.txt` adds the test tools on top. `requirements-notebook.txt` adds the
Kaggle CLI, JupyterLab and matplotlib, needed only to download the data and run the
exploration notebook.

## Training data

The model is trained on LendingClub's public loan data (CC0). It is not in the repository:
see [`DATA.md`](DATA.md) to download it into `data/raw/` and run `python -m ml.prepare`,
which converts it to Parquet. The exploration notebook is `notebooks/PB-12-eda.ipynb`.

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
| `ml/dataset.py` | Dataset rules: default label, modelling population, the three feature sets (deployable, self-declared, full), and the role of every raw column |
| `ml/prepare.py` | `python -m ml.prepare`: raw CSV to Parquet, once |
| `notebooks/PB-12-eda.ipynb` | Exploration notebook (charts saved to `docs/report-notes/PB-12-*.png`) |
| `notebooks/plot_style.py` | Chart colours and layout taken from the design system |
| `data/raw/` | Downloaded data and the Parquet file (git-ignored) |
| `DATA.md` | Source, licence, size, download steps, feature sets |
| `pytest.ini` | Test folder and import path |
