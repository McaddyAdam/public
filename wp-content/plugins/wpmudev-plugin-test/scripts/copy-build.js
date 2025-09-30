const fs = require('fs');
const path = require('path');

const root = process.cwd();
const pkg = require(path.join(root, 'package.json'));
const buildDir = path.join(root, 'build', pkg.name);

function copyRecursive(src, dest) {
    if (!fs.existsSync(src)) return;
    const stat = fs.statSync(src);
    if (stat.isDirectory()) {
        // Skip copying dependency folders to keep package small and deterministic
        const baseName = path.basename(src);
        if (baseName === 'node_modules' || baseName === 'vendor') {
            return;
        }
        if (!fs.existsSync(dest)) fs.mkdirSync(dest, { recursive: true });
        for (const f of fs.readdirSync(src)) {
            if (f === 'node_modules' || f === 'vendor') continue;
            copyRecursive(path.join(src, f), path.join(dest, f));
        }
    } else {
        const dir = path.dirname(dest);
        if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
        fs.copyFileSync(src, dest);
    }
}

console.log('Preparing build folder at', buildDir);
if (fs.existsSync(buildDir)) fs.rmSync(buildDir, { recursive: true, force: true });
fs.mkdirSync(buildDir, { recursive: true });

const copyList = [
    'app', 'core', 'languages', 'uninstall.php', 'wpmudev-plugin-test.php',
    'QUESTIONS.md', 'README.md', 'composer.json', 'package.json', 'webpack.config.js',
    'phpcs.ruleset.xml', 'phpunit.xml.dist', 'src', 'tests'
];

for (const p of copyList) {
    const src = path.join(root, p);
    const dest = path.join(buildDir, p);
    copyRecursive(src, dest);
}

console.log('Copy complete');
