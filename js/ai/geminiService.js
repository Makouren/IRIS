/**
 * IRIS AI - Intelligence Service (Google Gemini API & Local Rule-Based Fallback)
 * Provides document summarization, classification, insights, and interactive Q&A.
 */

class GeminiService {
  constructor() {
    this.apiKey = localStorage.getItem('iris_gemini_api_key') || '';
    this.selectedModel = localStorage.getItem('iris_gemini_model') || 'gemini-1.5-flash';
  }

  setApiKey(key) {
    this.apiKey = (key || '').trim();
    if (this.apiKey) {
      localStorage.setItem('iris_gemini_api_key', this.apiKey);
    } else {
      localStorage.removeItem('iris_gemini_api_key');
    }
  }

  setModel(model) {
    this.selectedModel = model;
    localStorage.setItem('iris_gemini_model', model);
  }

  hasApiKey() {
    return Boolean(this.apiKey && this.apiKey.length > 10);
  }

  /**
   * Local Rule-Based AI Summary & Analysis
   */
  generateLocalAnalysis(text, metadata, fileType) {
    const cleanText = (text || '').trim();
    const wordCount = cleanText ? cleanText.split(/\s+/).length : 0;
    const charCount = cleanText.length;
    const lines = cleanText.split('\n').filter(l => l.trim().length > 0);

    // Document classification heuristic
    let docType = 'General Document';
    const lower = cleanText.toLowerCase();
    if (lower.includes('invoice') || lower.includes('billed to') || lower.includes('subtotal') || lower.includes('payment terms')) {
      docType = 'Commercial Invoice / Billing Statement';
    } else if (lower.includes('agreement') || lower.includes('confidentiality') || lower.includes('non-disclosure') || lower.includes('parties hereto')) {
      docType = 'Legal Non-Disclosure Agreement / Contract';
    } else if (lower.includes('patient') || lower.includes('diagnosis') || lower.includes('physician') || lower.includes('prescription')) {
      docType = 'Medical / Health Record';
    } else if (lower.includes('payroll') || lower.includes('gross salary') || lower.includes('tax deduction') || lower.includes('revenue') || lower.includes('ebitda')) {
      docType = 'Financial Ledger / Spreadsheet';
    } else if (lower.includes('resume') || lower.includes('curriculum vitae') || lower.includes('work experience') || lower.includes('education')) {
      docType = 'Curriculum Vitae / Resume';
    } else if (lower.includes('error') || lower.includes('exception') || lower.includes('stack trace') || lower.includes('status code')) {
      docType = 'Technical System / Log Report';
    } else if (fileType === 'excel') {
      docType = 'Data Workbook / Table';
    }

    // Sentiment / Tone heuristic
    let tone = 'Professional & Objective';
    const urgentKeywords = ['urgent', 'immediate', 'penalty', 'breach', 'default', 'action required', 'critical'];
    const positiveKeywords = ['success', 'approved', 'profit', 'bonus', 'growth', 'effective', 'awarded'];
    const negativeKeywords = ['dispute', 'liability', 'failure', 'violation', 'terminated', 'lawsuit'];

    const hasUrgent = urgentKeywords.some(k => lower.includes(k));
    const posCount = positiveKeywords.filter(k => lower.includes(k)).length;
    const negCount = negativeKeywords.filter(k => lower.includes(k)).length;

    if (hasUrgent) tone = 'Urgent & Legally Binding';
    else if (posCount > negCount && posCount > 1) tone = 'Positive & Favorable';
    else if (negCount > posCount && negCount > 1) tone = 'Cautious & Risk-Conscious';

    // Readability metric (Flesch Reading Ease estimate)
    let readingEase = 65;
    if (wordCount > 20 && lines.length > 0) {
      const avgSentenceLength = wordCount / Math.max(1, lines.length);
      const avgSyllablesPerWord = 1.5; // Heuristic approximation
      readingEase = Math.round(206.835 - (1.015 * avgSentenceLength) - (84.6 * avgSyllablesPerWord));
      readingEase = Math.max(10, Math.min(100, readingEase));
    }

    // Extractive summary (Top salient sentences)
    const sentences = cleanText
      .replace(/([.?!])\s*(?=[A-Z])/g, "$1|")
      .split("|")
      .map(s => s.trim())
      .filter(s => s.length > 25 && s.length < 300);

    let summary = '';
    if (sentences.length <= 3) {
      summary = sentences.join(' ');
    } else {
      // Pick first sentence, a middle sentence with numbers/keywords, and last sentence
      const first = sentences[0];
      const middle = sentences.slice(1, -1).find(s => /\d|\$|agreed|total|obligation|liability/i.test(s)) || sentences[1];
      const last = sentences[sentences.length - 1];
      summary = [first, middle, last].filter(Boolean).join(' ');
    }

    if (!summary && cleanText) {
      summary = cleanText.substring(0, 300) + '...';
    }

    // Extract key entities / bullet takeaways
    const takeaways = [];
    if (metadata.sheets) {
      takeaways.push(`Spreadsheet contains ${metadata.sheets.length} active sheet(s) with ${metadata.totalRows || 0} total records.`);
    }
    if (metadata.pageCount) {
      takeaways.push(`Document spans ${metadata.pageCount} page(s) with standard formatting.`);
    }
    if (metadata.dimensions) {
      takeaways.push(`Visual dimensions: ${metadata.dimensions.width} x ${metadata.dimensions.height}px.`);
    }
    if (sentences.length > 0) {
      takeaways.push(`Synthesized ${wordCount.toLocaleString()} words across ${lines.length} content blocks.`);
    }
    takeaways.push(`Estimated content readability index: ${readingEase}/100 (${readingEase > 60 ? 'Standard/Plain English' : 'Complex/Technical'}).`);

    return {
      source: 'IRIS Heuristic Engine (Local)',
      docType,
      tone,
      readingEase,
      wordCount,
      charCount,
      summary: summary || 'No textual content could be parsed from this file.',
      takeaways,
      securityAdvisory: 'Local pattern scanning active. For deep semantic reasoning and contextual fraud auditing, connect your Gemini API key in Settings.'
    };
  }

