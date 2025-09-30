const { spawnSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const root = process.cwd();
console.log('BUILD SCRIPT START. cwd=', root);
const pkg = require(path.join(root, 'package.json'));
const buildDir = path.join(root, 'build', pkg.name);
const zipPath = path.join(root, 'build', `${pkg.name}-${pkg.version}.zip`);

function run(cmd, opts = {}) {
	console.log('> ' + cmd);
	const res = spawnSync(cmd, { shell: true, stdio: 'inherit', ...opts });
	console.log('  -> child status:', res && res.status, 'signal:', res && res.signal, 'error:', res && res.error);
	if (!res || res.status !== 0) {
		throw new Error(`Command failed: ${cmd} (status=${res && res.status})`);
	}
}

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
			// also skip nested node_modules/vendor
			if (f === 'node_modules' || f === 'vendor') continue;
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

		// 2) webpack compile - optional: don't fail the whole build if compile fails
		try {
			run('npm run compile');
		} catch (e) {
			console.log('npm run compile failed; continuing with existing assets. Error:', e && e.message ? e.message : e);
		}

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
			'app', 'core', 'languages', 'assets', 'uninstall.php', 'wpmudev-plugin-test.php',
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
		try {
			run(psCmd);
			const stats = fs.statSync(zipPath);
			console.log('Zip created:', zipPath, 'size:', (stats.size/1024).toFixed(2), 'KB');
		} catch (e) {
			console.log('PowerShell Compress-Archive failed; trying .NET ZipFile.CreateFromDirectory fallback...');
			const psDotNet = `powershell -NoProfile -Command "Add-Type -AssemblyName System.IO.Compression.FileSystem; [System.IO.Compression.ZipFile]::CreateFromDirectory('${buildDir}', '${zipPath}');"`;
			try {
				run(psDotNet);
				const stats = fs.statSync(zipPath);
				console.log('Zip created using .NET fallback:', zipPath, 'size:', (stats.size/1024).toFixed(2), 'KB');
			} catch (e2) {
				console.log('DotNet zip failed, attempting Node fallback with archiver...');
				try {
					const archiver = require('archiver');
					const output = fs.createWriteStream(zipPath);
					const archive = archiver('zip', { zlib: { level: 9 } });
					output.on('close', function() {
						console.log('Zip created (archiver):', zipPath, 'size:', (archive.pointer()/1024).toFixed(2), 'KB');
					});
					archive.on('warning', function(err) { console.warn('Archiver warning:', err.message); });
					archive.on('error', function(err) { throw err; });
					archive.pipe(output);
					archive.directory(buildDir + '/', false);
					await archive.finalize();
				} catch (err) {
					console.error('Archiver fallback failed:', err && err.message ? err.message : err);
					throw err;
				}
			}
		}

	} catch (err) {
		console.error('Build failed:', err.message);
		process.exit(1);
	}
})();
