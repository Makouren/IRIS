/**
 * IRIS AI - Image Parser & OCR Engine
 * Analyzes image files (PNG, JPG, WEBP, GIF, SVG, BMP) and extracts visual metadata + OCR text.
 */

class ImageParser {
  constructor() {
    this.ocrWorker = null;
  }

  /**
   * Parse image file, extract dimensions, color analysis, and OCR text
   */
  async parse(file, onProgress = () => {}) {
    onProgress({ status: 'Loading visual asset...', progress: 15 });

    const previewUrl = URL.createObjectURL(file);
    const img = await this.loadImage(previewUrl);

    onProgress({ status: 'Inspecting image properties & colors...', progress: 35 });
    const visualStats = this.analyzeVisuals(img);

    onProgress({ status: 'Running Optical Character Recognition (OCR)...', progress: 50 });

    let ocrResult = { text: '', confidence: 0, lines: [] };

    try {
      if (typeof Tesseract !== 'undefined') {
        const worker = await Tesseract.createWorker('eng', 1, {
          logger: m => {
            if (m.status === 'recognizing text' && m.progress) {
              onProgress({
                status: `AI OCR Reading text: ${Math.round(m.progress * 100)}%`,
                progress: 50 + Math.round(m.progress * 40)
              });
            }
          }
        });

        const ret = await worker.recognize(img);
        await worker.terminate();

        ocrResult = {
          text: (ret.data.text || '').trim(),
          confidence: Math.round(ret.data.confidence || 0),
          lines: ret.data.lines ? ret.data.lines.map(l => ({ text: l.text.trim(), confidence: l.confidence })) : []
        };
      } else {
        console.warn('Tesseract library not detected. Skipping local OCR.');
      }
    } catch (ocrErr) {
      console.warn('OCR error during image parse:', ocrErr);
      ocrResult.error = ocrErr.message;
    }

    onProgress({ status: 'Finalizing image synthesis...', progress: 100 });

    const metadata = {
      format: file.type || 'image/jpeg',
      dimensions: {
        width: img.naturalWidth,
        height: img.naturalHeight,
        aspectRatio: (img.naturalWidth / img.naturalHeight).toFixed(2)
      },
      fileSize: file.size,
      dominantColor: visualStats.dominantColor,
      brightness: visualStats.brightness,
      ocrConfidence: ocrResult.confidence,
      hasText: ocrResult.text.length > 0
    };

    return {
      name: file.name,
      type: 'image',
      size: file.size,
      rawText: ocrResult.text,
      previewUrl,
      metadata,
      ocrData: ocrResult
    };
  }

  loadImage(src) {
    return new Promise((resolve, reject) => {
      const img = new Image();
      img.crossOrigin = 'anonymous';
      img.onload = () => resolve(img);
      img.onerror = (err) => reject(new Error('Failed to load image into DOM'));
      img.src = src;
    });
  }

  analyzeVisuals(img) {
    try {
      const canvas = document.createElement('canvas');
      const ctx = canvas.getContext('2d');
      // Sample down to 100x100 for fast calculation
      canvas.width = 100;
      canvas.height = 100;
      ctx.drawImage(img, 0, 0, 100, 100);

      const imageData = ctx.getImageData(0, 0, 100, 100);
      const data = imageData.data;
      let rTotal = 0, gTotal = 0, bTotal = 0;
      const count = data.length / 4;

      for (let i = 0; i < data.length; i += 4) {
        rTotal += data[i];
        gTotal += data[i + 1];
        bTotal += data[i + 2];
      }

      const r = Math.round(rTotal / count);
      const g = Math.round(gTotal / count);
      const b = Math.round(bTotal / count);
      const brightness = Math.round((r * 299 + g * 587 + b * 114) / 1000);

      return {
        dominantColor: `rgb(${r}, ${g}, ${b})`,
        brightness: brightness > 128 ? 'Light' : 'Dark'
      };
    } catch (e) {
      return { dominantColor: 'rgb(24, 24, 27)', brightness: 'Unknown' };
    }
  }
}

if (typeof window !== 'undefined') {
  window.ImageParser = ImageParser;
}
