<?php
// 1. 初始化会话
session_start();

// 启用错误报告（开发环境）
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 常量定义
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);
define('DEFAULT_PORTRAIT', 'portrait-img/c1.jpg');
define('UPLOAD_DIR', 'portrait-img/');

// 初始化变量
$msg = '';
$msgType = '';
$newId = '';
$formData = [
    'pname' => '',
    'gender' => '',
    'portrait' => DEFAULT_PORTRAIT
];

// 2. 辅助函数
function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function validatePassword($password) {
    // 只检查最小长度
    if (strlen($password) < 4) {
        return '密码长度至少4位';
    }
    return true;
}

function validateUsername($username) {
    if (strlen($username) < 2 || strlen($username) > 20) {
        return '昵称长度需在2-20位之间';
    }
    if (!preg_match('/^[\x{4e00}-\x{9fa5}a-zA-Z0-9_]+$/u', $username)) {
        return '昵称只能包含中文、英文、数字和下划线';
    }
    return true;
}

function checkUsernameExists($username, $users) {
    foreach ($users as $user) {
        if ($user['pname'] === $username) {
            return true;
        }
    }
    return false;
}

function getNextUserId($users) {
    $maxId = 0;
    foreach ($users as $user) {
        if (isset($user['id']) && (int)$user['id'] > $maxId) {
            $maxId = (int)$user['id'];
        }
    }
    return str_pad($maxId + 1, 10, '0', STR_PAD_LEFT);
}

function handleFileUpload($fileField, &$msg, &$msgType) {
    if (!isset($_FILES[$fileField]) || $_FILES[$fileField]['error'] !== UPLOAD_ERR_OK) {
        $msg = '请选择要上传的文件';
        $msgType = 'error';
        return null;
    }
    
    $file = $_FILES[$fileField];
    
    // 检查文件大小
    if ($file['size'] > MAX_FILE_SIZE) {
        $msg = '文件大小不能超过2MB';
        $msgType = 'error';
        return null;
    }
    
    // 检查文件扩展名
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileExt, ALLOWED_EXTENSIONS)) {
        $msg = '只允许上传JPG、PNG、GIF格式的图片';
        $msgType = 'error';
        return null;
    }
    
    // 检查MIME类型
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($mime, $allowedMimes)) {
        $msg = '文件类型不被允许';
        $msgType = 'error';
        return null;
    }
    
    // 创建上传目录
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    
    // 生成安全文件名
    $safeName = 'custom_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $fileExt;
    $targetPath = UPLOAD_DIR . $safeName;
    
    // 移动文件
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return $targetPath;
    }
    
    $msg = '文件上传失败';
    $msgType = 'error';
    return null;
}

