"""Tests for GET /health and the service skeleton (PB-03)."""

import re
from pathlib import Path

from app import create_app

REQUIREMENTS = Path(__file__).resolve().parent.parent / "requirements.txt"


def test_health_returns_200_json(client):
    response = client.get("/health")

    assert response.status_code == 200
    assert response.is_json


def test_health_reports_service_name_and_version(client):
    body = client.get("/health").get_json()

    assert body["status"] == "ok"
    assert body["service"] == "risk-service"
    assert body["service_version"] == "0.1.0"


def test_model_version_is_null_when_no_model_is_configured(monkeypatch):
    monkeypatch.delenv("MODEL_VERSION", raising=False)

    body = create_app().test_client().get("/health").get_json()

    assert body["model_version"] is None


def test_blank_model_version_counts_as_no_model(monkeypatch):
    monkeypatch.setenv("MODEL_VERSION", "")

    body = create_app().test_client().get("/health").get_json()

    assert body["model_version"] is None


def test_model_version_is_read_from_environment(monkeypatch):
    monkeypatch.setenv("MODEL_VERSION", "xgb-2026-11-11")

    body = create_app().test_client().get("/health").get_json()

    assert body["model_version"] == "xgb-2026-11-11"


def test_post_to_health_returns_405_json(client):
    response = client.post("/health", json={})

    assert response.status_code == 405
    assert response.get_json()["error"] == "Method Not Allowed"


def test_unknown_url_returns_404_json(client):
    response = client.get("/does-not-exist")

    assert response.status_code == 404
    assert response.get_json()["error"] == "Not Found"


def test_requirements_pin_every_ml_library():
    pinned = {}
    for line in REQUIREMENTS.read_text().splitlines():
        match = re.fullmatch(r"([A-Za-z0-9_-]+)==(\S+)", line.strip())
        if match:
            pinned[match.group(1).lower()] = match.group(2)

    for package in ["pandas", "numpy", "scikit-learn", "xgboost", "imbalanced-learn", "shap", "flask"]:
        assert package in pinned, f"{package} is not pinned with == in requirements.txt"
