<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <title><?php echo $repoName; ?> | CodeVault</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Add your existing CSS styles here */
        /* ... previous CSS styles from s_me.php ... */
        
        /* Permission badges */
        .permission-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .permission-admin { 
            background: linear-gradient(135deg, #48bb78, #38a169);
            color: white;
        }
        .permission-write { 
            background: linear-gradient(135deg, #ed8936, #dd6b20);
            color: white;
        }
        .permission-read { 
            background: linear-gradient(135deg, #718096, #4a5568);
            color: white;
        }
        
        /* Collaborator info */
        .collaborator-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            padding: 10px;
            background: var(--gray-light);
            border-radius: 10px;
        }
        
        /* Action buttons based on permission */
        .action-btn {
            transition: all 0.3s ease;
        }
        
        .action-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
        <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #7209b7;
            --success: #4cc9f0;
            --warning: #f8961e;
            --danger: #e63946;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --gray-light: #e9ecef;
            --border-radius: 16px;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
        }

        [data-theme="dark"] {
            --light: #1a1d23;
            --dark: #f8f9fa;
            --gray-light: #2d3239;
            --gray: #adb5bd;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            --glass-bg: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--secondary) 0%, var(--primary) 100%);
            color: var(--dark);
            line-height: 1.6;
            transition: var(--transition);
            min-height: 100vh;
            padding: 20px;
        }

        .shared-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Header */
        .shared-header {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius);
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: var(--shadow);
            text-align: center;
            color: white;
        }

        .header-icon {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: white;
            margin: 0 auto 20px;
        }

        .shared-header h1 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 10px;
            color: white;
        }

        .shared-header p {
            opacity: 0.9;
            font-size: 16px;
            max-width: 600px;
            margin: 0 auto;
        }

        .header-stats {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 25px;
        }

        .stat {
            text-align: center;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
            color: white;
        }

        .stat-label {
            font-size: 14px;
            opacity: 0.8;
        }

        /* Navigation */
        .shared-nav {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius);
            padding: 15px;
        }

        .nav-btn {
            flex: 1;
            padding: 15px 20px;
            background: transparent;
            border: 2px solid transparent;
            border-radius: 12px;
            color: white;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .nav-btn.active {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .nav-btn:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        /* Content Sections */
        .content-section {
            display: none;
        }

        .content-section.active {
            display: block;
        }

        /* Cards */
        .repo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }

        .repo-card {
            background: var(--light);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 1px solid var(--gray-light);
            position: relative;
            overflow: hidden;
        }

        .repo-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .repo-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 6px;
            height: 100%;
        }

        .repo-card.read::before { background: var(--gray); }
        .repo-card.write::before { background: var(--warning); }
        .repo-card.admin::before { background: var(--success); }

        .repo-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .repo-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            margin-right: 15px;
        }

        .repo-icon.public { background: var(--success); }
        .repo-icon.private { background: var(--secondary); }

        .repo-info {
            flex: 1;
        }

        .repo-name {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--dark);
        }

        .repo-owner {
            font-size: 14px;
            color: var(--gray);
            margin-bottom: 8px;
        }

        .repo-desc {
            font-size: 14px;
            color: var(--gray);
            line-height: 1.4;
            margin-bottom: 15px;
        }

        .repo-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-top: 15px;
            border-top: 1px solid var(--gray-light);
        }

        .permission-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .permission-admin { 
            background: rgba(76, 201, 240, 0.2); 
            color: var(--success);
            border: 1px solid var(--success);
        }
        .permission-write { 
            background: rgba(247, 183, 49, 0.2); 
            color: #f7b731;
            border: 1px solid #f7b731;
        }
        .permission-read { 
            background: rgba(108, 117, 125, 0.2); 
            color: var(--gray);
            border: 1px solid var(--gray);
        }

        .repo-date {
            font-size: 12px;
            color: var(--gray);
        }

        .repo-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #3ab0d9;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #c1121f;
            transform: translateY(-2px);
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-warning:hover {
            background: #e6890e;
            transform: translateY(-2px);
        }

        .btn-block {
            width: 100%;
            justify-content: center;
        }

        /* Invite Cards */
        .invite-card {
            background: var(--light);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 2px dashed var(--primary);
            position: relative;
        }

        .invite-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .invite-badge {
            background: var(--warning);
            color: white;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .invite-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 60px 40px;
            color: var(--gray);
            background: var(--light);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .empty-state i {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h3 {
            margin-bottom: 10px;
            color: var(--dark);
            font-size: 24px;
        }

        .empty-state p {
            font-size: 16px;
            max-width: 400px;
            margin: 0 auto 25px;
        }

        /* Alert Styles */
        .alert {
            padding: 15px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: rgba(76, 201, 240, 0.1);
            border: 1px solid var(--success);
            color: var(--success);
        }

        .alert-error {
            background: rgba(230, 57, 70, 0.1);
            border: 1px solid var(--danger);
            color: var(--danger);
        }

        /* Header Actions */
        .header-actions {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
        }

        .theme-toggle, .back-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid var(--glass-border);
            color: white;
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            backdrop-filter: blur(10px);
        }

        .theme-toggle:hover, .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .shared-header h1 {
                font-size: 28px;
            }
            
            .header-stats {
                flex-direction: column;
                gap: 15px;
            }
            
            .repo-grid {
                grid-template-columns: 1fr;
            }
            
            .shared-nav {
                flex-direction: column;
            }
            
            .repo-header {
                flex-direction: column;
            }
            
            .repo-icon {
                margin-bottom: 15px;
            }
            
            .repo-actions {
                flex-direction: column;
            }
            
            .invite-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header with permission info -->
        <div class="header">
            <div class="repo-info">
                <h1><?php echo $repoName; ?></h1>
                <p>Owner: <?php echo $ownerName; ?></p>
                <div class="collaborator-info">
                    <span>Your role: </span>
                    <span class="permission-badge permission-<?php echo $userPermission; ?>">
                        <?php echo ucfirst($userPermission); ?>
                    </span>
                    <?php if (!$isOwner): ?>
                        <span>(Collaborator)</span>
                    <?php else: ?>
                        <span>(Owner)</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="header-actions">
                <?php if ($userPermission === 'admin'): ?>
                    <a href="share.php?id=<?php echo $repoId; ?>" class="btn btn-primary">
                        <i class="fas fa-share-alt"></i> Share
                    </a>
                <?php endif; ?>
                <a href="my_rep.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <!-- File Upload Section (only for write/admin) -->
        <?php if ($userPermission === 'write' || $userPermission === 'admin'): ?>
        <div class="upload-section">
            <h3>Upload File</h3>
            <form method="POST" enctype="multipart/form-data" class="upload-form">
                <input type="file" name="file" required accept=".txt,.pdf,.doc,.docx,.js,.php,.html,.css,.py,.java,.cpp,.c,.json,.xml,.sql">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-upload"></i> Upload File
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Files List -->
        <div class="files-section">
            <h3>Files</h3>
            <?php if (empty($files)): ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <h4>No files yet</h4>
                    <p><?php echo ($userPermission === 'read') ? 'Only users with write access can upload files.' : 'Upload your first file to get started.'; ?></p>
                </div>
            <?php else: ?>
                <div class="files-grid">
                    <?php foreach ($files as $file): ?>
                        <div class="file-card">
                            <div class="file-icon">
                                <i class="fas fa-file"></i>
                            </div>
                            <div class="file-info">
                                <h4><?php echo htmlspecialchars($file['name']); ?></h4>
                                <p>Size: <?php echo number_format(strlen($file['content'])); ?> bytes</p>
                                <p>Uploaded: <?php echo date('M j, Y g:i A', strtotime($file['created_at'])); ?></p>
                            </div>
                            <div class="file-actions">
                                <button class="btn btn-secondary view-file" data-file-id="<?php echo $file['id']; ?>" data-file-name="<?php echo htmlspecialchars($file['name']); ?>">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                
                                <?php if ($userPermission === 'write' || $userPermission === 'admin'): ?>
                                    <button class="btn btn-primary edit-file" data-file-id="<?php echo $file['id']; ?>" data-file-name="<?php echo htmlspecialchars($file['name']); ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                        <button type="submit" name="delete_file" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this file?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <a href="download.php?file_id=<?php echo $file['id']; ?>" class="btn btn-success">
                                    <i class="fas fa-download"></i> Download
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Danger Zone (admin only) -->
        <?php if ($userPermission === 'admin'): ?>
        <div class="danger-zone">
            <h3>Danger Zone</h3>
            <div class="danger-card">
                <h4>Delete Repository</h4>
                <p>Once you delete a repository, there is no going back. Please be certain.</p>
                <form method="POST">
                    <button type="submit" name="delete_repo" class="btn btn-danger" onclick="return confirm('Are you absolutely sure? This will delete ALL files and remove all collaborators.')">
                        <i class="fas fa-trash"></i> Delete this repository
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- File Viewer Modal -->
    <div id="fileModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalFileName">File Content</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <pre id="fileContent"></pre>
            </div>
        </div>
    </div>

    <!-- File Editor Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="editModalFileName">Edit File</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="editForm">
                    <input type="hidden" name="file_id" id="editFileId">
                    <textarea name="file_content" id="editFileContent" rows="20" style="width: 100%; font-family: monospace;"></textarea>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" id="cancelEdit">Cancel</button>
                        <button type="submit" name="save_file" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // File viewer functionality
        document.querySelectorAll('.view-file').forEach(button => {
            button.addEventListener('click', function() {
                const fileId = this.getAttribute('data-file-id');
                const fileName = this.getAttribute('data-file-name');
                
                fetch(`get_file.php?file_id=${fileId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('modalFileName').textContent = fileName;
                            document.getElementById('fileContent').textContent = data.content;
                            document.getElementById('fileModal').style.display = 'block';
                        } else {
                            alert('Error loading file: ' + data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error loading file');
                    });
            });
        });

        // File editor functionality
        document.querySelectorAll('.edit-file').forEach(button => {
            button.addEventListener('click', function() {
                const fileId = this.getAttribute('data-file-id');
                const fileName = this.getAttribute('data-file-name');
                
                fetch(`get_file.php?file_id=${fileId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('editModalFileName').textContent = 'Edit: ' + fileName;
                            document.getElementById('editFileId').value = fileId;
                            document.getElementById('editFileContent').value = data.content;
                            document.getElementById('editModal').style.display = 'block';
                        } else {
                            alert('Error loading file: ' + data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error loading file');
                    });
            });
        });

        // Modal close functionality
        document.querySelectorAll('.close, #cancelEdit').forEach(element => {
            element.addEventListener('click', function() {
                document.getElementById('fileModal').style.display = 'none';
                document.getElementById('editModal').style.display = 'none';
            });
        });

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const fileModal = document.getElementById('fileModal');
            const editModal = document.getElementById('editModal');
            
            if (event.target === fileModal) {
                fileModal.style.display = 'none';
            }
            if (event.target === editModal) {
                editModal.style.display = 'none';
            }
        });

        // Permission-based UI adjustments
        document.addEventListener('DOMContentLoaded', function() {
            const userPermission = '<?php echo $userPermission; ?>';
            
            // Disable certain actions for read-only users
            if (userPermission === 'read') {
                document.querySelectorAll('.edit-file, [name="delete_file"]').forEach(btn => {
                    btn.disabled = true;
                    btn.title = 'Read-only access';
                });
            }
        });
    </script>
</body>
</html>