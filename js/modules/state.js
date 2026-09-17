export function createState() {
  return {
    queue: [],
    activeScan: null,
    chartInstances: {},
    studioActiveRecord: null,
    studioChartInstance: null,
    studioFilterPreviousQuery: '',
    studioFilterPreviousResults: null,
    studioFilterPreviousScope: '',
    docWindowActiveView: 'sheet',
    docWindowActiveSheetKey: '',
    docWindowSearchQuery: '',
    docWindowFilterQuery: '',
    docWindowFilteredRows: null,
    acrobatZoomLevel: 100,
    acrobatCurrentPage: 1,
    acrobatTotalPages: 1,
    docWindowViewerInstance: null,
    docWindowDocxPages: [],
    docWindowFallbackPages: [],
    savedGraphIds: new Set(),
    savedGraphsViewAll: false
  };
}
