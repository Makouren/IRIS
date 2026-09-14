"""
Integration tests for FastAPI endpoints (/health and /scan-file).
"""

import io
from fastapi.testclient import TestClient
import pytest

from app.main import app

client = TestClient(app)


def test_health_endpoint():
    """Test the GET /health liveness check."""
    response = client.get("/health")
    assert response.status_code == 200
    data = response.json()
    assert data["status"] == "ok"
    assert data["service"] == "iris-ai-file-scanner"
    assert "gemini_enabled" in data


def test_scan_csv_file():
    """Test POST /scan-file with a valid CSV file upload."""
    csv_content = (
        "Academic Program,QAO Score,Board Exam Passing Rate\n"
        "BS Computer Science,92.5,88.0%\n"
        "BS Information Technology,89.0,85.5%\n"
        "BS Information Systems,84.5,80.0%\n"
    )

    files = {
        "file": ("qao_report.csv", io.BytesIO(csv_content.encode("utf-8")), "text/csv")
    }

    response = client.post("/scan-file", files=files)
    assert response.status_code == 200

    data = response.json()
    assert data["status"] == "draft"
    assert data["filename"] == "qao_report.csv"
    assert data["file_type"] == "spreadsheet"
    assert len(data["extracted_fields"]) > 0

    # Verify chart suggestions
    assert len(data["chart_suggestions"]) > 0
    first_chart = data["chart_suggestions"][0]
    assert first_chart["chart_type"] in {"bar", "line", "pie", "scatter", "radar"}
    assert "echarts_option" in first_chart
    assert "series" in first_chart["echarts_option"]

    # Verify dashboard_ready payload
    assert len(data["dashboard_ready"]) > 0
    first_widget = data["dashboard_ready"][0]
    assert first_widget["suggested_title"]
    assert first_widget["suggested_section"]
    assert first_widget["echarts_option_json"]
    assert isinstance(first_widget["data_table"], list)


def test_scan_unsupported_file():
    """Test POST /scan-file with an unsupported extension returns 400."""
    fake_exe = b"MZ\x90\x00\x03\x00\x00\x00"
    files = {
        "file": ("malicious.exe", io.BytesIO(fake_exe), "application/octet-stream")
    }

    response = client.post("/scan-file", files=files)
    assert response.status_code == 400
    assert "Unsupported file type" in response.json()["detail"]
