const { spawnSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const root = process.cwd();
const pkg = require(path.join(root, 'package.json'));
const buildDir = path.join(root, 'build', pkg.name);
const zipPath = path.join(root, 'build', `${pkg.name}-${pkg.version}.zip`);

function run(cmd, opts = {}) {
	console.log('> ' + cmd);
	const res = spawnSync(cmd, { shell: true, stdio: 'inherit', ...opts });
	if (res.status !== 0) {
		throw new Error(`Command failed: ${cmd}`);
	}
}

function copyRecursive(src, dest) {
	if (!fs.existsSync(src)) return;
	const stat = fs.statSync(src);
	if (stat.isDirectory()) {
		if (!fs.existsSync(dest)) fs.mkdirSync(dest, { recursive: true });
		for (const f of fs.readdirSync(src)) {
			copyRecursive(path.join(src, f), path.join(dest, f));
		}
	} else {
		const dir = path.dirname(dest);
		if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
		fs.copyFileSync(src, dest);
	}
}

(async () => {
	try {
		// 1) Composer
		try {
			run('composer install --no-dev --optimize-autoloader');
		} catch (e) {
			console.log('composer not available or failed; continuing.');
		}

		// 2) webpack compile
		run('npm run compile');

		// 3) translate
		try {
			run('npm run translate');
		} catch (e) {
			console.log('translate failed; continuing.');
		}

		// 4) Copy files
		console.log('Copying files to', buildDir);
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

		// 5) Zip using PowerShell Compress-Archive (works on Windows)
		console.log('Creating zip', zipPath);
		if (fs.existsSync(zipPath)) fs.unlinkSync(zipPath);
		const psCmd = `powershell -NoProfile -Command "Compress-Archive -Path '${buildDir}\\*' -DestinationPath '${zipPath}' -Force"`;
		run(psCmd);

		const stats = fs.statSync(zipPath);
		console.log('Zip created:', zipPath, 'size:', (stats.size/1024).toFixed(2), 'KB');

	} catch (err) {
		console.error('Build failed:', err.message);
		process.exit(1);
	}
})();
