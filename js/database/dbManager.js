/**
 * IRIS AI - Admin Database & Record Management System
 * Supports IndexedDB + LocalStorage + REST API synchronization.
 * Allows Admins to review, edit extracted cells, add rows, update draft approval status, and manage records.
 */

class DatabaseManager {
  constructor() {
    this.dbName = 'IRIS_AI_Database';
    this.dbVersion = 2;
    this.db = null;
    this.initPromise = this.initIndexedDB();
  }

  /**
   * Initialize IndexedDB
   */
  initIndexedDB() {
    return new Promise((resolve) => {
      if (typeof window === 'undefined' || !window.indexedDB) {
        return resolve(false);
      }

      const request = indexedDB.open(this.dbName, this.dbVersion);

      request.onupgradeneeded = (e) => {
        const db = e.target.result;
        if (!db.objectStoreNames.contains('records')) {
          const store = db.createObjectStore('records', { keyPath: 'id' });
          store.createIndex('scannedAt', 'scannedAt', { unique: false });
          store.createIndex('status', 'status', { unique: false });
          store.createIndex('fileType', 'fileType', { unique: false });
        }
      };

      request.onsuccess = (e) => {
        this.db = e.target.result;
        resolve(true);
      };

      request.onerror = (err) => {
        console.warn('IndexedDB initialization error:', err);
        resolve(false);
      };
    });
  }

