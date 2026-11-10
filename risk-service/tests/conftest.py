import pytest

from app import create_app


@pytest.fixture
def app():
    return create_app({"TESTING": True})


@pytest.fixture
def client(app):
    """A fake HTTP client: sends requests to the app without starting a server."""
    return app.test_client()