// 3. 处理请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 确定操作类型
    $action = $_POST['action'] ?? '';
    
    // --- 处理头像上传 ---
    if ($action === 'upload_portrait') {
        if (isset($_FILES['custom_portrait'])) {
            $uploadedPath = handleFileUpload('custom_portrait', $msg, $msgType);
            
            if ($uploadedPath) {
                // 存储到会话
                $_SESSION['temp_portrait'] = $uploadedPath;
                $msg = '头像上传成功！';
                $msgType = 'success';
                
                // 返回JSON响应
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => $msg,
                    'portraitPath' => $uploadedPath
                ]);
                exit;
            } else {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => $msg
                ]);
                exit;
            }
        }
    }
    
    // --- 处理头像选择 ---
    elseif ($action === 'select_portrait') {
        $selectedPortrait = $_POST['portrait'] ?? DEFAULT_PORTRAIT;
        
        // 清除会话中的临时头像
        if (isset($_SESSION['temp_portrait'])) {
            unset($_SESSION['temp_portrait']);
        }
        
        // 返回JSON响应
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'portraitPath' => $selectedPortrait
        ]);
        exit;
    }
    
    // --- 处理注册 ---
    elseif ($action === 'register') {
        // 获取并清理表单数据
        $pname = sanitizeInput($_POST['pname'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPwd = $_POST['confirmPwd'] ?? '';
        $gender = $_POST['gender'] ?? '';
        
        // 确定最终头像路径
        // 优先级：1. 会话临时头像 2. POST中的头像 3. 默认头像
        $finalPortrait = DEFAULT_PORTRAIT;
        if (isset($_SESSION['temp_portrait'])) {
            $finalPortrait = $_SESSION['temp_portrait'];
        } elseif (!empty($_POST['portrait'])) {
            $finalPortrait = $_POST['portrait'];
        }
        
        // 表单验证
        $errors = [];
        
        // 验证用户名
        $usernameValidation = validateUsername($pname);
        if ($usernameValidation !== true) {
            $errors[] = $usernameValidation;
        }
        
        // 验证密码
        $passwordValidation = validatePassword($password);
        if ($passwordValidation !== true) {
            $errors[] = $passwordValidation;
        } elseif ($password !== $confirmPwd) {
            $errors[] = '两次输入的密码不一致';
        }
        
        // 验证性别
        if (!in_array($gender, ['男', '女', '保密'])) {
            $errors[] = '请选择有效的性别';
        }
        
        // 如果有错误，显示错误
        if (!empty($errors)) {
            $msg = implode('<br>', $errors);
            $msgType = 'error';
        } else {
            // 处理注册逻辑
            $jsonFile = 'root.json';
            $users = [];
            
            if (file_exists($jsonFile)) {
                $jsonContent = file_get_contents($jsonFile);
                $users = json_decode($jsonContent, true) ?? [];
            }
            
            // 检查昵称是否已存在
            if (checkUsernameExists($pname, $users)) {
                $msg = '该昵称已被注册，请更换一个';
                $msgType = 'error';
            } else {
                // 生成新ID
                $newId = getNextUserId($users);
                
                // 注意：这里直接存储明文密码，存在安全风险！
                // 构建新用户数据
                $newUser = [
                    'id' => $newId,
                    'pname' => $pname,
                    'password' => $password, // 存储明文密码
                    'gender' => $gender,
                    'portrait' => $finalPortrait,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                // 添加新用户
                $users[] = $newUser;
                
                // 保存到JSON文件
                if (file_put_contents(
                    $jsonFile, 
                    json_encode($users, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                    LOCK_EX
                )) {
                    // 清除会话中的临时头像
                    if (isset($_SESSION['temp_portrait'])) {
                        unset($_SESSION['temp_portrait']);
                    }
                    
                    $msg = '注册成功！你的ID是 ' . $newId . '，请务必记住。';
                    $msgType = 'success';
                } else {
                    $msg = '注册失败，无法写入用户数据文件。';
                    $msgType = 'error';
                }
            }
        }
        
        // 保存表单数据用于回显
        $formData = [
            'pname' => $pname,
            'gender' => $gender,
            'portrait' => $finalPortrait
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户注册</title>
    <style>
        /* 动态背景样式 */
        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            overflow: hidden;
        }
        
        .bg-animation::before, .bg-animation::after {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 20s infinite ease-in-out;
        }
        
        .bg-animation::before {
            top: -100px;
            left: -100px;
            animation-delay: 0s;
        }
        
        .bg-animation::after {
            bottom: -150px;
            right: -150px;
            width: 400px;
            height: 400px;
            animation-delay: 5s;
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0) rotate(0deg);
            }
            50% {
                transform: translateY(-50px) rotate(10deg);
            }
        }
        
        /* 粒子效果 */
        .particle {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            animation: drift 15s infinite linear;
        }
        
        @keyframes drift {
            from {
                transform: translateY(100vh) translateX(0);
            }
            to {
                transform: translateY(-100px) translateX(calc(100vw - 100px));
            }
        }

        /* 主要样式 */
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            margin: 0; 
            padding: 15px;
            color: #333;
            overflow-x: hidden;
        }
        
        .register-container { 
            background: rgba(255, 255, 255, 0.95); 
            padding: 25px;
            border-radius: 16px; 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15); 
            width: 100%; 
            max-width: 400px; 
            backdrop-filter: blur(10px);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .register-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        }
        
        h2 { 
            text-align: center; 
            color: #4a4a8c; 
            margin: 0 0 20px 0;
            font-weight: 700;
            position: relative;
            padding-bottom: 10px;
            font-size: 22px;
        }
        
        h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 3px;
        }
        
        .msg-box { 
            padding: 8px 12px;
            border-radius: 8px; 
            margin-bottom: 15px;
            text-align: center; 
            font-weight: 500;
            animation: fadeIn 0.5s ease;
            position: relative;
            overflow: hidden;
            font-size: 14px;
        }
        
        .msg-box.success { 
            background-color: #edf7ed; 
            color: #155724; 
            border: 1px solid #d5e6d5; 
        }
        
        .msg-box.error { 
            background-color: #fef3f2; 
            color: #721c24; 
            border: 1px solid #fde6e3; 
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-group { 
            margin-bottom: 15px;
            position: relative;
        }
        
        label { 
            display: block; 
            margin-bottom: 5px;
            color: #555; 
            font-weight: 500;
            font-size: 14px;
        }
        
        input[type="text"], 
        input[type="password"] { 
            width: 100%; 
            padding: 10px 12px;
            border: 1px solid #ddd; 
            border-radius: 8px; 
            box-sizing: border-box; 
            font-size: 14px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.7);
        }
        
        input[type="text"]:focus, 
        input[type="password"]:focus { 
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
            outline: none;
            background: white;
        }
        
        .gender-options { 
            display: flex; 
            gap: 12px;
            margin-top: 5px;
        }
        
        .gender-options label { 
            display: flex; 
            align-items: center; 
            cursor: pointer;
            transition: color 0.2s ease;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 14px;
        }
        
        .gender-options label:hover {
            color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }
        
        .portrait-section { 
            margin-bottom: 15px;
        }
        
        .portrait-options { 
            display: flex; 
            flex-direction: column; 
            gap: 10px;
        }
        
        .portrait-select-btn, 
        .upload-btn { 
            padding: 8px 12px;
            background-color: #667eea; 
            color: white; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            text-align: center; 
            font-size: 13px;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .portrait-select-btn:hover, 
        .upload-btn:hover { 
            background-color: #556cd6;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
        }
        
        .portrait-preview-container { 
            display: flex; 
            align-items: center; 
            gap: 10px;
            margin-top: 8px;
        }
        
        .portrait-preview { 
            width: 50px;
            height: 50px; 
            border-radius: 50%; 
            object-fit: cover; 
            border: 2px solid #ddd;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .upload-portrait { 
            border: 2px dashed #ccc; 
            padding: 10px;
            text-align: center; 
            border-radius: 8px;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .upload-portrait:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.03);
        }
        
        .upload-portrait input[type="file"] { 
            display: none; 
        }
        
        .upload-portrait p {
            margin: 0 0 8px 0;
            font-size: 13px;
        }
        
        .upload-label { 
            display: inline-block; 
            padding: 6px 10px;
            background-color: #28a745; 
            color: white; 
            border-radius: 6px; 
            cursor: pointer; 
            font-size: 13px;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .upload-label:hover { 
            background-color: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(40, 167, 69, 0.3);
        }
        
        .submit-btn { 
            width: 100%; 
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; 
            border: none; 
            border-radius: 8px; 
            font-size: 15px;
            font-weight: 600; 
            cursor: pointer; 
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin-top: 5px;
        }
        
        .submit-btn:hover { 
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(118, 75, 162, 0.3);
        }
        
        .submit-btn:disabled { 
            background: #b3b3cc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .modal { 
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0; 
            top: 0; 
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.5); 
            justify-content: center; 
            align-items: center;
            backdrop-filter: blur(5px);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .modal.active {
            opacity: 1;
        }
        
        .modal-content { 
            background-color: #fff; 
            margin: auto; 
            padding: 20px;
            border: 1px solid #888; 
            width: 90%; 
            max-width: 600px; 
            max-height: 80vh; 
            overflow-y: auto; 
            border-radius: 12px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
            transform: translateY(30px) scale(0.95);
            transition: all 0.3s ease;
        }
        
        .modal.active .modal-content {
            transform: translateY(0) scale(1);
        }
        
        .close { 
            color: #aaa; 
            float: right; 
            font-size: 24px;
            font-weight: bold; 
            cursor: pointer;
            transition: all 0.2s ease;
            position: absolute;
            top: 15px;
            right: 15px;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .close:hover { 
            color: #dc3545;
            background: rgba(220, 53, 69, 0.1);
        }
        
        .portrait-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(60px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        
        .portrait-item { 
            width: 60px;
            height: 60px; 
            border-radius: 50%; 
            object-fit: cover; 
            cursor: pointer; 
            border: 2px solid transparent;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .portrait-item:hover { 
            transform: scale(1.1) rotate(5deg);
            border-color: #667eea;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .portrait-item.selected { 
            border-color: #667eea;
            transform: scale(1.05);
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
        }
        
        .success-message {
            text-align: center;
            margin: 15px 0;
        }
        
        .success-message .user-id {
            font-size: 24px;
            font-weight: bold;
            color: #764ba2;
            letter-spacing: 2px;
            margin: 10px 0;
            padding: 10px;
            background: rgba(118, 75, 162, 0.1);
            border-radius: 8px;
        }

        .copy-notification {
            margin-top: 10px;
            padding: 5px 10px;
            background-color: #e3f2fd;
            color: #0d47a1;
            border-radius: 4px;
            font-size: 13px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <!-- 动态背景容器 -->
    <div class="bg-animation"></div>

    <div class="register-container">
        <h2>成为十中人</h2>

        <?php if (!empty($msg)): ?>
            <div class="msg-box <?php echo $msgType; ?>"><?php echo $msg; ?></div>
        <?php endif; ?>

        <?php if ($msgType === 'success' && !empty($newId)): ?>
            <div class="success-message">
                <p>注册成功！</p>
                <p>你的ID是：</p>
                <div class="user-id" id="userId"><?php echo $newId; ?></div>
                <p style="font-size: 13px; color: #6c757d; margin-top: 10px;">
                    页面将在5秒后自动跳转...
                    <br>
                    <a href="login-service.php?new_id=<?php echo urlencode($newId); ?>" style="color: #667eea;">立即跳转</a>
                </p>
            </div>
            <script>
                // 自动复制ID到剪贴板
                document.addEventListener('DOMContentLoaded', function() {
                    const userIdElement = document.getElementById('userId');
                    const userId = userIdElement.textContent.trim();
                    
                    // 尝试复制到剪贴板
                    navigator.clipboard.writeText(userId)
                        .then(() => {
                            // 复制成功，显示提示
                            const notification = document.createElement('div');
                            notification.className = 'copy-notification';
                            notification.textContent = '✓ 用户ID已自动复制到剪贴板';
                            userIdElement.parentNode.insertBefore(notification, userIdElement.nextSibling);
                        })
                        .catch(err => {
                            console.error('无法复制文本: ', err);
                            // 复制失败时可以提供手动复制选项
                            const notification = document.createElement('div');
                            notification.className = 'copy-notification';
                            notification.innerHTML = '✗ 自动复制失败，可手动复制ID';
                            userIdElement.parentNode.insertBefore(notification, userIdElement.nextSibling);
                        });
                });

                setTimeout(() => {
                    window.location.href = 'login-service.php?new_id=<?php echo urlencode($newId); ?>';
                }, 5000);
            </script>
        <?php else: ?>
            <form id="registerForm" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="register">
                <input type="hidden" id="portraitInput" name="portrait" value="<?php echo htmlspecialchars($formData['portrait']); ?>">
                
                <div class="form-group">
                    <label for="pname">昵称</label>
                    <input type="text" id="pname" name="pname" 
                           value="<?php echo htmlspecialchars($formData['pname']); ?>" 
                           required 
                           placeholder="2-20位中文、英文、数字或下划线"
                           oninput="checkUsernameAvailability()">
                    <div id="usernameFeedback" style="font-size: 12px; margin-top: 5px;"></div>
                </div>
                
                <div class="form-group">
                    <label for="password">密码</label>
                    <input type="password" id="password" name="password" 
                           required 
                           placeholder="至少4位"
                           oninput="validatePasswordLength()">
                    <div id="passwordLengthFeedback" style="font-size: 12px; margin-top: 5px;"></div>
                </div>
                
                <div class="form-group">
                    <label for="confirmPwd">确认密码</label>
                    <input type="password" id="confirmPwd" name="confirmPwd" 
                           required 
                           placeholder="再次输入密码"
                           oninput="validatePasswordMatch()">
                    <div id="passwordMatchFeedback" style="font-size: 12px; margin-top: 5px;"></div>
                </div>
                
                <div class="form-group">
                    <label>性别</label>
                    <div class="gender-options">
                        <label>
                            <input type="radio" name="gender" value="男" 
                                   <?php echo $formData['gender'] === '男' ? 'checked' : ''; ?> required>
                            <span>男</span>
                        </label>
                        <label>
                            <input type="radio" name="gender" value="女" 
                                   <?php echo $formData['gender'] === '女' ? 'checked' : ''; ?>>
                            <span>女</span>
                        </label>
                        <label>
                            <input type="radio" name="gender" value="保密" 
                                   <?php echo $formData['gender'] === '保密' ? 'checked' : ''; ?>>
                            <span>保密</span>
                        </label>
                    </div>
                </div>

                <div class="portrait-section">
                    <label>头像</label>
                    <div class="portrait-preview-container">
                        <img id="currentPortraitPreview" class="portrait-preview" 
                             src="<?php echo htmlspecialchars($formData['portrait']); ?>" 
                             alt="头像预览">
                        <div>
                            <button type="button" class="portrait-select-btn" id="openPortraitModalBtn">从图库选择</button>
                        </div>
                    </div>
                    
                    <div class="upload-portrait">
                        <p>或上传自定义头像（小于2MB，支持JPG、PNG、GIF）</p>
                        <label for="customPortraitInput" class="upload-label">选择图片</label>
                        <input type="file" id="customPortraitInput" name="custom_portrait" accept="image/*">
                        <div id="uploadStatus" style="font-size: 12px; margin-top: 5px;"></div>
                    </div>
                </div>

                <button type="submit" class="submit-btn" id="submitBtn">注 册</button>
            </form>
            <a href="login-service.php">已有账号？去登录</a>
        <?php endif; ?>
    </div>

    <!-- 头像选择模态框 -->
    <div id="portraitModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3>选择头像</h3>
            <div class="portrait-grid" id="portraitGrid">
                <!-- 头像动态加载 -->
            </div>
        </div>
    </div>

    <script>
        // 初始化
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('portraitModal');
            const modalBtn = document.getElementById('openPortraitModalBtn');
            const closeBtn = document.querySelector('.close');
            const portraitGrid = document.getElementById('portraitGrid');
            const portraitInput = document.getElementById('portraitInput');
            const currentPreview = document.getElementById('currentPortraitPreview');
            const fileInput = document.getElementById('customPortraitInput');
            const form = document.getElementById('registerForm');
            const submitBtn = document.getElementById('submitBtn');
            const uploadStatus = document.getElementById('uploadStatus');

            // 模态框控制
            modalBtn.addEventListener('click', function() {
                modal.style.display = 'flex';
                setTimeout(() => {
                    modal.classList.add('active');
                }, 10);
                renderPortraits();
            });

            function closeModal() {
                modal.classList.remove('active');
                setTimeout(() => {
                    modal.style.display = 'none';
                }, 300);
            }
            
            closeBtn.addEventListener('click', closeModal);
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    closeModal();
                }
            });

            // 渲染头像图库
            function renderPortraits() {
                portraitGrid.innerHTML = '';
                const totalPortraits = 100;
                const selectedValue = portraitInput.value;

                for (let i = 1; i <= totalPortraits; i++) {
                    const imgSrc = `portrait-img/c${i}.jpg`;
                    const img = document.createElement('img');
                    img.src = imgSrc;
                    img.alt = `头像 ${i}`;
                    img.className = 'portrait-item' + (imgSrc === selectedValue ? ' selected' : '');
                    
                    img.addEventListener('click', function() {
                        selectSystemPortrait(imgSrc);
                    });
                    portraitGrid.appendChild(img);
                }
            }

            // 选择系统头像
            function selectSystemPortrait(imgSrc) {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=select_portrait&portrait=${encodeURIComponent(imgSrc)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        portraitInput.value = data.portraitPath;
                        currentPreview.src = data.portraitPath;
                        uploadStatus.innerHTML = '<span style="color:#28a745;">✓ 已选择系统头像</span>';
                        closeModal();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    uploadStatus.innerHTML = '<span style="color:#dc3545;">✗ 选择失败</span>';
                });
            }

            // 文件上传处理
            fileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const formData = new FormData();
                    formData.append('action', 'upload_portrait');
                    formData.append('custom_portrait', this.files[0]);

                    uploadStatus.innerHTML = '<span style="color:#666;">上传中...</span>';
                    
                    fetch('', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            currentPreview.src = data.portraitPath;
                            portraitInput.value = data.portraitPath;
                            uploadStatus.innerHTML = '<span style="color:#28a745;">✓ ' + data.message + '</span>';
                        } else {
                            uploadStatus.innerHTML = '<span style="color:#dc3545;">✗ ' + data.message + '</span>';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        uploadStatus.innerHTML = '<span style="color:#dc3545;">✗ 上传失败</span>';
                    });
                }
            });

            // 表单验证
            function validateForm() {
                const username = document.getElementById('pname').value.trim();
                const password = document.getElementById('password').value;
                const confirmPwd = document.getElementById('confirmPwd').value;
                const gender = document.querySelector('input[name="gender"]:checked');
                
                let isValid = true;
                
                if (username.length < 2 || username.length > 20) {
                    isValid = false;
                }
                
                if (password.length < 4) {
                    isValid = false;
                }
                
                if (password !== confirmPwd) {
                    isValid = false;
                }
                
                if (!gender) {
                    isValid = false;
                }
                
                submitBtn.disabled = !isValid;
                return isValid;
            }

            // 实时表单验证
            form.addEventListener('input', validateForm);
            
            // 表单提交处理
            form.addEventListener('submit', function(e) {
                if (!validateForm()) {
                    e.preventDefault();
                    alert('请正确填写所有字段');
                    return;
                }
                
                submitBtn.disabled = true;
                submitBtn.textContent = '注册中...';
            });
        });

        // 检查用户名可用性
        function checkUsernameAvailability() {
            const username = document.getElementById('pname').value.trim();
            const feedback = document.getElementById('usernameFeedback');
            
            if (username.length < 2 || username.length > 20) {
                feedback.innerHTML = '<span style="color:#dc3545;">✗ 昵称长度需在2-20位</span>';
                return;
            }
            
            if (!/^[\u4e00-\u9fa5a-zA-Z0-9_]+$/.test(username)) {
                feedback.innerHTML = '<span style="color:#dc3545;">✗ 只能包含中文、英文、数字和下划线</span>';
                return;
            }
            
            feedback.innerHTML = '<span style="color:#28a745;">✓ 格式正确</span>';
        }

        // 验证密码长度
        function validatePasswordLength() {
            const password = document.getElementById('password').value;
            const feedback = document.getElementById('passwordLengthFeedback');
            
            if (password === '') {
                feedback.innerHTML = '';
                return;
            }
            
            if (password.length < 4) {
                feedback.innerHTML = '<span style="color:#dc3545;">✗ 密码至少需要4位</span>';
            } else {
                feedback.innerHTML = '<span style="color:#28a745;">✓ 密码长度符合要求</span>';
            }
        }

        // 验证密码匹配
        function validatePasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPwd = document.getElementById('confirmPwd').value;
            const feedback = document.getElementById('passwordMatchFeedback');
            
            if (confirmPwd === '') {
                feedback.innerHTML = '';
                return;
            }
            
            if (password === confirmPwd) {
                feedback.innerHTML = '<span style="color:#28a745;">✓ 密码匹配</span>';
            } else {
                feedback.innerHTML = '<span style="color:#dc3545;">✗ 密码不匹配</span>';
            }
        }
    </script>
</body>
</html>