/**
 * IRIS AI - Pre-loaded Interactive Demo Sample Generator
 * Dynamically creates sample files in-memory for 1-click testing of Images, Excel, DOCX, and PDF formats.
 */

class SampleGenerator {
  /**
   * Create dynamic sample invoice image with clear text for OCR scanning
   */
  static async createSampleInvoiceImage() {
    const canvas = document.createElement('canvas');
    canvas.width = 1000;
    canvas.height = 1200;
    const ctx = canvas.getContext('2d');

    // Gradient dark background with white document page
    ctx.fillStyle = '#0F172A';
    ctx.fillRect(0, 0, 1000, 1200);

    // White paper invoice container
    ctx.fillStyle = '#FFFFFF';
    ctx.roundRect(50, 50, 900, 1100, 16);
    ctx.fill();

    // Invoice Header
    ctx.fillStyle = '#1E293B';
    ctx.font = 'bold 36px sans-serif';
    ctx.fillText('ACME SOLUTIONS INC.', 100, 130);

    ctx.fillStyle = '#64748B';
    ctx.font = '18px sans-serif';
    ctx.fillText('INVOICE #INV-2026-8941', 100, 165);
    ctx.fillText('Date: September 14, 2026', 100, 195);
    ctx.fillText('Payment Terms: Net 30', 100, 225);

    // Bill To
    ctx.fillStyle = '#0F172A';
    ctx.font = 'bold 20px sans-serif';
    ctx.fillText('Billed To:', 550, 130);
    ctx.font = '18px sans-serif';
    ctx.fillStyle = '#334155';
    ctx.fillText('Global CyberCorp Ltd.', 550, 165);
    ctx.fillText('Attn: Finance Department', 550, 195);
    ctx.fillText('Email: billing@cybercorp-global.com', 550, 225);
    ctx.fillText('Phone: +1 (555) 382-9102', 550, 255);

    // Table Header
    ctx.fillStyle = '#F1F5F9';
    ctx.fillRect(100, 310, 800, 45);

    ctx.fillStyle = '#1E293B';
    ctx.font = 'bold 18px sans-serif';
    ctx.fillText('Item Description', 120, 340);
    ctx.fillText('Hours / Qty', 500, 340);
    ctx.fillText('Rate', 660, 340);
    ctx.fillText('Total', 800, 340);

    // Items
    const items = [
      { desc: 'Enterprise AI Security Scan Audit', qty: '40 hrs', rate: '$150.00', total: '$6,000.00' },
      { desc: 'Cloud Vulnerability Assessment', qty: '25 hrs', rate: '$175.00', total: '$4,375.00' },
      { desc: 'PII Data Leak Mitigation & Patching', qty: '15 hrs', rate: '$160.00', total: '$2,400.00' },
      { desc: 'Database Encryption & Compliance Setup', qty: '10 hrs', rate: '$200.00', total: '$2,000.00' }
    ];

    let y = 390;
    ctx.font = '17px sans-serif';
    items.forEach(item => {
      ctx.fillStyle = '#334155';
      ctx.fillText(item.desc, 120, y);
      ctx.fillText(item.qty, 500, y);
      ctx.fillText(item.rate, 660, y);
      ctx.fillText(item.total, 800, y);

      ctx.strokeStyle = '#E2E8F0';
      ctx.lineWidth = 1;
      ctx.beginPath();
      ctx.moveTo(100, y + 15);
      ctx.lineTo(900, y + 15);
      ctx.stroke();

      y += 50;
    });

    // Totals Box
    ctx.fillStyle = '#F8FAFC';
    ctx.fillRect(520, y + 20, 380, 160);

    ctx.fillStyle = '#475569';
    ctx.font = '18px sans-serif';
    ctx.fillText('Subtotal:', 540, y + 60);
    ctx.fillText('$14,775.00', 780, y + 60);

    ctx.fillText('Tax (8%):', 540, y + 95);
    ctx.fillText('$1,182.00', 780, y + 95);

    ctx.fillStyle = '#0F172A';
    ctx.font = 'bold 22px sans-serif';
    ctx.fillText('TOTAL DUE:', 540, y + 140);
    ctx.fillText('$15,957.00', 760, y + 140);

    // Footer notice & test PII
    ctx.fillStyle = '#94A3B8';
    ctx.font = '14px sans-serif';
    ctx.fillText('Thank you for your business. Remit payment to Chase Bank Acct # 9842-1049-2918', 100, 1100);

    return new Promise(resolve => {
      canvas.toBlob(blob => {
        const file = new File([blob], 'Sample_Invoice_ACME_2026.png', { type: 'image/png' });
        resolve(file);
      }, 'image/png');
    });
  }

