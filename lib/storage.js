const fs = require('fs');
const path = require('path');

const uploadDir = path.join(process.cwd(), 'uploads'); // project-relative uploads/
function ensureUploadDir() {
  if (!fs.existsSync(uploadDir)) {
    fs.mkdirSync(uploadDir, { recursive: true });
    console.log('Created upload dir at', uploadDir);
  }
  try { fs.accessSync(uploadDir, fs.constants.W_OK); } catch(e) {
    console.warn('Upload dir not writable:', uploadDir);
  }
}
module.exports = { ensureUploadDir, uploadDir };