  /**
   * Cloud AI Deep Analysis using Google Gemini API
   */
  async analyzeWithGemini(fileData, promptType = 'full_analysis') {
    if (!this.hasApiKey()) {
      return this.generateLocalAnalysis(fileData.rawText, fileData.metadata, fileData.type);
    }

    const systemPrompt = `You are IRIS AI, an elite document intelligence, forensic parsing, and cybersecurity auditing analyst.
Analyze the following document content extracted from a ${fileData.name} (${fileData.type}).
Provide an objective, high-value structured JSON response with the following keys:
{
  "docType": "Short specific document classification",
  "executiveSummary": "A concise 2-3 paragraph executive overview highlighting critical details, parties, financial figures, or purposes",
  "keyTakeaways": ["Point 1", "Point 2", "Point 3", "Point 4"],
  "riskAssessment": "Comprehensive security, privacy, legal, or financial risk breakdown",
  "actionItems": ["Actionable next step 1", "Actionable next step 2"],
  "tone": "Document tone (e.g. Formal Legal, Urgent Notice, Collaborative)",
  "complianceFlags": ["Any GDPR, HIPAA, PCI-DSS or regulatory concerns detected"]
}`;

    const userContent = `File Metadata: ${JSON.stringify(fileData.metadata)}
File Content Sample:
${(fileData.rawText || '').substring(0, 15000)}`;

    const url = `https://generativelanguage.googleapis.com/v1beta/models/${this.selectedModel}:generateContent?key=${this.apiKey}`;

    try {
      const response = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          contents: [
            {
              role: 'user',
              parts: [
                { text: systemPrompt + '\n\n' + userContent }
              ]
            }
          ],
          generationConfig: {
            temperature: 0.2,
            responseMimeType: 'application/json'
          }
        })
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.error?.message || `API error ${response.status}`);
      }

      const result = await response.json();
      const rawResponseText = result.candidates?.[0]?.content?.parts?.[0]?.text;
      if (!rawResponseText) throw new Error('Empty response from Gemini API');

      const parsed = JSON.parse(rawResponseText);
      return {
        source: `Google Gemini (${this.selectedModel})`,
        docType: parsed.docType,
        tone: parsed.tone || 'Formal',
        summary: parsed.executiveSummary,
        takeaways: parsed.keyTakeaways || [],
        riskAssessment: parsed.riskAssessment,
        actionItems: parsed.actionItems || [],
        complianceFlags: parsed.complianceFlags || [],
        isCloudAi: true
      };
    } catch (err) {
      console.warn('Gemini cloud API call failed, falling back to local analysis:', err);
      const fallback = this.generateLocalAnalysis(fileData.rawText, fileData.metadata, fileData.type);
      fallback.apiWarning = `Gemini API notice: ${err.message}. Showing local heuristic analysis.`;
      return fallback;
    }
  }

  /**
   * Interactive Q&A (Chat with Document)
   */
  async askQuestion(question, fileData, chatHistory = []) {
    const cleanQ = (question || '').trim();
    if (!cleanQ) return 'Please enter a valid question.';

    // If Gemini API Key is available, use Gemini for Q&A
    if (this.hasApiKey()) {
      const url = `https://generativelanguage.googleapis.com/v1beta/models/${this.selectedModel}:generateContent?key=${this.apiKey}`;
      const contextPrompt = `You are IRIS Document Assistant answering user questions about a scanned file named "${fileData.name}" (${fileData.type}).
Document Content:
${(fileData.rawText || '').substring(0, 18000)}

Please answer the user's question directly, accurately, and concisely based on the document. If information is not present, state so clearly.`;

      const contents = [
        { role: 'user', parts: [{ text: contextPrompt }] }
      ];

      for (const msg of chatHistory.slice(-4)) {
        contents.push({
          role: msg.sender === 'user' ? 'user' : 'model',
          parts: [{ text: msg.text }]
        });
      }

      contents.push({
        role: 'user',
        parts: [{ text: cleanQ }]
      });

      try {
        const response = await fetch(url, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            contents,
            generationConfig: { temperature: 0.3 }
          })
        });

        if (response.ok) {
          const data = await response.json();
          return data.candidates?.[0]?.content?.parts?.[0]?.text || 'No response generated.';
        }
      } catch (err) {
        console.warn('Chat Gemini API request failed, falling back to local search:', err);
      }
    }

    // Local heuristic search answer
    return this.answerLocally(cleanQ, fileData);
  }

  /**
   * Heuristic search in document for fast local answers
   */
  answerLocally(query, fileData) {
    const text = fileData.rawText || '';
    if (!text) return 'The document appears to have no extracted text to search.';

    const qLower = query.toLowerCase();
    const sentences = text
      .replace(/([.?!])\s*(?=[A-Z])/g, "$1|")
      .split("|")
      .map(s => s.trim())
      .filter(s => s.length > 15);

    const queryTokens = qLower.split(/\s+/).filter(w => w.length > 2 && !['what', 'when', 'where', 'who', 'how', 'the', 'this', 'that', 'from', 'with'].includes(w));

    // Score sentences by token overlap
    const scored = sentences.map(sentence => {
      const sLower = sentence.toLowerCase();
      let matchCount = 0;
      for (const token of queryTokens) {
        if (sLower.includes(token)) matchCount++;
      }
      return { sentence, matchCount };
    }).filter(item => item.matchCount > 0);

    scored.sort((a, b) => b.matchCount - a.matchCount);

    if (scored.length > 0) {
      const topMatches = scored.slice(0, 3).map(m => `• "${m.sentence}"`).join('\n\n');
      return `Based on relevant excerpts found in **${fileData.name}**:\n\n${topMatches}\n\n*(Tip: Add your Gemini API key in Settings for conversational reasoning)*`;
    }

    return `I searched **${fileData.name}** for keywords related to "${query}", but didn't find direct matches. You can review the full text in the Viewer tab or connect Gemini API in Settings.`;
  }
}

// Attach globally
if (typeof window !== 'undefined') {
  window.GeminiService = GeminiService;
}

if (typeof module !== 'undefined' && module.exports) {
  module.exports = GeminiService;
}
