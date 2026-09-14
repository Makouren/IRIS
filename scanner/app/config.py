"""
Configuration settings for the IRIS AI File Scanner.

Loads from environment variables / .env file using Pydantic Settings.
"""

from pydantic_settings import BaseSettings
from typing import Optional


class Settings(BaseSettings):
    """Application settings loaded from environment variables or .env file."""

    # Google Gemini API key (optional — falls back to heuristic if missing)
    gemini_api_key: Optional[str] = None

    # Path to Tesseract binary (None = use system PATH)
    tesseract_cmd: Optional[str] = None

    # Maximum upload file size in megabytes
    max_file_size_mb: int = 25

    # Allowed file extensions (comma-separated string in .env)
    allowed_extensions: str = ".xlsx,.xls,.csv,.pdf,.docx,.png,.jpg,.jpeg,.webp"

    # Logging level
    log_level: str = "INFO"

    @property
    def max_file_size_bytes(self) -> int:
        """Maximum file size in bytes."""
        return self.max_file_size_mb * 1024 * 1024

    @property
    def allowed_extensions_set(self) -> set[str]:
        """Parsed set of allowed file extensions (lowercased)."""
        return {ext.strip().lower() for ext in self.allowed_extensions.split(",")}

    @property
    def gemini_enabled(self) -> bool:
        """Whether the Gemini API is configured and available."""
        return bool(self.gemini_api_key)

    model_config = {
        "env_file": ".env",
        "env_file_encoding": "utf-8",
        "extra": "ignore",
    }


# Singleton settings instance
settings = Settings()
