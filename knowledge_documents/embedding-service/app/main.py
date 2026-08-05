from functools import lru_cache
import os

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field
from sentence_transformers import SentenceTransformer


MODEL_NAME = os.getenv("LOCAL_EMBEDDING_MODEL", "sentence-transformers/all-MiniLM-L6-v2")
EXPECTED_DIMENSIONS = int(os.getenv("LOCAL_EMBEDDING_DIMENSIONS", "384"))

app = FastAPI(title="Local Embedding Service")


class EmbedRequest(BaseModel):
    texts: list[str] = Field(min_length=1)


class EmbedResponse(BaseModel):
    embeddings: list[list[float]]
    model: str
    dimensions: int


@lru_cache(maxsize=1)
def model() -> SentenceTransformer:
    return SentenceTransformer(MODEL_NAME, device="cpu")


def model_dimensions() -> int:
    dimensions = int(model().get_sentence_embedding_dimension())
    if dimensions != EXPECTED_DIMENSIONS:
        raise RuntimeError(
            f"Model {MODEL_NAME} returned {dimensions} dimensions; expected {EXPECTED_DIMENSIONS}."
        )

    return dimensions


@app.get("/health")
def health() -> dict[str, str | int | bool]:
    try:
        dimensions = model_dimensions()
    except Exception as exc:
        return {
            "status": "error",
            "model": MODEL_NAME,
            "dimensions": EXPECTED_DIMENSIONS,
            "loaded": False,
            "message": str(exc),
        }

    return {
        "status": "ok",
        "model": MODEL_NAME,
        "dimensions": dimensions,
        "loaded": True,
    }


@app.post("/embed", response_model=EmbedResponse)
def embed(request: EmbedRequest) -> EmbedResponse:
    texts = [text.strip() for text in request.texts]

    if any(text == "" for text in texts):
        raise HTTPException(status_code=422, detail="texts must not contain empty strings")

    try:
        dimensions = model_dimensions()
        embeddings = model().encode(
            texts,
            batch_size=len(texts),
            convert_to_numpy=True,
            normalize_embeddings=True,
            show_progress_bar=False,
        )
    except Exception as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc

    return EmbedResponse(
        embeddings=embeddings.astype(float).tolist(),
        model=MODEL_NAME,
        dimensions=dimensions,
    )
