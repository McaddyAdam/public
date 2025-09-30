import { createRoot, render, StrictMode, useState, useEffect, createInterpolateElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, TextControl, Spinner, Notice } from '@wordpress/components';

import "./scss/style.scss"

const domElement = document.getElementById( window.wpmudevDriveTest.dom_element_id );

const WPMUDEV_DriveTest = () => {
    const [isAuthenticated, setIsAuthenticated] = useState(window.wpmudevDriveTest.authStatus || false);
    const [hasCredentials, setHasCredentials] = useState(window.wpmudevDriveTest.hasCredentials || false);
    const [showCredentials, setShowCredentials] = useState(!window.wpmudevDriveTest.hasCredentials);
    const [isLoading, setIsLoading] = useState(false);
    const [files, setFiles] = useState([]);
    const [uploadFile, setUploadFile] = useState(null);
    const [folderName, setFolderName] = useState('');
    const [notice, setNotice] = useState({ message: '', type: '' });
    const [uploadProgress, setUploadProgress] = useState(0);
    const [needsReauth, setNeedsReauth] = useState(false);
    const [credentials, setCredentials] = useState({
        clientId: '',
        clientSecret: ''
    });

    useEffect(() => {
    }, [isAuthenticated]);

    const showNotice = (message, type = 'success') => {
        setNotice({ message, type });
        setTimeout(() => setNotice({ message: '', type: '' }), 5000);
    };

    const handleSaveCredentials = async () => {
        if (!credentials.clientId.trim() || !credentials.clientSecret.trim()) {
            showNotice(__('Client ID and Client Secret are required', 'wpmudev-plugin-test'), 'error');
            return;
        }

        setIsLoading(true);
        try {
            const resp = await fetch(`/wp-json/${window.wpmudevDriveTest.restEndpointSave}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.wpmudevDriveTest.nonce,
                },
                body: JSON.stringify({ client_id: credentials.clientId.trim(), client_secret: credentials.clientSecret.trim() }),
            });
            const res = await resp.json().catch(() => null);

            if (res && res.success) {
                setHasCredentials(true);
                setShowCredentials(false);
                showNotice(__('Credentials saved', 'wpmudev-plugin-test'), 'success');
            } else {
                showNotice(__('Failed to save credentials', 'wpmudev-plugin-test'), 'error');
            }
        } catch (err) {
            console.error(err);
            showNotice(err.message || __('Failed to save credentials', 'wpmudev-plugin-test'), 'error');
        } finally {
            setIsLoading(false);
        }
    };

    const handleAuth = async () => {
        setIsLoading(true);
        try {
            const resp = await fetch(`/wp-json/${window.wpmudevDriveTest.restEndpointAuth}`, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': window.wpmudevDriveTest.nonce,
                },
            });
            const res = await resp.json().catch(() => null);

            if (res && res.url) {
                // Open auth URL in a new window so callback can redirect back to admin.
                window.open(res.url, '_blank');
                showNotice(__('Opened Google auth page. Complete authentication in the new tab.', 'wpmudev-plugin-test'), 'success');
            } else {
                showNotice(__('Failed to start authentication', 'wpmudev-plugin-test'), 'error');
            }
        } catch (err) {
            console.error(err);
            showNotice(err.message || __('Failed to start authentication', 'wpmudev-plugin-test'), 'error');
        } finally {
            setIsLoading(false);
        }
    };

    const loadFiles = async () => {
        setIsLoading(true);
        try {
            const resp = await fetch(`/wp-json/${window.wpmudevDriveTest.restEndpointFiles}?page_size=50`, {
                method: 'GET',
                headers: { 'X-WP-Nonce': window.wpmudevDriveTest.nonce },
            });
            if (resp.status === 401) {
                // Attempt to clear tokens and prompt re-auth
                await fetch(`/wp-json/${window.wpmudevDriveTest.restEndpointRoot}/clear-tokens`, {
                    method: 'POST',
                    headers: { 'X-WP-Nonce': window.wpmudevDriveTest.nonce },
                }).catch(() => null);
                showNotice(__('Authentication expired. Please re-authenticate.', 'wpmudev-plugin-test'), 'error');
                setNeedsReauth(true);
                setIsLoading(false);
                return;
            }
            const res = await resp.json().catch(() => null);
            if (res && res.files) setFiles(res.files); else setFiles([]);
        } catch (err) {
            console.error(err);
            showNotice(err.message || __('Failed to load files', 'wpmudev-plugin-test'), 'error');
        } finally {
            setIsLoading(false);
        }
    };

    const handleUpload = async () => {
        if (!uploadFile) {
            showNotice(__('No file selected', 'wpmudev-plugin-test'), 'error');
            return;
        }

        setIsLoading(true);
        const form = new FormData();
        form.append('file', uploadFile, uploadFile.name);

        // Use XHR to track progress
        try {
            await new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', `/wp-json/${window.wpmudevDriveTest.restEndpointUpload}`);
                xhr.setRequestHeader('X-WP-Nonce', window.wpmudevDriveTest.nonce);
                xhr.upload.onprogress = function (e) {
                    if (e.lengthComputable) {
                        const pct = Math.round((e.loaded / e.total) * 100);
                        // Update progress state (for progress bar and notice)
                        setUploadProgress(pct);
                        setNotice({ message: __('Uploading:') + ' ' + pct + '%', type: 'info' });
                    }
                };
                xhr.onload = function () {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        try {
                            const json = JSON.parse(xhr.responseText);
                            if (json && json.success) {
                                showNotice(__('Upload successful', 'wpmudev-plugin-test'), 'success');
                                loadFiles();
                                    setUploadProgress(0);
                                    resolve(json);
                            } else {
                                reject(new Error(json && json.message ? json.message : 'Upload failed'));
                            }
                        } catch (e) {
                            reject(e);
                        }
                    } else {
                        reject(new Error('Upload failed with status ' + xhr.status));
                    }
                };
                xhr.onerror = function () { reject(new Error('Upload network error')); };
                xhr.send(form);
            });
        } catch (err) {
            console.error(err);
            showNotice(err.message || __('Upload failed', 'wpmudev-plugin-test'), 'error');
        } finally {
            setIsLoading(false);
            setUploadProgress(0);
        }
    };

    const handleDownload = async (fileId, fileName) => {
        if (!fileId) return;
        setIsLoading(true);
        try {
            const resp = await fetch(`/wp-json/${window.wpmudevDriveTest.restEndpointDownload}?file_id=${encodeURIComponent(fileId)}`, {
                method: 'GET',
                headers: { 'X-WP-Nonce': window.wpmudevDriveTest.nonce },
            });
            const res = await resp.json().catch(() => null);

            if (res && res.success && res.content) {
                const binary = atob(res.content);
                const len = binary.length;
                const buffer = new Uint8Array(len);
                for (let i = 0; i < len; i++) buffer[i] = binary.charCodeAt(i);
                const blob = new Blob([buffer], { type: res.mimeType || 'application/octet-stream' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = res.filename || fileName || 'download';
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
            } else {
                showNotice(__('Failed to download file', 'wpmudev-plugin-test'), 'error');
            }
        } catch (err) {
            console.error(err);
            showNotice(err.message || __('Failed to download file', 'wpmudev-plugin-test'), 'error');
        } finally {
            setIsLoading(false);
        }
    };

    const handleCreateFolder = async () => {
        if (!folderName.trim()) {
            showNotice(__('Folder name required', 'wpmudev-plugin-test'), 'error');
            return;
        }

        setIsLoading(true);
        try {
            const resp = await fetch(`/wp-json/${window.wpmudevDriveTest.restEndpointCreate}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.wpmudevDriveTest.nonce,
                },
                body: JSON.stringify({ name: folderName.trim() }),
            });
            const res = await resp.json().catch(() => null);

            if (res && res.success) {
                showNotice(__('Folder created', 'wpmudev-plugin-test'), 'success');
                setFolderName('');
                loadFiles();
            } else {
                showNotice(__('Failed to create folder', 'wpmudev-plugin-test'), 'error');
            }
        } catch (err) {
            console.error(err);
            showNotice(err.message || __('Failed to create folder', 'wpmudev-plugin-test'), 'error');
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <>
            <div className="sui-header">
                <h1 className="sui-header-title">
                    Google Drive Test
                </h1>
                <p className="sui-description">Test Google Drive API integration for applicant assessment</p>
            </div>

            {notice.message && (
                <Notice status={notice.type} isDismissible onRemove=''>
                    {notice.message}
                </Notice>
            )}

            {showCredentials ? (
                <div className="sui-box">
                    <div className="sui-box-header">
                        <h2 className="sui-box-title">Set Google Drive Credentials</h2>
                    </div>
                    <div className="sui-box-body">
                        <div className="sui-box-settings-row">
                            <TextControl
                                help={createInterpolateElement(
                                    'You can get Client ID from <a>Google Cloud Console</a>. Make sure to enable Google Drive API.',
                                    {
                                        a: <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener noreferrer" />,
                                    }
                                )}
                                label="Client ID"
                                value={credentials.clientId}
                                onChange={(value) => setCredentials({...credentials, clientId: value})}
                            />
                        </div>

                        <div className="sui-box-settings-row">
                            <TextControl
                                help={createInterpolateElement(
                                    'You can get Client Secret from <a>Google Cloud Console</a>.',
                                    {
                                        a: <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener noreferrer" />,
                                    }
                                )}
                                label="Client Secret"
                                value={credentials.clientSecret}
                                onChange={(value) => setCredentials({...credentials, clientSecret: value})}
                                type="password"
                            />
                        </div>

                        <div className="sui-box-settings-row">
                            <span>Please use this URL <em>{window.wpmudevDriveTest.redirectUri}</em> in your Google API's <strong>Authorized redirect URIs</strong> field.</span>
                        </div>

                        <div className="sui-box-settings-row">
                            <p><strong>Required scopes for Google Drive API:</strong></p>
                            <ul>
                                <li>https://www.googleapis.com/auth/drive.file</li>
                                <li>https://www.googleapis.com/auth/drive.readonly</li>
                            </ul>
                        </div>
                    </div>
                    <div className="sui-box-footer">
                        <div className="sui-actions-right">
                            <Button
                                variant="primary"
                                onClick={handleSaveCredentials}
                                disabled={isLoading}
                            >
                                {isLoading ? <Spinner /> : 'Save Credentials'}
                            </Button>
                        </div>
                    </div>
                </div>
            ) : !isAuthenticated ? (
                <div className="sui-box">
                    <div className="sui-box-header">
                        <h2 className="sui-box-title">Authenticate with Google Drive</h2>
                    </div>
                    <div className="sui-box-body">
                        <div className="sui-box-settings-row">
                            <p>Please authenticate with Google Drive to proceed with the test.</p>
                            <p><strong>This test will require the following permissions:</strong></p>
                            <ul>
                                <li>View and manage Google Drive files</li>
                                <li>Upload new files to Drive</li>
                                <li>Create folders in Drive</li>
                            </ul>
                        </div>
                    </div>
                    <div className="sui-box-footer">
                        <div className="sui-actions-left">
                            <Button
                                variant="secondary"
                                onClick={() => setShowCredentials(true)}
                            >
                                Change Credentials
                            </Button>
                        </div>
                        <div className="sui-actions-right">
                            <Button
                                variant="primary"
                                onClick={handleAuth}
                                disabled={isLoading}
                            >
                                {isLoading ? <Spinner /> : 'Authenticate with Google Drive'}
                            </Button>
                        </div>
                    </div>
                </div>
            ) : (
                <>
                    {/* File Upload Section */}
                    <div className="sui-box">
                        <div className="sui-box-header">
                            <h2 className="sui-box-title">Upload File to Drive</h2>
                        </div>
                        <div className="sui-box-body">
                            <div className="sui-box-settings-row">
                                <input
                                    type="file"
                                    onChange={(e) => setUploadFile(e.target.files[0])}
                                    className="drive-file-input"
                                />
                                {uploadFile && (
                                    <p><strong>Selected:</strong> {uploadFile.name} ({Math.round(uploadFile.size / 1024)} KB)</p>
                                )}
                            </div>
                        </div>
                        <div className="sui-box-footer">
                            <div className="sui-actions-right">
                                <Button
                                    variant="primary"
                                    onClick={handleUpload}
                                    disabled={isLoading || !uploadFile}
                                >
                                    {isLoading ? <Spinner /> : 'Upload to Drive'}
                                </Button>
                            </div>
                            {uploadProgress > 0 && (
                                <div className="drive-upload-progress" style={{ padding: '8px 16px' }}>
                                    <div style={{ fontSize: '12px' }}>{__('Uploading')}: {uploadProgress}%</div>
                                    <div style={{ height: '6px', background: '#eee', borderRadius: 3, marginTop: 6 }}>
                                        <div style={{ width: `${uploadProgress}%`, height: '6px', background: '#0073aa', borderRadius: 3 }} />
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Create Folder Section */}
                    <div className="sui-box">
                        <div className="sui-box-header">
                            <h2 className="sui-box-title">Create New Folder</h2>
                        </div>
                        <div className="sui-box-body">
                            <div className="sui-box-settings-row">
                                <TextControl
                                    label="Folder Name"
                                    value={folderName}
                                    onChange={setFolderName}
                                    placeholder="Enter folder name"
                                />
                            </div>
                        </div>
                        <div className="sui-box-footer">
                            <div className="sui-actions-right">
                                <Button
                                    variant="secondary"
                                    onClick={handleCreateFolder}
                                    disabled={isLoading || !folderName.trim()}
                                >
                                    {isLoading ? <Spinner /> : 'Create Folder'}
                                </Button>
                            </div>
                        </div>
                    </div>

                    {/* Files List Section */}
                    <div className="sui-box">
                        <div className="sui-box-header">
                            <h2 className="sui-box-title">Your Drive Files</h2>
                            <div className="sui-actions-right">
                                {needsReauth ? (
                                    <Button variant="primary" onClick={handleAuth} disabled={isLoading}>
                                        {isLoading ? <Spinner /> : 'Re-authenticate'}
                                    </Button>
                                ) : (
                                    <Button
                                        variant="secondary"
                                        onClick={loadFiles}
                                        disabled={isLoading}
                                    >
                                        {isLoading ? <Spinner /> : 'Refresh Files'}
                                    </Button>
                                )}
                            </div>
                        </div>
                        <div className="sui-box-body">
                            {isLoading ? (
                                <div className="drive-loading">
                                    <Spinner />
                                    <p>Loading files...</p>
                                </div>
                            ) : files.length > 0 ? (
                                <div className="drive-files-grid">
                                    {files.map((file) => (
                                        <div key={file.id} className="drive-file-item">
                                            <div className="file-info">
                                                <strong>{file.name}</strong>
                                                <small>
                                                    {file.modifiedTime ? new Date(file.modifiedTime).toLocaleDateString() : 'Unknown date'}
                                                </small>
                                            </div>
                                            <div className="file-actions">
                                                {file.webViewLink && (
                                                    <Button
                                                        variant="link"
                                                        size="small"
                                                        href=''
                                                        target="_blank"
                                                    >
                                                        View in Drive
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="sui-box-settings-row">
                                    <p>No files found in your Drive. Upload a file or create a folder to get started.</p>
                                </div>
                            )}
                        </div>
                    </div>
                </>
            )}
        </>
    );
}

if ( createRoot ) {
    createRoot( domElement ).render(<StrictMode><WPMUDEV_DriveTest/></StrictMode>);
} else {
    render( <StrictMode><WPMUDEV_DriveTest/></StrictMode>, domElement );
}