  /**
   * Create dynamic sample Excel file (.csv / SheetJS ready)
   */
  static createSampleExcelFile() {
    const csvContent = `Employee Name,Department,Monthly Salary,Credit Card Leak,Email,Performance Rating,Projects Completed
Alex Vance,Cybersecurity,9800,4532-8921-1049-5829,alex.vance@cybercorp.com,94,12
Sarah Connor,AI Engineering,11200,5412-7589-3910-8842,sarah.connor@cybercorp.com,98,15
Marcus Wright,DevOps,8500,3782-8224-9104-1192,marcus.wright@cybercorp.com,88,9
Elena Rostova,Data Science,10500,4024-0071-8892-3341,elena.rostova@cybercorp.com,95,14
David Miller,Finance,7900,4532-1192-3849-0012,david.miller@cybercorp.com,82,8
Rachel Green,Product Design,8800,5521-9921-3847-1920,rachel.green@cybercorp.com,91,11
Total Summary,6 Departments,56700,6 Leaks Detected,6 Accounts,Average 91.3,79 Total`;

    const blob = new Blob([csvContent], { type: 'text/csv' });
    return new File([blob], 'Q3_Financial_Payroll_Audit.csv', { type: 'text/csv' });
  }

  /**
   * Create dynamic sample DOCX NDA agreement file
   */
  static createSampleDocxFile() {
    const textContent = `CONFIDENTIALITY AND NON-DISCLOSURE AGREEMENT (NDA)

This Non-Disclosure Agreement ("Agreement") is entered into as of September 14, 2026, by and between IRIS AI Technologies Corp. ("Disclosing Party") and Quantum Cyber Dynamics ("Receiving Party").

1. PURPOSE OF DISCLOSURE
The Disclosing Party agrees to share proprietary source code, credentials, and artificial intelligence model architecture for the purpose of joint technical evaluation.

2. CONFIDENTIAL INFORMATION & RESTRICTIONS
Confidential Information includes, without limitation, technical data, trade secrets, API tokens, security audit reports, and customer records.
Exposed Internal AWS Key: AKIAIOSFODNN7EXAMPLE
Exposed Stripe Secret: sk_live_51M0x92K8102938491823901
Authorized Primary Contact Email: security-officer@iris-tech.ai
Emergency Support Line: +1 (800) 555-0199

3. OBLIGATIONS OF RECEIVING PARTY
The Receiving Party shall maintain strict confidentiality and protect all received documents with no less than a reasonable degree of care. Unintentional disclosure of Social Security Numbers (e.g. SSN: 666-42-9981) or passwords (e.g. root_pass = "Admin2026!Secret") constitutes a material breach under Section 8.4.

4. GOVERNING LAW & JURISDICTION
This Agreement shall be governed by and construed in accordance with the laws of the State of California.

IN WITNESS WHEREOF, the parties hereto have executed this Agreement as of the date first written above.

Disclosing Party: IRIS AI Corp.
Receiving Party: Quantum Cyber Dynamics`;

    const blob = new Blob([textContent], { type: 'text/plain' });
    return new File([blob], 'Sample_Confidential_NDA_Contract.docx', { type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' });
  }

  /**
   * Create dynamic sample PDF report
   */
  static createSamplePdfFile() {
    const textContent = `%PDF-1.7
%IRIS AI PDF Test Document
PATIENT & MEDICAL DATA AUDIT REPORT
Document Reference: AUD-2026-MED-99
Date: 2026-09-14

EXECUTIVE SUMMARY
This medical facility risk audit evaluates privacy compliance under HIPAA regulations.
Facility Name: St. Jude General Hospital
Patient Name: Jonathan Harker
Diagnosis Code: ICD-10-CM (Type 2 Diabetes Mellitus)
Patient SSN: 489-00-1284
Attending Physician: Dr. Elizabeth Vance (License # MD-88412)
Contact Phone: +1 (555) 782-9912

COMPLIANCE RISK EVALUATION
1. Patient Records Encryption: Standard AES-256 applied.
2. Unencrypted Data Leaks: Detected 1 unmasked SSN in plain text patient chart.
3. Recommended Mitigation: Apply immediate automated PII redaction and audit database logging.

SIGNATURE & APPROVAL
Auditor Signature: Chief Information Security Officer (CISO)`;

    const blob = new Blob([textContent], { type: 'application/pdf' });
    return new File([blob], 'Medical_Compliance_Audit_Report.pdf', { type: 'application/pdf' });
  }
}

if (typeof window !== 'undefined') {
  window.SampleGenerator = SampleGenerator;
}
