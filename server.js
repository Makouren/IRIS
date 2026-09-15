const express = require('express');
const path = require('path');
const cors = require('cors');
const fs = require('fs');

const app = express();
let PORT = parseInt(process.env.PORT, 10) || 3000;
const DB_FILE = path.join(__dirname, 'iris_database.json');
const SCANNER_SERVICE_URL = process.env.SCANNER_SERVICE_URL || 'http://127.0.0.1:8000';

app.use(cors());
app.use(express.json({ limit: '50mb' }));
app.use(express.urlencoded({ extended: true, limit: '50mb' }));

// Forward browser uploads to the Python scanner without exposing a second CORS boundary.
app.post('/api/scan-file', express.raw({ type: 'multipart/form-data', limit: '50mb' }), async (req, res) => {
  try {
    const scannerResponse = await fetch(`${SCANNER_SERVICE_URL}/scan-file`, {
      method: 'POST',
      headers: {
        'content-type': req.headers['content-type'],
        'content-length': String(req.body.length)
      },
      body: req.body
    });

    const payload = await scannerResponse.text();
    res.status(scannerResponse.status).type('application/json').send(payload);
  } catch (err) {
    console.error('Scanner service proxy error:', err.message);
    res.status(503).json({
      detail: 'The Python scanner service is unavailable. Start scanner_service on port 8000 and try again.'
    });
  }
});

// Helper to read database file
function readDb() {
  try {
    if (fs.existsSync(DB_FILE)) {
      const raw = fs.readFileSync(DB_FILE, 'utf8');
      return JSON.parse(raw);
    }
  } catch (err) {
    console.error('Error reading database file:', err);
  }
  return { records: [], settings: {}, logs: [] };
}

// Helper to write database file
function writeDb(data) {
  try {
    fs.writeFileSync(DB_FILE, JSON.stringify(data, null, 2), 'utf8');
    return true;
  } catch (err) {
    console.error('Error writing database file:', err);
    return false;
  }
}

// Serve static frontend files
app.use(express.static(path.join(__dirname)));

// Health check endpoint
app.get('/api/health', (req, res) => {
  res.json({
    status: 'online',
    service: 'IRIS AI File Scanner & Admin Data Engine',
    databaseRecords: readDb().records.length,
    timestamp: new Date().toISOString()
  });
});

// Database REST API Endpoints for Admin Review & Edit

// GET all records
app.get('/api/records', (req, res) => {
  const db = readDb();
  res.json(db.records || []);
});

// POST save new scanned record
app.post('/api/records', (req, res) => {
  const db = readDb();
  const record = {
    id: req.body.id || `rec_${Date.now()}_${Math.random().toString(36).substr(2, 5)}`,
    fileName: req.body.fileName || req.body.name || 'Untitled',
    fileType: req.body.fileType || req.body.type || 'unknown',
    fileSize: req.body.fileSize || req.body.size || 0,
    scannedAt: req.body.scannedAt || new Date().toISOString(),
    status: req.body.status || 'Pending Review',
    docType: req.body.docType || 'General Institutional Data',
    rawText: req.body.rawText || '',
    extractedData: req.body.extractedData || req.body.sheetsData || {},
    graphDrafts: req.body.graphDrafts || [],
    adminNotes: req.body.adminNotes || '',
    metadata: req.body.metadata || {}
  };

  db.records.unshift(record);
  writeDb(db);
  res.status(201).json(record);
});

// PUT update record (Admin Edit)
app.put('/api/records/:id', (req, res) => {
  const db = readDb();
  const index = db.records.findIndex(r => r.id === req.params.id);
  if (index === -1) {
    return res.status(404).json({ error: 'Record not found' });
  }

  db.records[index] = {
    ...db.records[index],
    ...req.body,
    updatedAt: new Date().toISOString()
  };

  writeDb(db);
  res.json(db.records[index]);
});

// DELETE record
app.delete('/api/records/:id', (req, res) => {
  const db = readDb();
  const index = db.records.findIndex(r => r.id === req.params.id);
  if (index === -1) {
    return res.status(404).json({ error: 'Record not found' });
  }

  const deleted = db.records.splice(index, 1);
  writeDb(db);
  res.json({ message: 'Record deleted', record: deleted[0] });
});

// Fallback to index.html
app.get('*', (req, res) => {
  res.sendFile(path.join(__dirname, 'index.html'));
});

// Start Server with Automatic Port Fallback if EADDRINUSE
function startServer(portToTry) {
  const server = app.listen(portToTry, () => {
    console.log(`====================================================`);
    console.log(`  IRIS AI File Scanner running at: http://localhost:${portToTry}`);
    console.log(`  Admin Data Engine & Draft Visualization Ready!`);
    console.log(`====================================================`);
  });

  server.on('error', (err) => {
    if (err.code === 'EADDRINUSE') {
      console.warn(`Port ${portToTry} is already in use. Trying port ${portToTry + 1}...`);
      startServer(portToTry + 1);
    } else {
      console.error('Server error:', err);
    }
  });
}

startServer(PORT);
