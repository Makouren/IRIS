const express = require('express');
const path = require('path');
const cors = require('cors');
const mysql = require('mysql2/promise');

const app = express();
let PORT = parseInt(process.env.PORT, 10) || 3000;

app.use(cors());
app.use(express.json({ limit: '50mb' }));
app.use(express.urlencoded({ extended: true, limit: '50mb' }));

// Database connection pool
const pool = mysql.createPool({
  host: 'localhost',
  user: 'root',
  password: '',
  database: 'iris_db',
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0
});

// Initialize Database Table
async function initDb() {
  try {
    await pool.query(`
      CREATE TABLE IF NOT EXISTS records (
        id VARCHAR(255) PRIMARY KEY,
        fileName VARCHAR(255),
        fileType VARCHAR(50),
        fileSize INT,
        scannedAt DATETIME,
        status VARCHAR(50),
        docType VARCHAR(100),
        rawText LONGTEXT,
        extractedData JSON,
        graphDrafts JSON,
        adminNotes TEXT,
        metadata JSON,
        updatedAt DATETIME
      )
    `);

    await pool.query(`
      CREATE TABLE IF NOT EXISTS saved_graphs (
        id VARCHAR(255) PRIMARY KEY,
        record_id VARCHAR(255) NOT NULL,
        title VARCHAR(255),
        chart_type VARCHAR(50),
        labels JSON,
        values_data JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (record_id) REFERENCES records(id) ON DELETE CASCADE
      )
    `);
    console.log('MySQL Database initialized: records & saved_graphs tables ready.');
  } catch (error) {
    console.error('Error initializing database:', error);
  }
}

initDb();

// Serve static frontend files
app.use(express.static(path.join(__dirname)));

// Health check endpoint
app.get('/api/health', async (req, res) => {
  try {
    const [rows] = await pool.query('SELECT COUNT(*) as count FROM records');
    res.json({
      status: 'online',
      service: 'IRIS AI File Scanner & Admin Data Engine (MySQL)',
      databaseRecords: rows[0].count,
      timestamp: new Date().toISOString()
    });
  } catch (error) {
    res.status(500).json({ status: 'error', message: error.message });
  }
});

// Database REST API Endpoints for Admin Review & Edit

// GET all records
app.get('/api/records', async (req, res) => {
  try {
    const [rows] = await pool.query('SELECT * FROM records ORDER BY scannedAt DESC');
    // Parse JSON columns back into objects
    rows.forEach(row => {
      try {
        if (typeof row.extractedData === 'string') row.extractedData = JSON.parse(row.extractedData);
        if (typeof row.graphDrafts === 'string') row.graphDrafts = JSON.parse(row.graphDrafts);
        if (typeof row.metadata === 'string') row.metadata = JSON.parse(row.metadata);
      } catch(e) {
        // Ignore parse errors if data is already an object or invalid
      }
    });
    res.json(rows);
  } catch (error) {
    console.error(error);
    res.status(500).json({ error: 'Failed to fetch records' });
  }
});

// POST save new scanned record
app.post('/api/records', async (req, res) => {
  try {
    const record = {
      id: req.body.id || `rec_${Date.now()}_${Math.random().toString(36).substr(2, 5)}`,
      fileName: req.body.fileName || req.body.name || 'Untitled',
      fileType: req.body.fileType || req.body.type || 'unknown',
      fileSize: req.body.fileSize || req.body.size || 0,
      scannedAt: req.body.scannedAt ? new Date(req.body.scannedAt) : new Date(),
      status: req.body.status || 'Pending Review',
      docType: req.body.docType || 'General Institutional Data',
      rawText: req.body.rawText || '',
      extractedData: JSON.stringify(req.body.extractedData || req.body.sheetsData || {}),
      graphDrafts: JSON.stringify(req.body.graphDrafts || []),
      adminNotes: req.body.adminNotes || '',
      metadata: JSON.stringify(req.body.metadata || {})
    };

    const query = `
      INSERT INTO records (id, fileName, fileType, fileSize, scannedAt, status, docType, rawText, extractedData, graphDrafts, adminNotes, metadata)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `;

    const values = [
      record.id, record.fileName, record.fileType, record.fileSize, record.scannedAt,
      record.status, record.docType, record.rawText, record.extractedData, record.graphDrafts,
      record.adminNotes, record.metadata
    ];

    await pool.query(query, values);

    // Parse JSON back for the response
    record.extractedData = JSON.parse(record.extractedData);
    record.graphDrafts = JSON.parse(record.graphDrafts);
    record.metadata = JSON.parse(record.metadata);

    res.status(201).json(record);
  } catch (error) {
    console.error(error);
    res.status(500).json({ error: 'Failed to insert record' });
  }
});

