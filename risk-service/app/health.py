from flask import Blueprint, current_app, jsonify

health_bp = Blueprint("health", __name__)


@health_bp.get("/health")
def health():
    """Report that the service is up, and which service and model versions are running."""
    return jsonify(
        status="ok",
        service=current_app.config["SERVICE_NAME"],
        service_version=current_app.config["SERVICE_VERSION"],
        model_version=current_app.config["MODEL_VERSION"],
    )