  /**
   * Save a newly scanned document into Database
   */
  async saveRecord(record) {
    await this.initPromise;

    const formattedRecord = {
      id: record.id || `rec_${Date.now()}_${Math.random().toString(36).substr(2, 5)}`,
      fileName: record.name || record.fileName || 'Untitled',
      fileType: record.type || record.fileType || 'unknown',
      fileSize: record.size || record.fileSize || 0,
      scannedAt: record.scannedAt || new Date().toISOString(),
      status: record.status || 'Pending Review',
      docType: record.docType || 'General Institutional Data',
      rawText: record.rawText || '',
      extractedData: record.sheetsData || record.formattedHtml || record.ocrData || {},
      graphDrafts: record.graphDrafts || [],
      adminNotes: record.adminNotes || '',
      metadata: record.metadata || {}
    };

    // Save to IndexedDB
    if (this.db) {
      try {
        const tx = this.db.transaction('records', 'readwrite');
        tx.objectStore('records').put(formattedRecord);
      } catch (e) {}
    }

    // Sync to LocalStorage backup
    this.saveToLocalStorage(formattedRecord);

    // Sync to Server REST API if online
    try {
      await fetch('/api/records', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formattedRecord)
      });
    } catch (apiErr) {
      // Ignore offline server errors
    }

    return formattedRecord;
  }

  /**
   * Get all database records
   */
  async getAllRecords() {
    await this.initPromise;

    // Always prefer MySQL server — it is the source of truth
    try {
      const resp = await fetch('/api/records');
      if (resp.ok) {
        const data = await resp.json();
        // Return MySQL data even if empty — MySQL is canonical
        if (Array.isArray(data)) return data;
      }
    } catch (e) {
      console.warn('Server API unavailable, falling back to local storage:', e);
    }

    // Offline Fallback to IndexedDB
    if (this.db) {
      return new Promise((resolve) => {
        const tx = this.db.transaction('records', 'readonly');
        const store = tx.objectStore('records');
        const req = store.getAll();
        req.onsuccess = () => resolve(req.result || []);
        req.onerror = () => resolve(this.getLocalStorageRecords());
      });
    }

    return this.getLocalStorageRecords();
  }

  /**
   * Update record (Admin edit, status change, cell modifications, notes update)
   */
  async updateRecord(id, updatedFields) {
    await this.initPromise;
    const records = await this.getAllRecords();
    const record = records.find(r => r.id === id);

    if (!record) throw new Error(`Record ${id} not found.`);

    const merged = {
      ...record,
      ...updatedFields,
      updatedAt: new Date().toISOString()
    };

    if (this.db) {
      try {
        const tx = this.db.transaction('records', 'readwrite');
        tx.objectStore('records').put(merged);
      } catch (e) {}
    }

    this.saveToLocalStorage(merged);

    // Sync to server API
    try {
      await fetch(`/api/records/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(merged)
      });
    } catch (e) {}

    return merged;
  }

  /**
   * Delete record from database
   */
  async deleteRecord(id) {
    await this.initPromise;

    if (this.db) {
      try {
        const tx = this.db.transaction('records', 'readwrite');
        tx.objectStore('records').delete(id);
      } catch (e) {}
    }

    const local = this.getLocalStorageRecords().filter(r => r.id !== id);
    localStorage.setItem('iris_db_records', JSON.stringify(local));

    try {
      await fetch(`/api/records/${id}`, { method: 'DELETE' });
    } catch (e) {}

    return true;
  }

  async saveGraph(graphData) {
    const payload = {
      id: graphData.id || `graph_${Date.now()}_${Math.random().toString(36).substr(2, 7)}`,
      record_id: graphData.record_id || graphData.recordId,
      title: graphData.title || 'Saved Chart',
      chart_type: graphData.chart_type || graphData.chartType || 'bar',
      labels: graphData.labels || [],
      values_data: graphData.values_data || graphData.valuesData || graphData.data || []
    };

    const response = await fetch('/api/graphs', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    if (!response.ok) {
      const error = await response.json().catch(() => ({}));
      throw new Error(error.error || 'Failed to save graph');
    }

    return response.json();
  }

  async exportGraph(graphData, recordId) {
    if (typeof GraphExport !== 'undefined' && GraphExport.normalizeGraphExportItem) {
      const payload = GraphExport.normalizeGraphExportItem(graphData, recordId);
      return this.saveGraph(payload);
    }

    return this.saveGraph({
      record_id: recordId || graphData.record_id || graphData.recordId,
      title: graphData.title || 'Saved Chart',
      chart_type: graphData.chart_type || graphData.chartType || graphData.primaryType || 'bar',
      labels: graphData.labels || [],
      values_data: graphData.values_data || graphData.valuesData || graphData.data || []
    });
  }

  async exportGraphs(snapshotIds, mode) {
    const response = await fetch('/api/graphs/export', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ snapshot_ids: snapshotIds, mode })
    });

    if (!response.ok) {
      const error = await response.json().catch(() => ({}));
      throw new Error(error.error || 'Failed to export saved graphs');
    }

    if (mode === 'script') {
      const disposition = response.headers.get('Content-Disposition') || '';
      const fileName = disposition.match(/filename="?([^";]+)"?/i)?.[1] || 'iris_saved_graphs.txt';
      return {
        mode,
        count: Number(response.headers.get('X-Export-Count') || snapshotIds.length),
        fileName,
        blob: await response.blob()
      };
    }

    return response.json();
  }

  printGraphSheet(graphData, context = {}) {
    if (typeof GraphExport !== 'undefined' && GraphExport.buildPrintableGraphSheet) {
      const html = GraphExport.buildPrintableGraphSheet(graphData, context);
      const popup = window.open('', '_blank', 'width=1200,height=900');
      if (!popup) {
        throw new Error('Popup blocked. Please allow popups to print the graph sheet.');
      }
      popup.document.write(html);
      popup.document.close();
      popup.focus();
      return popup;
    }

    return null;
  }

  printGraphSheets(graphs, context = {}) {
    if (typeof GraphExport !== 'undefined' && GraphExport.buildPrintableGraphSheets) {
      const html = GraphExport.buildPrintableGraphSheets(graphs, context);
      const popup = window.open('', '_blank', 'width=1200,height=900');
      if (!popup) {
        throw new Error('Popup blocked. Please allow popups to print the graphs.');
      }
      popup.document.write(html);
      popup.document.close();
      popup.focus();
      return popup;
    }

    return null;
  }

  async getAllSavedGraphs() {
    try {
      const response = await fetch('/api/graphs');
      if (!response.ok) return [];
      const rows = await response.json();
      return rows || [];
    } catch (e) {
      return [];
    }
  }

  async getGraphsByRecord(recordId) {
    if (!recordId) return [];

    try {
      const response = await fetch(`/api/graphs/${recordId}`);
      if (!response.ok) return [];
      const rows = await response.json();
      return (rows || []).map(row => ({
        id: row.id,
        title: row.title || 'Saved Chart',
        source: 'Saved Chart',
        primaryType: row.chart_type || 'bar',
        recommendation: 'Saved chart from the dashboard studio.',
        isDraft: true,
        chartData: {
          labels: Array.isArray(row.labels) ? row.labels : [],
          datasets: [{
            label: row.title || 'Series',
            data: Array.isArray(row.values_data) ? row.values_data : [],
            backgroundColor: 'rgba(20, 108, 54, 0.45)',
            borderColor: '#146C36',
            borderWidth: 2
          }]
        }
      }));
    } catch (e) {
      return [];
    }
  }

  async deleteGraph(graphId) {
    if (!graphId) return false;

    try {
      const response = await fetch(`/api/graphs/${graphId}`, { method: 'DELETE' });
      return response.ok;
    } catch (e) {
      return false;
    }
  }

  /**
   * Admin Helper: Add a new custom data row to a sheet table inside a record
   */
  async addDataRow(recordId, sheetName, newRowArray) {
    const records = await this.getAllRecords();
    const record = records.find(r => r.id === recordId);
    if (!record) return;

    if (record.extractedData && record.extractedData[sheetName]) {
      record.extractedData[sheetName].rows.unshift(newRowArray);
      record.extractedData[sheetName].rowCount = record.extractedData[sheetName].rows.length;
      await this.updateRecord(recordId, { extractedData: record.extractedData });
    }
  }

  /**
   * Admin Helper: Edit a cell value in a sheet table
   */
  async updateDataCell(recordId, sheetName, rowIndex, colIndex, newValue) {
    const records = await this.getAllRecords();
    const record = records.find(r => r.id === recordId);
    if (!record) return;

    if (record.extractedData && record.extractedData[sheetName] && record.extractedData[sheetName].rows[rowIndex]) {
      record.extractedData[sheetName].rows[rowIndex][colIndex] = newValue;
      await this.updateRecord(recordId, { extractedData: record.extractedData });
    }
  }

  // LocalStorage Fallbacks
  saveToLocalStorage(record) {
    const current = this.getLocalStorageRecords();
    const idx = current.findIndex(r => r.id === record.id);
    if (idx >= 0) current[idx] = record;
    else current.unshift(record);
    localStorage.setItem('iris_db_records', JSON.stringify(current));
  }

  getLocalStorageRecords() {
    try {
      const raw = localStorage.getItem('iris_db_records');
      return raw ? JSON.parse(raw) : [];
    } catch (e) {
      return [];
    }
  }
}

if (typeof window !== 'undefined') {
  window.DatabaseManager = DatabaseManager;
}
