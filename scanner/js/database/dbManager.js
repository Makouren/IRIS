// Compute the base URL relative to the project root so it works from any subdirectory (admin/, scanner/, etc.)
const IRIS_BASE = (() => {
  const path = window.location.pathname;
  // Walk up from /admin/ or /scanner/ to find the project root
  const match = path.match(/^(.*?\/iris)\//i) || path.match(/^(.*?)\/(admin|scanner|api|user|auth)\//i);
  return match ? match[1] : path.replace(/\/[^/]*$/, '');
})();
const IRIS_API = `${IRIS_BASE}/api/iris.php`;
const DEFAULT_API_ENDPOINTS = {
  records: `${IRIS_API}?resource=records`,
  recordById: id => `${IRIS_API}?resource=records&id=${encodeURIComponent(id)}`,
  recordsBulkDelete: `${IRIS_API}?resource=records&action=bulk-delete`,
  recordsBulkApprove: `${IRIS_API}?resource=records&action=bulk-approve`,
  fieldColors: `${IRIS_API}?resource=field_colors`,
  graphs: `${IRIS_API}?resource=graphs`,
  graphById: id => `${IRIS_API}?resource=graphs&id=${encodeURIComponent(id)}`,
  graphsByRecord: recordId => `${IRIS_API}?resource=graphs&record_id=${encodeURIComponent(recordId)}`,
  graphsBulkDelete: `${IRIS_API}?resource=graphs&action=bulk-delete`,
  graphsExport: `${IRIS_API}?resource=graphs&action=export`
};

/**
 * IRIS AI - Admin Database & Record Management System
 * Supports IndexedDB + LocalStorage + REST API synchronization.
 * Allows Admins to review, edit extracted cells, add rows, update draft approval status, and manage records.
 */

class DatabaseManager {
  constructor(config = {}) {
    this.dbName = 'IRIS_AI_Database';
    this.dbVersion = 2;
    this.db = null;
    this.config = {
      endpoints: {
        ...DEFAULT_API_ENDPOINTS,
        ...(config.endpoints || {})
      }
    };
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
      extractedData: record.extractedData || record.sheetsData || record.formattedHtml || record.ocrData || {},
      graphDrafts: record.graphDrafts || [],
      adminNotes: record.adminNotes || '',
      metadata: record.metadata || {}
    };
    const response = await fetch(this.config.endpoints.records, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(formattedRecord)
    });
    if (!response.ok) {
      const error = await response.json().catch(() => ({}));
      throw new Error(error.error || `Unable to save record (HTTP ${response.status})`);
    }
    const saved = await response.json();
    if (this.db) { try { this.db.transaction('records', 'readwrite').objectStore('records').put(saved); } catch (e) {} }
    this.saveToLocalStorage(saved);
    return saved;
  }

  /**
   * Get all database records
   */
  async getAllRecords({ excludeImportRecords = false } = {}) {
    await this.initPromise;
    const filterRecords = records => excludeImportRecords
      ? records.filter(record => !['ranking_history', 'summary_cards'].includes(String(record.import_destination || record.metadata?.upload_purpose || '')))
      : records;

    // Always prefer MySQL server — it is the source of truth
    try {
      const endpoint = new URL(this.config.endpoints.records, window.location.href);
      if (excludeImportRecords) endpoint.searchParams.set('exclude_import_records', '1');
      const resp = await fetch(endpoint);
      if (resp.ok) {
        const data = await resp.json();
        // Return MySQL data even if empty — MySQL is canonical
        if (Array.isArray(data)) return filterRecords(data);
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
        req.onsuccess = () => resolve(filterRecords(req.result || []));
        req.onerror = () => resolve(filterRecords(this.getLocalStorageRecords()));
      });
    }

    return filterRecords(this.getLocalStorageRecords());
  }

  async getFieldColors() {
    const rows = await this.getFieldColorRows();
    return Object.fromEntries(rows.map(row => [row.field_key, row.color]));
  }

  async getFieldColorRows() {
    const response = await fetch(this.config.endpoints.fieldColors, { headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error(`Unable to load field colors (HTTP ${response.status})`);
    const rows = await response.json();
    return (Array.isArray(rows) ? rows : [])
      .filter(row => typeof row.field_key === 'string' && /^#[0-9A-Fa-f]{6}$/.test(row.color || ''))
      .map(row => ({ ...row, color: row.color.toUpperCase() }));
  }

  async saveFieldColor(fieldKey, label, color) {
    if (typeof color !== 'string' || !/^#[0-9A-Fa-f]{6}$/.test(color)) throw new Error('Use a six-digit HEX color.');
    const response = await fetch(this.config.endpoints.fieldColors, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ field_key: fieldKey, label, color: color.toUpperCase() })
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.error || 'Unable to save field color.');
    return payload;
  }

  async deleteFieldColor(fieldKey) {
    const url = `${this.config.endpoints.fieldColors}&field_key=${encodeURIComponent(fieldKey)}`;
    const response = await fetch(url, { method: 'DELETE', headers: { Accept: 'application/json' } });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.error || 'Unable to reset field color.');
    return payload;
  }

  /**
   * Update record (Admin edit, status change, cell modifications, notes update)
   */
  async updateRecord(id, updatedFields) {
    await this.initPromise;
    const response = await fetch(this.config.endpoints.recordById(id), {
      method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(updatedFields)
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.error || `Unable to update record (HTTP ${response.status})`);
    const merged = payload;
    if (this.db) { try { this.db.transaction('records', 'readwrite').objectStore('records').put(merged); } catch (e) {} }
    this.saveToLocalStorage(merged);
    return merged;
  }

  /**
   * Delete record from database
   */
  async deleteRecord(id) {
    await this.initPromise;
    const response = await fetch(this.config.endpoints.recordById(id), { method: 'DELETE' });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.error || `Unable to delete record (HTTP ${response.status})`);
    if (this.db) { try { this.db.transaction('records', 'readwrite').objectStore('records').delete(id); } catch (e) {} }
    const local = this.getLocalStorageRecords().filter(r => r.id !== id);
    localStorage.setItem('iris_db_records', JSON.stringify(local));
    return payload;
  }

  async deleteRecords(ids) {
    await this.initPromise;
    const recordIds = [...new Set((ids || []).filter(Boolean))];
    if (!recordIds.length) throw new Error('No records selected.');
    const response = await fetch(this.config.endpoints.recordsBulkDelete, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ ids: recordIds })
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.error || `Bulk delete failed (HTTP ${response.status})`);
    if (this.db) {
      try { const tx=this.db.transaction('records','readwrite'); const store=tx.objectStore('records'); recordIds.forEach(id=>store.delete(id)); } catch(e) {}
    }
    const local = this.getLocalStorageRecords().filter(r => !recordIds.includes(r.id));
    localStorage.setItem('iris_db_records', JSON.stringify(local));
    return payload;
  }

  async approveRecords(ids) {
    await this.initPromise;
    const recordIds = [...new Set((ids || []).filter(Boolean))];
    if (!recordIds.length) throw new Error('No records selected.');
    const response = await fetch(this.config.endpoints.recordsBulkApprove, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ ids: recordIds })
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.error || `Bulk approval failed (HTTP ${response.status})`);
    const approved = new Set(recordIds);
    if (this.db) {
      try {
        const current = await this.getAllRecords();
        const tx = this.db.transaction('records', 'readwrite');
        const store = tx.objectStore('records');
        current.filter(r => approved.has(r.id)).forEach(r => store.put({ ...r, status: 'Approved', updatedAt: new Date().toISOString() }));
      } catch(e) {}
    }
    const local = this.getLocalStorageRecords().map(r => approved.has(r.id) ? {...r,status:'Approved',updatedAt:new Date().toISOString()} : r);
    localStorage.setItem('iris_db_records', JSON.stringify(local));
    return payload;
  }

  async unpublishRecord(id) {
    await this.initPromise;
    const response = await fetch(`${this.config.endpoints.recordById(id)}&action=unpublish`, { method: 'POST' });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.error || `Unable to unpublish record (HTTP ${response.status})`);
    const record = payload.record;
    if (record && this.db) {
      try { this.db.transaction('records', 'readwrite').objectStore('records').put(record); } catch (e) {}
    }
    if (record) {
      const local = this.getLocalStorageRecords().map(item => item.id === record.id ? record : item);
      localStorage.setItem('iris_db_records', JSON.stringify(local));
    }
    return payload;
  }

  async stageRestore(oldId, officeId) {
    await this.initPromise;
    const response = await fetch(`${this.config.endpoints.recordById(oldId)}&action=stage-restore`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ office_id: officeId })
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.error || `Unable to stage merge recovery (HTTP ${response.status})`);
    return payload;
  }

  async trashStoredFile(oldId, trashId) {
    await this.initPromise;
    const response = await fetch(`${this.config.endpoints.recordById(oldId)}&action=trash-stored-file`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ trash_id: trashId })
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.error || `Unable to move the previous file to trash (HTTP ${response.status})`);
    return payload;
  }

  async restoreMerge(oldId, trashId) {
    await this.initPromise;
    const response = await fetch(`${this.config.endpoints.recordById(oldId)}&action=restore-merge`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ trash_id: trashId })
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.error || `Unable to restore the previous file (HTTP ${response.status})`);
    return payload;
  }

  async setRecordsPublication(ids, published) {
    await this.initPromise;
    const recordIds = [...new Set((ids || []).filter(Boolean))];
    if (!recordIds.length) throw new Error('No records selected.');
    const response = await fetch(`${this.config.endpoints.records}&action=bulk-${published ? 'publish' : 'unpublish'}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ids: recordIds })
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.error || `Bulk ${published ? 'publish' : 'unpublish'} failed (HTTP ${response.status})`);
    const selected = new Set(recordIds);
    const status = published ? 'Approved' : 'Pending Review';
    if (this.db) {
      try {
        const current = await this.getAllRecords();
        const store = this.db.transaction('records', 'readwrite').objectStore('records');
        current.filter(record => selected.has(String(record.id))).forEach(record => store.put({ ...record, status }));
      } catch (e) {}
    }
    const local = this.getLocalStorageRecords().map(record => selected.has(String(record.id)) ? { ...record, status } : record);
    localStorage.setItem('iris_db_records', JSON.stringify(local));
    return payload;
  }

  async saveGraph(graphData) {
    const chartData = graphData.chart_data || graphData.chartData || graphData.option || graphData.config || {};
    const payload = {
      id: graphData.id || `graph_${Date.now()}_${Math.random().toString(36).substr(2, 7)}`,
      record_id: graphData.record_id || graphData.recordId,
      title: graphData.title || 'Saved Chart',
      chart_type: graphData.chart_type || graphData.chartType || 'bar',
      orientation: graphData.orientation || 'vertical',
      valueAxisReversed: graphData.valueAxisReversed === true,
      valueAxisMin: graphData.valueAxisMin,
      valueAxisMax: graphData.valueAxisMax,
      rankSemantic: graphData.rankSemantic === true,
      rankValueMin: graphData.rankValueMin,
      rankValueMax: graphData.rankValueMax,
      labels: graphData.labels || [],
      values_data: graphData.values_data || graphData.valuesData || graphData.data || [],
      colors: Array.isArray(graphData.colors) && graphData.colors.every(color => typeof color === 'string' && /^#[0-9A-Fa-f]{6}$/.test(color)) ? graphData.colors : null,
      chart_data: chartData && typeof chartData === 'object' ? chartData : {},
      is_published: graphData.is_published === true || graphData.is_published === 1 || graphData.is_published === '1'
    };

    const response = await fetch(this.config.endpoints.graphs, {
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

  async updateGraph(graphId, graphData) {
    if (!graphId) throw new Error('Graph id is required.');
    const chartData = graphData.chart_data || graphData.chartData || graphData.option || graphData.config || {};
    const payload = {
      record_id: graphData.record_id || graphData.recordId,
      title: graphData.title || 'Saved Chart',
      chart_type: graphData.chart_type || graphData.chartType || 'bar',
      orientation: graphData.orientation || 'vertical',
      valueAxisReversed: graphData.valueAxisReversed === true,
      valueAxisMin: graphData.valueAxisMin,
      valueAxisMax: graphData.valueAxisMax,
      rankSemantic: graphData.rankSemantic === true,
      rankValueMin: graphData.rankValueMin,
      rankValueMax: graphData.rankValueMax,
      labels: graphData.labels || [],
      values_data: graphData.values_data || graphData.valuesData || graphData.data || [],
      colors: Array.isArray(graphData.colors) && graphData.colors.every(color => typeof color === 'string' && /^#[0-9A-Fa-f]{6}$/.test(color)) ? graphData.colors : null,
      chart_data: chartData && typeof chartData === 'object' ? chartData : {}
    };
    if (Object.prototype.hasOwnProperty.call(graphData, 'is_published')) {
      payload.is_published = graphData.is_published === true || graphData.is_published === 1 || graphData.is_published === '1';
    }

    const response = await fetch(this.config.endpoints.graphById(graphId), {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(result.error || `Unable to update graph (HTTP ${response.status})`);
    return result;
  }

  async publishGraph(graphId, published = true) {
    const response = await fetch(`${this.config.endpoints.graphById(graphId)}&action=${published ? 'publish' : 'unpublish'}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ published: Boolean(published) })
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.error || `Unable to ${published ? 'publish' : 'unpublish'} graph (HTTP ${response.status})`);
    return payload;
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
    const response = await fetch(this.config.endpoints.graphsExport, {
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
      const response = await fetch(this.config.endpoints.graphs);
      if (!response.ok) return [];
      const rows = await response.json();
      return rows || [];
    } catch (e) {
      return [];
    }
  }

  async getGraphById(graphId) {
    if (!graphId) throw new Error('Graph id is required.');
    const response = await fetch(this.config.endpoints.graphById(graphId), {
      headers: { Accept: 'application/json' },
      cache: 'no-store'
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.error || `Unable to load graph (HTTP ${response.status})`);
    return payload;
  }

  async getGraphsByRecord(recordId) {
    if (!recordId) return [];

    try {
      const response = await fetch(this.config.endpoints.graphsByRecord(recordId));
      if (!response.ok) return [];
      const rows = await response.json();
      return (rows || []).map(row => ({
        ...row,
        id: row.id,
        title: row.title || 'Saved Chart',
        is_published: row.is_published === true || row.is_published === 1,
        source: 'Saved Chart',
        primaryType: row.chart_type || 'bar',
        chart_type: row.chart_type || 'bar',
        orientation: row.orientation || 'vertical',
        values_data: Array.isArray(row.values_data) ? row.values_data : [],
        labels: Array.isArray(row.labels) ? row.labels : [],
        colors: Array.isArray(row.colors) ? row.colors : null,
        chart_data: row.chart_data || {},
        rankSemantic: row.rank_semantic === true || row.rank_semantic === 1,
        recommendation: 'Saved chart from the dashboard studio.',
        isDraft: true,
        chartData: {
          ...(row.chart_data || {}),
          labels: Array.isArray(row.labels) ? row.labels : [],
          rankSemantic: row.rank_semantic === true || row.rank_semantic === 1,
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
      const response = await fetch(this.config.endpoints.graphById(graphId), { method: 'DELETE' });
      return response.ok;
    } catch (e) {
      return false;
    }
  }

  async deleteGraphs(graphIds) {
    const ids = [...new Set((graphIds || []).filter(Boolean))];
    if (!ids.length) return { results: [], successCount: 0, failureCount: 0 };
    try {
      const response = await fetch(this.config.endpoints.graphsBulkDelete, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ ids }) });
      if (!response.ok) throw new Error('Bulk graph delete request failed');
      return response.json();
    } catch (error) {
      return { results: ids.map(id => ({ id, success: false, error: error.message })), successCount: 0, failureCount: ids.length };
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
