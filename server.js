const express = require('express');
const path = require('path');
const cors = require('cors');
const fs = require('fs');

const app = express();
let PORT = parseInt(process.env.PORT, 10) || 3000;
const DB_FILE = path.join(__dirname, 'iris_database.json');

app.use(cors());
app.use(express.json({ limit: '50mb' }));
app.use(express.urlencoded({ extended: true, limit: '50mb' }));

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
    service: 'IRIS AI File Scanner & Admin DB Engine',
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
    fileName: req.body.fileName || 'Untitled',
    fileType: req.body.fileType || 'unknown',
    fileSize: req.body.fileSize || 0,
    scannedAt: new Date().toISOString(),
    status: req.body.status || 'Pending Review',
    riskScore: req.body.riskScore || 0,
    riskLevel: req.body.riskLevel || 'SAFE',
    docType: req.body.docType || 'General Document',
    rawText: req.body.rawText || '',
    extractedData: req.body.extractedData || {},
    findings: req.body.findings || [],
    graphDrafts: req.body.graphDrafts || [],
    adminNotes: req.body.adminNotes || ''
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

// GET export SQL dump
app.get('/api/export-sql', (req, res) => {
  const db = readDb();
  let sql = `-- IRIS AI Database Export\n-- Generated at: ${new Date().toISOString()}\n\n`;
  sql += `CREATE TABLE IF NOT EXISTS iris_scans (\n`;
  sql += `  id VARCHAR(64) PRIMARY KEY,\n`;
  sql += `  file_name VARCHAR(255),\n`;
  sql += `  file_type VARCHAR(32),\n`;
  sql += `  doc_type VARCHAR(128),\n`;
  sql += `  risk_score INT,\n`;
  sql += `  risk_level VARCHAR(32),\n`;
  sql += `  status VARCHAR(32),\n`;
  sql += `  scanned_at DATETIME,\n`;
  sql += `  admin_notes TEXT\n`;
  sql += `);\n\n`;

  (db.records || []).forEach(r => {
    const escName = (r.fileName || '').replace(/'/g, "''");
    const escNotes = (r.adminNotes || '').replace(/'/g, "''");
    sql += `INSERT INTO iris_scans (id, file_name, file_type, doc_type, risk_score, risk_level, status, scanned_at, admin_notes) VALUES ('${r.id}', '${escName}', '${r.fileType}', '${r.docType}', ${r.riskScore}, '${r.riskLevel}', '${r.status}', '${r.scannedAt}', '${escNotes}');\n`;
  });

  res.setHeader('Content-Type', 'text/plain');
  res.setHeader('Content-Disposition', 'attachment; filename="iris_database_export.sql"');
  res.send(sql);
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
    console.log(`  Admin Database & Graph Engine Ready!`);
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
