(function () {
  const patterns = {
    '0':'nnnwwnwnn','1':'wnnwnnnnw','2':'nnwwnnnnw','3':'wnwwnnnnn','4':'nnnwwnnnw',
    '5':'wnnwwnnnn','6':'nnwwwnnnn','7':'nnnwnnwnw','8':'wnnwnnwnn','9':'nnwwnnwnn',
    'A':'wnnnnwnnw','B':'nnwnnwnnw','C':'wnwnnwnnn','D':'nnnnwwnnw','E':'wnnnwwnnn',
    'F':'nnwnwwnnn','G':'nnnnnwwnw','H':'wnnnnwwnn','I':'nnwnnwwnn','J':'nnnnwwwnn',
    'K':'wnnnnnnww','L':'nnwnnnnww','M':'wnwnnnnwn','N':'nnnnwnnww','O':'wnnnwnnwn',
    'P':'nnwnwnnwn','Q':'nnnnnnwww','R':'wnnnnnwwn','S':'nnwnnnwwn','T':'nnnnwnwwn',
    'U':'wwnnnnnnw','V':'nwwnnnnnw','W':'wwwnnnnnn','X':'nwnnwnnnw','Y':'wwnnwnnnn',
    'Z':'nwwnwnnnn','-':'nwnnnnwnw','.':'wwnnnwnnn',' ':'nwwnnwnnn','$':'nwnwnwnnn',
    '/':'nwnwnnnwn','+':'nwnnnwnwn','%':'nnnwnwnwn','*':'nwnnwnwnn'
  };
  const charactersByPattern = Object.keys(patterns).reduce(function (map, character) {
    map[patterns[character]] = character;
    return map;
  }, {});

  function render(svg, value) {
    const code = String(value || '').toUpperCase();
    if (!code || !/^[A-Z0-9 .\-$/+%]+$/.test(code)) {
      throw new Error('Asset code contains characters that cannot be printed as a Code 39 barcode.');
    }

    const encoded = '*' + code + '*';
    const narrow = 2;
    const wide = 5;
    const height = 48;
    let x = 0;
    const bars = [];

    for (let c = 0; c < encoded.length; c++) {
      const pattern = patterns[encoded[c]];
      if (!pattern) throw new Error('Unsupported character in asset code.');
      for (let i = 0; i < pattern.length; i++) {
        const width = pattern[i] === 'w' ? wide : narrow;
        if (i % 2 === 0) bars.push('<rect x="' + x + '" y="0" width="' + width + '" height="' + height + '"/>');
        x += width;
      }
      if (c < encoded.length - 1) x += narrow;
    }

    svg.setAttribute('viewBox', '0 0 ' + x + ' ' + height);
    svg.setAttribute('preserveAspectRatio', 'none');
    svg.setAttribute('role', 'img');
    svg.setAttribute('aria-label', 'Code 39 barcode for ' + code);
    svg.innerHTML = '<title>' + code + '</title>' + bars.join('');
  }

  async function scan(video, onCode, onError) {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      onError('Camera access requires HTTPS (or localhost). Enter the printed asset code below instead.');
      return function () {};
    }

    let stream;
    let stopped = false;
    let detector = null;
    try {
      if ('BarcodeDetector' in window) {
        try {
          const formats = await BarcodeDetector.getSupportedFormats();
          if (formats.includes('code_39')) detector = new BarcodeDetector({ formats: ['code_39'] });
        } catch (error) {
          console.warn('Native Code 39 scanning unavailable; using the built-in decoder.', error);
        }
      }
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
      video.srcObject = stream;
      await video.play();
      const canvas = document.createElement('canvas');
      const context = canvas.getContext('2d', { willReadFrequently: true });

      const detect = async function () {
        if (stopped) return;
        try {
          if (detector) {
            const codes = await detector.detect(video);
            if (codes.length && codes[0].rawValue) {
              onCode(codes[0].rawValue);
              stop();
              return;
            }
          } else if (video.videoWidth) {
            const width = Math.min(video.videoWidth, 960);
            const height = Math.round(video.videoHeight * width / video.videoWidth);
            if (canvas.width !== width || canvas.height !== height) {
              canvas.width = width;
              canvas.height = height;
            }
            context.drawImage(video, 0, 0, width, height);
            for (const ratio of [0.4, 0.5, 0.6]) {
              const row = context.getImageData(0, Math.round(height * ratio), width, 1);
              const value = decodeRow(row.data, width);
              if (value) {
                onCode(value);
                stop();
                return;
              }
            }
          }
        } catch (error) {
          onError('Could not read the barcode: ' + error.message);
        }
        if (!stopped) window.requestAnimationFrame(detect);
      };
      window.requestAnimationFrame(detect);
    } catch (error) {
      onError('Could not start the camera: ' + error.message);
      if (stream) stream.getTracks().forEach(function (track) { track.stop(); });
    }

    function stop() {
      stopped = true;
      if (stream) stream.getTracks().forEach(function (track) { track.stop(); });
      video.srcObject = null;
    }
    return stop;
  }

  function decodeRow(data, width) {
    const histogram = new Uint32Array(256);
    for (let x = 0; x < width; x++) {
      const offset = x * 4;
      const gray = Math.round((data[offset] * 299 + data[offset + 1] * 587 + data[offset + 2] * 114) / 1000);
      histogram[gray]++;
    }

    let total = width;
    let sum = 0;
    for (let i = 0; i < 256; i++) sum += i * histogram[i];
    let backgroundWeight = 0;
    let backgroundSum = 0;
    let bestVariance = 0;
    let threshold = 128;
    for (let i = 0; i < 256; i++) {
      backgroundWeight += histogram[i];
      if (!backgroundWeight) continue;
      const foregroundWeight = total - backgroundWeight;
      if (!foregroundWeight) break;
      backgroundSum += i * histogram[i];
      const meanBackground = backgroundSum / backgroundWeight;
      const meanForeground = (sum - backgroundSum) / foregroundWeight;
      const variance = backgroundWeight * foregroundWeight * Math.pow(meanBackground - meanForeground, 2);
      if (variance > bestVariance) {
        bestVariance = variance;
        threshold = i;
      }
    }

    const runs = [];
    let black = false;
    let length = 0;
    for (let x = 0; x < width; x++) {
      const offset = x * 4;
      const gray = (data[offset] * 299 + data[offset + 1] * 587 + data[offset + 2] * 114) / 1000;
      const isBlack = gray <= threshold;
      if (isBlack === black) {
        length++;
      } else {
        if (length) runs.push({ black: black, length: length });
        black = isBlack;
        length = 1;
      }
    }
    if (length) runs.push({ black: black, length: length });
    while (runs.length && !runs[0].black) runs.shift();

    let pos = 0;
    let started = false;
    let decoded = '';
    while (pos + 9 <= runs.length) {
      const symbol = runs.slice(pos, pos + 9);
      if (symbol.some(function (run, index) { return run.black !== (index % 2 === 0); })) break;
      const widths = symbol.map(function (run) { return run.length; }).sort(function (a, b) { return a - b; });
      const cutoff = (widths[5] + widths[6]) / 2;
      const pattern = symbol.map(function (run) { return run.length >= cutoff ? 'w' : 'n'; }).join('');
      const character = charactersByPattern[pattern];
      if (!character) break;

      if (!started) {
        if (character !== '*') break;
        started = true;
      } else if (character === '*') {
        return decoded || null;
      } else {
        decoded += character;
      }
      pos += 9;
      if (runs[pos] && !runs[pos].black) pos++;
    }
    return null;
  }

  window.AssetBarcode = { render: render, scan: scan, decodeRow: decodeRow };
})();
