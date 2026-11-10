"""Risk-scoring service: a small Flask app that the Laravel web app calls over REST."""

import os

from flask import Flask, jsonify
from werkzeug.exceptions import HTTPException

SERVICE_NAME = "risk-service"
SERVICE_VERSION = "0.1.0"


def create_app(test_config=None):
    """Build and configure the Flask app (the "application factory" pattern).

    `flask --app app run` finds this function automatically. Tests call it directly
    with `test_config` so each test gets a fresh app with its own settings.
    """
    app = Flask(__name__)

    app.config.from_mapping(
        SERVICE_NAME=SERVICE_NAME,
        SERVICE_VERSION=SERVICE_VERSION,
        # No trained model exists yet. PB-22 replaces this with the version of the
        # saved model file. An empty value counts as "no model".
        MODEL_VERSION=os.environ.get("MODEL_VERSION") or None,
    )
    if test_config is not None:
        app.config.update(test_config)

    from app.health import health_bp

    app.register_blueprint(health_bp)

    @app.errorhandler(HTTPException)
    def json_error(error):
        # Laravel reads every response as JSON, so errors such as 404 and 405 are
        # JSON too instead of Flask's default HTML error page.
        return jsonify(error=error.name, message=error.description), error.code

    return app
