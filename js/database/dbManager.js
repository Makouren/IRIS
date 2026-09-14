/**
 * IRIS AI - Admin Database & Record Management System
 * Supports IndexedDB + LocalStorage + REST API synchronization.
 * Allows Admins to review, edit, update status, modify tabular cell data, add rows, and export database records.
 */

class DatabaseManager {
  constructor() {
    this.dbName = 'IRIS_AI_Database';
    this.dbVersion = 1;
    this.db = null;
    this.initPromise = this.initIndexedDB();
  }

  /**
   * Initialize IndexedDB
   */
  initIndexedDB() {
    return new Promise((resolve) => {
      if (typeof window === 'undefined' || !window.indexedDB) {
        console.warn('IndexedDB not supported, falling back to LocalStorage & Server API');
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
      scannedAt: new Date().toISOString(),
      status: record.status || 'Pending Review',
      riskScore: record.piiResult ? record.piiResult.riskScore : (record.riskScore || 0),
      riskLevel: record.piiResult ? record.piiResult.riskLevel : (record.riskLevel || 'SAFE'),
      docType: record.aiAnalysis ? record.aiAnalysis.docType : (record.docType || 'General Document'),
      rawText: record.rawText || '',
      extractedData: record.sheetsData || record.formattedHtml || record.ocrData || {},
      findings: record.piiResult ? record.piiResult.findings : (record.findings || []),
      graphDrafts: record.graphDrafts || [],
      adminNotes: record.adminNotes || '',
      metadata: record.metadata || {}
    };

    // Save to IndexedDB
    if (this.db) {
      const tx = this.db.transaction('records', 'readwrite');
      tx.objectStore('records').put(formattedRecord);
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

    if (this.db) {
      return new Promise((resolve) => {
        const tx = this.db.transaction('records', 'readonly');
        const store = tx.objectStore('records');
        const req = store.getAll();
        req.onsuccess = () => resolve(req.result || []);
        req.onerror = () => resolve(this.getLocalStorageRecords());
      });
    }

    // Attempt Server API
    try {
      const resp = await fetch('/api/records');
      if (resp.ok) {
        const data = await resp.json();
        if (Array.isArray(data) && data.length > 0) return data;
      }
    } catch (e) {}

    return this.getLocalStorageRecords();
  }

  /**
   * Update record (Admin edit, status change, notes update)
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
      const tx = this.db.transaction('records', 'readwrite');
      tx.objectStore('records').put(merged);
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
      const tx = this.db.transaction('records', 'readwrite');
      tx.objectStore('records').delete(id);
    }

    const local = this.getLocalStorageRecords().filter(r => r.id !== id);
    localStorage.setItem('iris_db_records', JSON.stringify(local));

    try {
      await fetch(`/api/records/${id}`, { method: 'DELETE' });
    } catch (e) {}

    return true;
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

  /**
   * Export Database to JSON, CSV, or SQL
   */
  async exportDatabase(format = 'json') {
    const records = await this.getAllRecords();

    if (format === 'json') {
      const blob = new Blob([JSON.stringify(records, null, 2)], { type: 'application/json' });
      this.triggerDownload(blob, `iris_database_${Date.now()}.json`);
    } else if (format === 'csv') {
      let csv = 'ID,File Name,Type,Doc Type,Risk Score,Risk Level,Status,Scanned At,Findings Count,Admin Notes\n';
      records.forEach(r => {
        csv += `"${r.id}","${(r.fileName || '').replace(/"/g, '""')}","${r.fileType}","${r.docType}",${r.riskScore},"${r.riskLevel}","${r.status}","${r.scannedAt}",${(r.findings || []).length},"${(r.adminNotes || '').replace(/"/g, '""')}"\n`;
      });
      const blob = new Blob([csv], { type: 'text/csv' });
      this.triggerDownload(blob, `iris_database_${Date.now()}.csv`);
    } else if (format === 'sql') {
      window.location.href = '/api/export-sql';
    }
  }

  triggerDownload(blob, filename) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }
}

if (typeof window !== 'undefined') {
  window.DatabaseManager = DatabaseManager;
}
