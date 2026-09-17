# Tests

The project uses Node's built-in test runner.

Run the complete suite from the `IRIS` directory:

```powershell
npm test
```

## Coverage areas

- Chart column mapping and numeric parsing
- Chart grouping and circular chart labels
- Extracted text pairing
- Progressive and column-scoped table filtering
- Document text pagination
- Graph export normalization and printable sheets
- Frontend structural contracts for the modular entry point

Tests are intentionally dependency-light and exercise pure utilities or source-level contracts. Browser workflows should also be checked when changing parser loading, module initialization, or DOM event wiring.
