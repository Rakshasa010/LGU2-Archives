const express = require('express');
const path = require('path');
const fs = require('fs');
const multer = require('multer');
const { ensureUploadDir, uploadDir } = require('../lib/storage');
const router = express.Router();

// ensure upload dir exists
ensureUploadDir();

// simple metadata store (in-memory for demo; persist to file if needed)
const metaFile = path.join(uploadDir, '..', 'files-meta.json'); // optional persist location
let store = [];
try { if (fs.existsSync(metaFile)) store = JSON.parse(fs.readFileSync(metaFile)); } catch(e){}

/* multer disk storage */
const storage = multer.diskStorage({
  destination: function (req, file, cb) { cb(null, uploadDir); },
  filename: function (req, file, cb) {
    const name = Date.now() + '-' + file.originalname.replace(/\s+/g, '_');
    cb(null, name);
  }
});
const upload = multer({ storage });

router.post('/upload', upload.single('file'), (req, res) => {
  if (!req.file) return res.status(400).json({ ok: false, error: 'No file provided in field "file"' });
  const absPath = path.resolve(req.file.path);
  console.log('Saved file to:', absPath);
  const record = {
    id: String(Date.now()) + Math.random().toString(36).slice(2,8),
    filename: req.file.filename,
    originalName: req.file.originalname,
    path: path.relative(process.cwd(), absPath)
  };
  store.push(record);
  try { fs.writeFileSync(metaFile, JSON.stringify(store, null, 2)); } catch(e){ console.warn('Could not persist metadata:', e.message); }
  res.json({ ok: true, filename: record.filename, path: record.path, id: record.id });
});

router.get('/', (req, res) => res.json(store));

router.get('/:id', (req, res) => {
  const r = store.find(x => x.id === req.params.id);
  if (!r) return res.status(404).json({ ok: false, error: 'Not found' });
  res.json(r);
});

router.delete('/:id', (req, res) => {
  const i = store.findIndex(x => x.id === req.params.id);
  if (i === -1) return res.status(404).json({ ok: false, error: 'Not found' });
  const [removed] = store.splice(i,1);
  try { fs.unlinkSync(path.join(uploadDir, removed.filename)); } catch(e){ console.warn('Failed deleting file:', e.message); }
  try { fs.writeFileSync(metaFile, JSON.stringify(store, null, 2)); } catch(e){ console.warn('Could not persist metadata:', e.message); }
  res.json({ ok: true });
});

router.put('/:id', (req, res) => {
  const r = store.find(x => x.id === req.params.id);
  if (!r) return res.status(404).json({ ok: false, error: 'Not found' });
  if (req.body && req.body.filename) r.filename = req.body.filename;
  try { fs.writeFileSync(metaFile, JSON.stringify(store, null, 2)); } catch(e){ console.warn('Could not persist metadata:', e.message); }
  res.json({ ok: true, record: r });
});

module.exports = router;
