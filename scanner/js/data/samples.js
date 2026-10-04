/**
 * IRIS AI - 1-Click Institutional Demo Sample Generator
 * Generates IAO International Rankings dataset (Excel XLSX)
 */

class SampleGenerator {
  /**
   * Sample 1: IAO International Rankings Dataset (Excel XLSX)
   */
  static createSampleExcelFile() {
    const wb = XLSX.utils.book_new();

    const dataSheet1 = [
      ['Ranking Organization', 'Category', 'Year', 'Institution', 'Regional Rank', 'Global Rank', 'Score'],
      ['QS World University Rankings', 'Overall', 2024, 'Central Luzon State University', 125, 801, 42.5],
      ['Times Higher Education (THE)', 'Impact Rankings', 2024, 'Central Luzon State University', 89, 601, 55.2],
      ['Webometrics', 'Excellence', 2024, 'Central Luzon State University', 45, 1205, 38.9],
      ['EduRank', 'Agriculture', 2024, 'Central Luzon State University', 12, 450, 72.1],
      ['UI GreenMetric', 'Sustainability', 2024, 'Central Luzon State University', 34, 512, 68.4]
    ];
    const ws1 = XLSX.utils.aoa_to_sheet(dataSheet1);
    XLSX.utils.book_append_sheet(wb, ws1, 'IAO_Rankings_2024');

    const dataSheet2 = [
      ['Academic Year', 'Average Global Rank', 'Total Score Index'],
      ['2020-2021', 1105, 45.2],
      ['2021-2022', 980, 50.1],
      ['2022-2023', 875, 54.3],
      ['2023-2024', 801, 58.7]
    ];
    const ws2 = XLSX.utils.aoa_to_sheet(dataSheet2);
    XLSX.utils.book_append_sheet(wb, ws2, 'Annual_Performance_Trends');

    const wbout = XLSX.write(wb, { bookType: 'xlsx', type: 'array' });
    const blob = new Blob([wbout], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    return new File([blob], 'IAO_International_Rankings_2024.xlsx', { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
  }
}

if (typeof window !== 'undefined') {
  window.SampleGenerator = SampleGenerator;
}
