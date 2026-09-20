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
- Graph export normalization, SQL-formatted text content, and printable sheets
- Frontend structural contracts for the modular entry point

Tests are intentionally dependency-light and exercise pure utilities or source-level contracts. Browser workflows should also be checked when changing parser loading, module initialization, or DOM event wiring.

Saved Dashboard Graph coverage includes FILE dropdown filtering, selected-graph bulk state, data-only SQL-formatted `.txt` export, and combined printable sheet generation for Print All.

## v7 verification

The v7 refactor is verified with the existing suite after endpoint configurability and chart host-parameter changes. The suite confirms chart mapping, filtering, exports, pagination, and frontend structural contracts; it does not replace a browser smoke test or an end-to-end scanner-service integration test.
