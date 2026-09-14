/**
 * IRIS AI - High-Precision Sensitive Data (PII & Credentials) Scanner
 * Detects confidential information, credentials, financial data, and personal identifiers.
 */

class PIIScanner {
  constructor() {
    // Severity definitions: 'critical' (weight 30), 'high' (weight 20), 'medium' (weight 10), 'low' (weight 5)
    this.patterns = [
      {
        id: 'credit_card',
        name: 'Credit / Debit Card Number',
        category: 'Financial',
        severity: 'critical',
        compliance: 'PCI-DSS',
        regex: /\b(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|3[47][0-9]{13}|3(?:0[0-5]|[68][0-9])[0-9]{11}|6(?:011|5[0-9]{2})[0-9]{12}|(?:2131|1800|35\d{3})\d{11})\b/g,
        validate: (match) => this.luhnCheck(match.replace(/[\s-]/g, ''))
      },
      {
        id: 'ssn',
        name: 'Social Security Number (SSN)',
        category: 'Personal Identification',
        severity: 'high',
        compliance: 'HIPAA / GDPR',
        regex: /\b(?!000|666|9\d{2})\d{3}[- ]?(?!00)\d{2}[- ]?(?!0000)\d{4}\b/g,
        validate: (match) => {
          const digits = match.replace(/\D/g, '');
          return digits.length === 9 && !/^(\d)\1{8}$/.test(digits);
        }
      },
      {
        id: 'aws_key',
        name: 'AWS Access Key ID',
        category: 'Credentials & Secrets',
        severity: 'critical',
        compliance: 'SOC 2 / ISO 27001',
        regex: /\b(AKIA[0-9A-Z]{16})\b/g
      },
      {
        id: 'github_token',
        name: 'GitHub Personal Access Token',
        category: 'Credentials & Secrets',
        severity: 'critical',
        compliance: 'SOC 2',
        regex: /\b(gh[pousr]_[a-zA-Z0-9]{36,255})\b/g
      },
      {
        id: 'google_api_key',
        name: 'Google Cloud / Gemini API Key',
        category: 'Credentials & Secrets',
        severity: 'critical',
        compliance: 'SOC 2',
        regex: /\bAIza[0-9A-Za-z-_]{35}\b/g
      },
      {
        id: 'stripe_secret',
        name: 'Stripe API Secret / Restricted Key',
        category: 'Financial Credentials',
        severity: 'critical',
        compliance: 'PCI-DSS',
        regex: /\b(?:sk|rk)_(?:live|test)_[0-9a-zA-Z]{24,99}\b/g
      },
      {
        id: 'jwt_token',
        name: 'JSON Web Token (JWT)',
        category: 'Authentication',
        severity: 'high',
        compliance: 'SOC 2',
        regex: /\beyJ[a-zA-Z0-9_-]{10,}\.eyJ[a-zA-Z0-9_-]{10,}\.[a-zA-Z0-9_\-.+/=]{10,}\b/g
      },
      {
        id: 'private_key',
        name: 'Private Cryptographic Key',
        category: 'Cryptographic Secrets',
        severity: 'critical',
        compliance: 'FIPS / SOC 2',
        regex: /-----BEGIN (?:RSA|EC|DSA|OPENSSH|PGP)?\s*PRIVATE KEY-----[\s\S]*?-----END (?:RSA|EC|DSA|OPENSSH|PGP)?\s*PRIVATE KEY-----/g
      },
      {
        id: 'password_leak',
        name: 'Plaintext Password Exposure',
        category: 'Credentials & Secrets',
        severity: 'high',
        compliance: 'SOC 2',
        regex: /(?:password|passwd|pwd|secret)\s*[:=]\s*['"]?([^\s,;'"]{4,64})['"]?/gi,
        extractValue: (match) => {
          const parts = match.split(/[:=]/);
          return parts.length > 1 ? parts[1].replace(/['"]/g, '').trim() : match;
        }
      },
      {
        id: 'database_uri',
        name: 'Database Connection String with Credentials',
        category: 'Infrastructure',
        severity: 'critical',
        compliance: 'SOC 2 / ISO 27001',
        regex: /\b(?:mongodb(?:\+srv)?|postgres(?:ql)?|mysql|redis):\/\/[a-zA-Z0-9_\-\.]+:[^@\s]+@[a-zA-Z0-9_\-\.]+(?::\d+)?\/[a-zA-Z0-9_\-\.]*/g
      },
      {
        id: 'email',
        name: 'Email Address',
        category: 'Personal Identification',
        severity: 'medium',
        compliance: 'GDPR',
        regex: /\b[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}\b/g
      },
      {
        id: 'phone_number',
        name: 'Phone Number',
        category: 'Personal Identification',
        severity: 'medium',
        compliance: 'GDPR / CCPA',
        regex: /(?:\+?\d{1,3}[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}\b/g,
        validate: (match) => {
          const digits = match.replace(/\D/g, '');
          return digits.length >= 10 && digits.length <= 15;
        }
      },
      {
        id: 'ipv4',
        name: 'Internal / Public IP Address',
        category: 'Network Infrastructure',
        severity: 'low',
        compliance: 'Security Hygiene',
        regex: /\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\b/g,
        validate: (match) => !match.startsWith('0.') && !match.startsWith('127.0.0.')
      },
      {
        id: 'iban',
        name: 'International Bank Account Number (IBAN)',
        category: 'Financial',
        severity: 'high',
        compliance: 'PCI-DSS / GDPR',
        regex: /\b[A-Z]{2}\d{2}[A-Z0-9]{4}\d{7}([A-Z0-9]?){0,16}\b/g
      }
    ];
  }

  /**
   * Luhn algorithm for valid credit card checksum
   */
  luhnCheck(val) {
    let sum = 0;
    let shouldDouble = false;
    for (let i = val.length - 1; i >= 0; i--) {
      let digit = parseInt(val.charAt(i), 10);
      if (isNaN(digit)) return false;
      if (shouldDouble) {
        digit *= 2;
        if (digit > 9) digit -= 9;
      }
      sum += digit;
      shouldDouble = !shouldDouble;
    }
    return sum % 10 === 0;
  }

  /**
   * Mask a sensitive string, e.g. 4532-XXXX-XXXX-8921 or j***@example.com
   */
  mask(value, type) {
    if (!value) return '';
    if (type === 'credit_card') {
      const clean = value.replace(/\s+/g, '');
      if (clean.length > 8) {
        return clean.substring(0, 4) + '-••••-••••-' + clean.substring(clean.length - 4);
      }
      return '••••-••••-••••-' + clean.slice(-4);
    }
    if (type === 'email') {
      const parts = value.split('@');
      if (parts.length === 2) {
        const name = parts[0];
        const maskedName = name.length > 2 ? name[0] + '•••' + name[name.length - 1] : name[0] + '•••';
        return `${maskedName}@${parts[1]}`;
      }
    }
    if (type === 'ssn') {
      const digits = value.replace(/\D/g, '');
      return `•••-••-${digits.slice(-4)}`;
    }
    if (value.length <= 6) return '••••••';
    return value.substring(0, 3) + '••••••••' + value.substring(value.length - 3);
  }

  /**
   * Scan text content and return itemized findings, risk score, and summary
   */
  scanText(text, sourceIdentifier = 'Document') {
    if (!text || typeof text !== 'string') {
      return {
        findings: [],
        riskScore: 0,
        riskLevel: 'SAFE',
        totalFindings: 0,
        complianceImpact: [],
        redactedText: ''
      };
    }

    const findings = [];
    const seenValues = new Set();
    const complianceImpactSet = new Set();

    for (const rule of this.patterns) {
      const regex = new RegExp(rule.regex.source, rule.regex.flags);
      let match;

      while ((match = regex.exec(text)) !== null) {
        const fullMatch = match[0];
        const extracted = rule.extractValue ? rule.extractValue(fullMatch) : fullMatch;

        if (rule.validate && !rule.validate(extracted)) {
          continue;
        }

        const uniqueKey = `${rule.id}:${extracted}`;
        if (seenValues.has(uniqueKey)) continue;
        seenValues.add(uniqueKey);

        complianceImpactSet.add(rule.compliance);

        // Find surrounding snippet for context
        const start = Math.max(0, match.index - 35);
        const end = Math.min(text.length, match.index + fullMatch.length + 35);
        let context = text.substring(start, end).replace(/\s+/g, ' ');
        if (start > 0) context = '...' + context;
        if (end < text.length) context = context + '...';

        findings.push({
          id: `find_${findings.length + 1}`,
          ruleId: rule.id,
          name: rule.name,
          category: rule.category,
          severity: rule.severity,
          compliance: rule.compliance,
          raw: extracted,
          masked: this.mask(extracted, rule.id),
          index: match.index,
          context: context,
          source: sourceIdentifier
        });
      }
    }

    // Calculate Risk Score (0 - 100)
    let score = 0;
    const weights = { critical: 25, high: 15, medium: 8, low: 3 };
    for (const f of findings) {
      score += weights[f.severity] || 5;
    }
    score = Math.min(100, Math.round(score));

    let riskLevel = 'SAFE';
    if (score >= 70) riskLevel = 'CRITICAL';
    else if (score >= 40) riskLevel = 'HIGH';
    else if (score >= 15) riskLevel = 'MEDIUM';
    else if (score > 0) riskLevel = 'LOW';

    // Generate Redacted Text
    let redactedText = text;
    // Sort findings descending by index so replacements don't shift offsets
    const sortedForRedaction = [...findings].sort((a, b) => b.index - a.index);
    for (const item of sortedForRedaction) {
      redactedText = redactedText.split(item.raw).join(`[REDACTED: ${item.name.toUpperCase()}]`);
    }

    return {
      findings,
      riskScore: score,
      riskLevel,
      totalFindings: findings.length,
      complianceImpact: Array.from(complianceImpactSet),
      redactedText,
      stats: {
        critical: findings.filter(f => f.severity === 'critical').length,
        high: findings.filter(f => f.severity === 'high').length,
        medium: findings.filter(f => f.severity === 'medium').length,
        low: findings.filter(f => f.severity === 'low').length
      }
    };
  }
}

// Attach globally for client browser runtime
if (typeof window !== 'undefined') {
  window.PIIScanner = PIIScanner;
}

if (typeof module !== 'undefined' && module.exports) {
  module.exports = PIIScanner;
}
