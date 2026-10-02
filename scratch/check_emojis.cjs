const fs = require('fs');
const path = require('path');

const emojiRegex = /[\u{1F300}-\u{1F6FF}\u{1F900}-\u{1F9FF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}\u{1F1E6}-\u{1F1FF}]/u;

function scanDir(dir) {
    const files = fs.readdirSync(dir, { withFileTypes: true });
    for (const file of files) {
        const fullPath = path.join(dir, file.name);
        if (file.isDirectory()) {
            if (!['.git', 'node_modules', 'vendor', 'storage'].includes(file.name)) {
                scanDir(fullPath);
            }
        } else if (file.isFile() && /\.(php|blade\.php|js|json|css)$/.test(file.name)) {
            const content = fs.readFileSync(fullPath, 'utf8');
            const lines = content.split('\n');
            lines.forEach((line, idx) => {
                if (emojiRegex.test(line)) {
                    console.log(`EMOJI in ${fullPath}:${idx + 1}: ${line.trim()}`);
                }
            });
        }
    }
}

console.log('Scanning for emojis in resources, app, routes, preview-server.js...');
scanDir(path.join(__dirname, '../resources'));
scanDir(path.join(__dirname, '../app'));
scanDir(path.join(__dirname, '../routes'));
const previewContent = fs.readFileSync(path.join(__dirname, '../preview-server.js'), 'utf8');
previewContent.split('\n').forEach((line, idx) => {
    if (emojiRegex.test(line)) {
        console.log(`EMOJI in preview-server.js:${idx + 1}: ${line.trim()}`);
    }
});
console.log('Emoji scan complete.');