// PUT update record (Admin Edit)
app.put('/api/records/:id', async (req, res) => {
  try {
    const { id } = req.params;
    const updates = req.body;
    let setClause = [];
    let values = [];

    // Helper to add to SET clause
    const addUpdate = (field, val, isJson = false) => {
      if (val !== undefined) {
        setClause.push(`${field} = ?`);
        values.push(isJson ? JSON.stringify(val) : val);
      }
    };

    addUpdate('fileName', updates.fileName);
    addUpdate('fileType', updates.fileType);
    addUpdate('fileSize', updates.fileSize);
    if(updates.scannedAt) addUpdate('scannedAt', new Date(updates.scannedAt));
    addUpdate('status', updates.status);
    addUpdate('docType', updates.docType);
    addUpdate('rawText', updates.rawText);
    addUpdate('extractedData', updates.extractedData, true);
    addUpdate('graphDrafts', updates.graphDrafts, true);
    addUpdate('adminNotes', updates.adminNotes);
    addUpdate('metadata', updates.metadata, true);
    
    setClause.push('updatedAt = ?');
    values.push(new Date());

    if (setClause.length === 1) { // Only updatedAt
      return res.status(400).json({ error: 'No fields to update' });
    }

    values.push(id);

    const query = `UPDATE records SET ${setClause.join(', ')} WHERE id = ?`;
    const [result] = await pool.query(query, values);

    if (result.affectedRows === 0) {
      return res.status(404).json({ error: 'Record not found' });
    }

    // Fetch the updated record
    const [rows] = await pool.query('SELECT * FROM records WHERE id = ?', [id]);
    const updatedRecord = rows[0];
    if (updatedRecord) {
      try {
        if (typeof updatedRecord.extractedData === 'string') updatedRecord.extractedData = JSON.parse(updatedRecord.extractedData);
        if (typeof updatedRecord.graphDrafts === 'string') updatedRecord.graphDrafts = JSON.parse(updatedRecord.graphDrafts);
        if (typeof updatedRecord.metadata === 'string') updatedRecord.metadata = JSON.parse(updatedRecord.metadata);
      } catch(e) {}
    }
    res.json(updatedRecord);

  } catch (error) {
    console.error(error);
    res.status(500).json({ error: 'Failed to update record' });
  }
});

// DELETE record
app.delete('/api/records/:id', async (req, res) => {
  try {
    const { id } = req.params;
    
    // First get it so we can return what was deleted
    const [rows] = await pool.query('SELECT * FROM records WHERE id = ?', [id]);
    
    if (rows.length === 0) {
      return res.status(404).json({ error: 'Record not found' });
    }

    await pool.query('DELETE FROM records WHERE id = ?', [id]);
    
    res.json({ message: 'Record deleted', record: rows[0] });
  } catch (error) {
    console.error(error);
    res.status(500).json({ error: 'Failed to delete record' });
  }
});

// Saved graphs endpoints
app.get('/api/graphs', async (req, res) => {
  try {
    const [rows] = await pool.query('SELECT * FROM saved_graphs ORDER BY created_at DESC');
    const normalized = rows.map(row => {
      try {
        row.labels = typeof row.labels === 'string' ? JSON.parse(row.labels) : (row.labels || []);
        row.values_data = typeof row.values_data === 'string' ? JSON.parse(row.values_data) : (row.values_data || []);
      } catch (e) {
        row.labels = [];
        row.values_data = [];
      }
      return row;
    });

    res.json(normalized);
  } catch (error) {
    console.error(error);
    res.status(500).json({ error: 'Failed to fetch saved graphs' });
  }
});

app.post('/api/graphs', async (req, res) => {
  try {
    const graph = {
      id: req.body.id || `graph_${Date.now()}_${Math.random().toString(36).substr(2, 6)}`,
      record_id: req.body.record_id || req.body.recordId,
      title: req.body.title || 'Saved Chart',
      chart_type: req.body.chart_type || req.body.chartType || 'bar',
      labels: JSON.stringify(req.body.labels || []),
      values_data: JSON.stringify(req.body.values_data || req.body.valuesData || req.body.data || [])
    };

    if (!graph.record_id) {
      return res.status(400).json({ error: 'record_id is required' });
    }

    const [recordRows] = await pool.query('SELECT id FROM records WHERE id = ?', [graph.record_id]);
    if (recordRows.length === 0) {
      return res.status(404).json({ error: 'Record not found' });
    }

    await pool.query(
      `INSERT INTO saved_graphs (id, record_id, title, chart_type, labels, values_data) VALUES (?, ?, ?, ?, ?, ?)`,
      [graph.id, graph.record_id, graph.title, graph.chart_type, graph.labels, graph.values_data]
    );

    res.status(201).json({
      ...graph,
      labels: JSON.parse(graph.labels),
      values_data: JSON.parse(graph.values_data)
    });
  } catch (error) {
    console.error(error);
    res.status(500).json({ error: 'Failed to save graph' });
  }
});

app.get('/api/graphs/:recordId', async (req, res) => {
  try {
    const { recordId } = req.params;
    const [rows] = await pool.query('SELECT * FROM saved_graphs WHERE record_id = ? ORDER BY created_at DESC', [recordId]);

    const normalized = rows.map(row => {
      try {
        row.labels = typeof row.labels === 'string' ? JSON.parse(row.labels) : (row.labels || []);
        row.values_data = typeof row.values_data === 'string' ? JSON.parse(row.values_data) : (row.values_data || []);
      } catch (e) {
        row.labels = [];
        row.values_data = [];
      }
      return row;
    });

    res.json(normalized);
  } catch (error) {
    console.error(error);
    res.status(500).json({ error: 'Failed to fetch graphs' });
  }
});

app.delete('/api/graphs/:id', async (req, res) => {
  try {
    const { id } = req.params;
    const [rows] = await pool.query('SELECT * FROM saved_graphs WHERE id = ?', [id]);

    if (rows.length === 0) {
      return res.status(404).json({ error: 'Graph not found' });
    }

    await pool.query('DELETE FROM saved_graphs WHERE id = ?', [id]);
    res.json({ message: 'Graph deleted', graph: rows[0] });
  } catch (error) {
    console.error(error);
    res.status(500).json({ error: 'Failed to delete graph' });
  }
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
    console.log(`  Admin Data Engine & Draft Visualization Ready! (MySQL)`);
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
