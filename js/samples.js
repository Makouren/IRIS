/**
 * IRIS AI - 1-Click Institutional Demo Sample Generator
 * Generates realistic institutional files (QAO Scores Excel, OAD Infograph PDF, Program Accreditation DOCX, Performance Certificate Image)
 */

class SampleGenerator {
  /**
   * Sample 1: QAO Evaluation Scores Spreadsheet (Excel XLSX)
   */
  static createSampleExcelFile() {
    const wb = XLSX.utils.book_new();

    const dataSheet1 = [
      ['College / Unit', 'QAO Evaluation Score (%)', 'Accredited Programs', 'Faculty Total', 'Passing Rate (%)'],
      ['College of Arts and Sciences (CAS)', 94.5, 12, 145, 92.4],
      ['College of Business Administration (CBA)', 89.2, 8, 98, 86.7],
      ['College of Engineering (COE)', 96.0, 10, 160, 95.8],
      ['College of Education (CED)', 91.8, 6, 75, 89.5],
      ['College of Agriculture (CA)', 93.4, 9, 110, 91.0]
    ];
    const ws1 = XLSX.utils.aoa_to_sheet(dataSheet1);
    XLSX.utils.book_append_sheet(wb, ws1, 'QAO_Scores_2024');

    const dataSheet2 = [
      ['Academic Year', 'Institutional Performance Index', 'Target Score'],
      ['2020-2021', 88.5, 85.0],
      ['2021-2022', 91.2, 90.0],
      ['2022-2023', 93.0, 92.0],
      ['2023-2024', 95.4, 94.0]
    ];
    const ws2 = XLSX.utils.aoa_to_sheet(dataSheet2);
    XLSX.utils.book_append_sheet(wb, ws2, 'Annual_Trends');

    const wbout = XLSX.write(wb, { bookType: 'xlsx', type: 'array' });
    const blob = new Blob([wbout], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    return new File([blob], 'qao_evaluation_scores_2024.xlsx', { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
  }

  /**
   * Sample 2: OAD-CLSU Infograph Stats (PDF)
   */
  static createSamplePdfFile() {
    const textContent = `
CENTRAL LUZON STATE UNIVERSITY
OFFICE OF ACADEMIC DEVELOPMENT (OAD)
INSTITUTIONAL PERFORMANCE INFOGRAPH (ANNUAL SUMMARY)

OVERVIEW OF METRICS:
Total Accredited Programs: 45
Level IV Accredited Programs: 18
Level III Accredited Programs: 21
Faculty Count: 588
Student Population: 14250
Institutional Passing Rate: 93.8%
Research Citations: 3420

ACADEMIC UNIT PERFORMANCE SUMMARY:
College of Agriculture: 94.2% Rating
College of Engineering: 96.5% Rating
College of Education: 92.0% Rating
College of Arts & Sciences: 95.1% Rating
College of Veterinary Science: 97.4% Rating
`;

    const blob = new Blob([textContent], { type: 'application/pdf' });
    return new File([blob], 'OAD_CLSU_Infograph_Summary.pdf', { type: 'application/pdf' });
  }

  /**
   * Sample 3: AACCUP Program Accreditation Link (DOCX)
   */
  static createSampleDocxFile() {
    const text = `
ACCREDITING AGENCY OF CHARTERED COLLEGES AND UNIVERSITIES IN THE PHILIPPINES (AACCUP)
PROGRAM ACCREDITATION STATUS REPORT

Institutional Name: Central Luzon State University
Document Classification: Institutional Accreditation Report
Evaluation Period: 2023-2024

PROGRAM EVALUATION SCORES:
BS Agricultural Engineering: 4.65 (Level IV Accredited)
BS Biology: 4.52 (Level IV Accredited)
BS Civil Engineering: 4.40 (Level III Accredited)
Bachelor of Elementary Education: 4.35 (Level III Accredited)
BS Business Administration: 4.28 (Level III Accredited)

SUMMARY FINDINGS:
All candidate programs have fulfilled criterion metrics with satisfactory institutional compliance.
`;

    const blob = new Blob([text], { type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' });
    return new File([blob], 'AACCUP_Program_Accreditation_Link.docx', { type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' });
  }

  /* [SLATED FOR REVIEW & REVISION]: Sample image generator disabled
  static async createSampleInvoiceImage() {
    // ...
  }
  */
}

if (typeof window !== 'undefined') {
  window.SampleGenerator = SampleGenerator;
}
