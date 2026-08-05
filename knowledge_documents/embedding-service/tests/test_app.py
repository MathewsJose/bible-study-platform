import numpy as np
import pytest
from fastapi.testclient import TestClient

from app import main


class FakeModel:
    def get_sentence_embedding_dimension(self):
        return 384

    def encode(self, texts, **kwargs):
        return np.array([[float(index), 0.1, 0.2] + [0.0] * 381 for index, _ in enumerate(texts)])


@pytest.fixture(autouse=True)
def fake_model(monkeypatch):
    main.model.cache_clear()
    monkeypatch.setattr(main, "model", lambda: FakeModel())
    yield
    main.model.cache_clear()


def test_health():
    response = TestClient(main.app).get("/health")

    assert response.status_code == 200
    assert response.json()["status"] == "ok"
    assert response.json()["dimensions"] == 384
    assert response.json()["loaded"] is True


def test_embed_one_text():
    response = TestClient(main.app).post("/embed", json={"texts": ["Why did Jesus become man?"]})

    assert response.status_code == 200
    payload = response.json()
    assert payload["model"] == "sentence-transformers/all-MiniLM-L6-v2"
    assert payload["dimensions"] == 384
    assert len(payload["embeddings"]) == 1
    assert len(payload["embeddings"][0]) == 384


def test_embed_multiple_texts_preserves_order():
    response = TestClient(main.app).post("/embed", json={"texts": ["Bible verse", "Catechism paragraph"]})

    assert response.status_code == 200
    embeddings = response.json()["embeddings"]
    assert embeddings[0][0] == 0.0
    assert embeddings[1][0] == 1.0


def test_empty_text_is_invalid():
    response = TestClient(main.app).post("/embed", json={"texts": [""]})

    assert response.status_code == 422


def test_invalid_request_is_rejected():
    response = TestClient(main.app).post("/embed", json={"text": "missing list"})

    assert response.status_code == 422
