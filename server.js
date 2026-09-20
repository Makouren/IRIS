const express = require('express');
const path = require('path');
const cors = require('cors');
const mysql = require('mysql2/promise');

const app = express();
let PORT = parseInt(process.env.PORT, 10) || 3000;

app.use(cors());
app.use(express.json({ limit: '50mb' }));
app.use(express.urlencoded({ extended: true, limit: '50mb' }));

const dbConfig = {
  host: process.env.DB_HOST || 'localhost',
  port: Number(process.env.DB_PORT || 3306),
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASSWORD || '',
  database: process.env.DB_NAME || 'iris_db',
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0
};

// Database connection pool
const pool = mysql.createPool(dbConfig);

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
        orientation VARCHAR(20) DEFAULT 'vertical',
        value_axis_reversed BOOLEAN DEFAULT FALSE,
        value_axis_min DECIMAL(20,8),
        value_axis_max DECIMAL(20,8),
        rank_semantic BOOLEAN DEFAULT FALSE,
        rank_value_min DECIMAL(20,8),
        rank_value_max DECIMAL(20,8),
        labels JSON,
        values_data JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (record_id) REFERENCES records(id) ON DELETE CASCADE
      )
    `);
    try {
      await pool.query('ALTER TABLE saved_graphs ADD COLUMN orientation VARCHAR(20) DEFAULT \'vertical\'');
    } catch (error) {
      if (!/duplicate column/i.test(error.message || '')) throw error;
    }
    try {
      await pool.query('ALTER TABLE saved_graphs ADD COLUMN value_axis_reversed BOOLEAN DEFAULT FALSE');
    } catch (error) {
      if (!/duplicate column/i.test(error.message || '')) throw error;
    }
    for (const column of ['value_axis_min', 'value_axis_max']) {
      try {
        await pool.query(`ALTER TABLE saved_graphs ADD COLUMN ${column} DECIMAL(20,8)`);
      } catch (error) {
        if (!/duplicate column/i.test(error.message || '')) throw error;
      }
    }
    try {
      await pool.query('ALTER TABLE saved_graphs ADD COLUMN rank_semantic BOOLEAN DEFAULT FALSE');
    } catch (error) {
      if (!/duplicate column/i.test(error.message || '')) throw error;
    }
    for (const column of ['rank_value_min', 'rank_value_max']) {
      try {
        await pool.query(`ALTER TABLE saved_graphs ADD COLUMN ${column} DECIMAL(20,8)`);
      } catch (error) {
        if (!/duplicate column/i.test(error.message || '')) throw error;
      }
    }
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

app.post('/api/records/bulk-delete', async (req, res) => {
  try {
    const ids = [...new Set(Array.isArray(req.body.ids) ? req.body.ids.filter(Boolean) : [])];
    if (!ids.length) return res.status(400).json({ error: 'ids must be a non-empty array' });
    const placeholders = ids.map(() => '?').join(', ');
    const [existing] = await pool.query(`SELECT id FROM records WHERE id IN (${placeholders})`, ids);
    const existingIds = existing.map(record => record.id);
    if (existingIds.length) await pool.query(`DELETE FROM records WHERE id IN (${existingIds.map(() => '?').join(', ')})`, existingIds);
    const existingSet = new Set(existingIds);
    const results = ids.map(id => ({ id, success: existingSet.has(id), error: existingSet.has(id) ? null : 'Record not found' }));
    res.json({ results, successCount: existingIds.length, failureCount: ids.length - existingIds.length });
  } catch (error) {
    console.error(error);
    res.status(500).json({ error: 'Failed to bulk delete records' });
  }
});

// Saved graphs endpoints
app.get('/api/graphs', async (req, res) => {
  try {
    const [rows] = await pool.query(`
      SELECT saved_graphs.*, records.id AS source_file_id, records.fileName AS source_file_name, records.fileType AS source_file_type
      FROM saved_graphs
      LEFT JOIN records ON records.id = saved_graphs.record_id
      ORDER BY saved_graphs.created_at DESC
    `);
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

function buildGraphExportText(graphs) {
  return graphs.map(graph => {
    const labels = typeof graph.labels === 'string' ? JSON.parse(graph.labels) : (graph.labels || []);
    const values = typeof graph.values_data === 'string' ? JSON.parse(graph.values_data) : (graph.values_data || []);
    const rows = labels.map((label, index) => `${label || `Item ${index + 1}`}: ${values[index] ?? ''}`);
    return [`Title: ${graph.title || 'Saved Chart'}`, `Chart Type: ${(graph.chart_type || 'bar').toUpperCase()}`, `Source Record ID: ${graph.record_id}`, '', 'Category: Value', ...rows].join('\n');
  }).join('\n\n');
}

app.post('/api/graphs/export', async (req, res) => {
  const snapshotIds = Array.isArray(req.body.snapshot_ids) ? req.body.snapshot_ids.filter(Boolean) : [];
  const mode = req.body.mode;

  if (!snapshotIds.length) return res.status(400).json({ error: 'snapshot_ids must contain at least one graph id' });
  if (!['database', 'script'].includes(mode)) return res.status(400).json({ error: 'mode must be database or script' });

  try {
    const placeholders = snapshotIds.map(() => '?').join(',');
    const [graphs] = await pool.query(
      `SELECT saved_graphs.*, records.id AS source_file_id, records.fileName AS source_file_name, records.fileType AS source_file_type
       FROM saved_graphs LEFT JOIN records ON records.id = saved_graphs.record_id
       WHERE saved_graphs.id IN (${placeholders}) ORDER BY saved_graphs.created_at DESC`,
      snapshotIds
    );

    if (!graphs.length) return res.status(404).json({ error: 'No saved graphs found for the supplied ids' });

    if (mode === 'script') {
      const fileName = `iris_saved_graphs_${Date.now()}.txt`;
      res.set({
        'Content-Type': 'text/plain; charset=utf-8',
        'Content-Disposition': `attachment; filename="${fileName}"`,
        'X-Export-Count': String(graphs.length)
      });
      return res.send(buildGraphExportText(graphs));
    }

    const connection = await pool.getConnection();
    try {
      await connection.beginTransaction();
      const exportedIds = [];
      const rows = graphs.map(graph => {
        const exportedId = `export_${Date.now()}_${Math.random().toString(36).slice(2, 10)}`;
        exportedIds.push(exportedId);
        return [exportedId, graph.record_id, graph.title, graph.chart_type, graph.labels, graph.values_data];
      });
      const values = rows.flat();
      const rowPlaceholders = rows.map(() => '(?, ?, ?, ?, ?, ?)').join(', ');
      await connection.query(
        `INSERT INTO saved_graphs (id, record_id, title, chart_type, labels, values_data) VALUES ${rowPlaceholders}`,
        values
      );
      await connection.commit();
      return res.json({ mode, count: graphs.length, exported_ids: exportedIds });
    } catch (error) {
      await connection.rollback();
      throw error;
    } finally {
      connection.release();
    }
  } catch (error) {
    console.error(error);
    res.status(500).json({ error: 'Failed to export saved graphs' });
  }
});

app.post('/api/graphs', async (req, res) => {
  try {
    const graph = {
      id: req.body.id || `graph_${Date.now()}_${Math.random().toString(36).substr(2, 6)}`,
      record_id: req.body.record_id || req.body.recordId,
      title: req.body.title || 'Saved Chart',
      chart_type: req.body.chart_type || req.body.chartType || 'bar',
      orientation: req.body.orientation || 'vertical',
      value_axis_reversed: req.body.valueAxisReversed === true,
      value_axis_min: Number.isFinite(Number(req.body.valueAxisMin)) ? Number(req.body.valueAxisMin) : null,
      value_axis_max: Number.isFinite(Number(req.body.valueAxisMax)) ? Number(req.body.valueAxisMax) : null,
      rank_semantic: req.body.rankSemantic === true,
      rank_value_min: Number.isFinite(Number(req.body.rankValueMin)) ? Number(req.body.rankValueMin) : null,
      rank_value_max: Number.isFinite(Number(req.body.rankValueMax)) ? Number(req.body.rankValueMax) : null,
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
      `INSERT INTO saved_graphs (id, record_id, title, chart_type, orientation, value_axis_reversed, value_axis_min, value_axis_max, rank_semantic, rank_value_min, rank_value_max, labels, values_data) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [graph.id, graph.record_id, graph.title, graph.chart_type, graph.orientation, graph.value_axis_reversed, graph.value_axis_min, graph.value_axis_max, graph.rank_semantic, graph.rank_value_min, graph.rank_value_max, graph.labels, graph.values_data]
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

app.post('/api/graphs/bulk-delete', async (req, res) => {
  try {
    const ids = [...new Set(Array.isArray(req.body.ids) ? req.body.ids.filter(Boolean) : [])];
    if (!ids.length) return res.status(400).json({ error: 'ids must be a non-empty array' });
    const placeholders = ids.map(() => '?').join(', ');
    const [existing] = await pool.query(`SELECT id FROM saved_graphs WHERE id IN (${placeholders})`, ids);
    const existingIds = existing.map(graph => graph.id);
    if (existingIds.length) await pool.query(`DELETE FROM saved_graphs WHERE id IN (${existingIds.map(() => '?').join(', ')})`, existingIds);
    const existingSet = new Set(existingIds);
    const results = ids.map(id => ({ id, success: existingSet.has(id), error: existingSet.has(id) ? null : 'Graph not found' }));
    res.json({ results, successCount: existingIds.length, failureCount: ids.length - existingIds.length });
  } catch (error) {
    console.error(error);
    res.status(500).json({ error: 'Failed to bulk delete graphs' });
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
