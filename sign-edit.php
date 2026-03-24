<?php
// 定义JSON文件路径
$jsonFile = __DIR__ . '/notice-content.json';

// ========== 优先读取JSON文件，增加异常处理 ==========
// 初始化内容变量
$currentContent = [];

// 第一步：尝试读取JSON文件（最高优先级）
try {
    // 强制读取最新文件内容，禁用缓存
    $jsonContent = file_get_contents($jsonFile, false, stream_context_create([
        'http' => [
            'header' => 'Cache-Control: no-cache'
        ]
    ]));
    
    // 验证JSON格式是否有效
    if ($jsonContent === false) {
        throw new Exception('无法读取JSON文件');
    }
    
    $decodedContent = json_decode($jsonContent, true);
    
    // 验证解码结果是否为有效数组
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decodedContent)) {
        throw new Exception('JSON文件格式错误或内容无效');
    }
    
    // 验证必要字段是否存在，缺失则用默认值补充
    $currentContent = [
        'title' => isset($decodedContent['title']) ? trim($decodedContent['title']) : '📱 每日提醒',
        'content' => isset($decodedContent['content']) ? trim($decodedContent['content']) : '今日首次访问的专属提示',
        'subContent' => isset($decodedContent['subContent']) ? trim($decodedContent['subContent']) : '明天再次访问会重新显示哦～'
    ];
    
} catch (Exception $e) {
    // 读取失败时：1. 记录错误 2. 初始化默认JSON文件 3. 使用默认内容
    error_log('读取JSON文件失败：' . $e->getMessage());
    
    // 重新生成合法的JSON文件
    $defaultContent = [
        'title' => '📱 iOS风格每日提醒',
        'content' => '这是你今日首次访问的专属提示',
        'subContent' => '明天再次访问会重新显示哦～'
    ];
    file_put_contents($jsonFile, json_encode($defaultContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // 使用默认内容
    $currentContent = $defaultContent;
}

// 处理保存请求（保存后会立即重新读取最新内容）
$message = '';
$messageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取表单数据
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $subContent = trim($_POST['subContent'] ?? '');
    
    // 验证必填项
    if (empty($title) || empty($content)) {
        $message = '标题和正文不能为空！';
        $messageType = 'error';
    } else {
        // 组装内容并保存到JSON
        $newContent = [
            'title' => $title,
            'content' => $content,
            'subContent' => $subContent
        ];
        
        // 保存时增加写入验证
        $saveResult = file_put_contents($jsonFile, json_encode($newContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        if ($saveResult === false) {
            $message = '保存失败！请检查JSON文件写入权限';
            $messageType = 'error';
        } else {
            // 保存成功后，立即重新读取JSON文件（确保页面显示最新内容）
            $currentContent = $newContent;
            $message = '内容保存成功！所有访问者都会看到新内容～';
            $messageType = 'success';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/a.svg">
    <title>编辑通知内容 - iOS风格</title>
    <style>
        /* 重置默认样式 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* 页面基础样式 - 适配深浅色模式 */
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            padding: 32px 20px;
            min-height: 100vh;
            transition: background-color 0.3s ease;
        }

        /* 浅色模式（默认） */
        body {
            background-color: #f5f5f7;
            color: #1d1d1f;
        }

        /* 深色模式适配 */
        @media (prefers-color-scheme: dark) {
            body {
                background-color: #1c1c1e;
                color: #f5f5f7;
            }
        }

        /* iOS液态玻璃编辑容器 - 核心样式 */
        .editor-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 32px 28px;
            border-radius: 24px; /* iOS大圆角 */
            
            /* 液态玻璃核心效果 */
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.18);
            
            /* iOS风格阴影 */
            box-shadow: 
                0 1px 2px rgba(0, 0, 0, 0.05),
                0 4px 12px rgba(0, 0, 0, 0.08);
            
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        /* 深色模式玻璃效果 */
        @media (prefers-color-scheme: dark) {
            .editor-container {
                background: rgba(30, 30, 40, 0.7);
                border: 1px solid rgba(60, 60, 60, 0.18);
                box-shadow: 
                    0 1px 2px rgba(0, 0, 0, 0.15),
                    0 4px 12px rgba(0, 0, 0, 0.2);
            }
        }

        /* 标题样式 */
        h2 {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 24px;
            color: #1d1d1f;
        }

        /* 深色模式标题 */
        @media (prefers-color-scheme: dark) {
            h2 {
                color: #f5f5f7;
            }
        }

        /* 提示消息 */
        .message {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .message.success {
            background: rgba(46, 125, 50, 0.1);
            color: #2e7d32;
            border: 1px solid rgba(46, 125, 50, 0.2);
        }
        .message.error {
            background: rgba(198, 40, 40, 0.1);
            color: #c62828;
            border: 1px solid rgba(198, 40, 40, 0.2);
        }
        .message.warning {
            background: rgba(251, 192, 45, 0.1);
            color: #f9a825;
            border: 1px solid rgba(251, 192, 45, 0.2);
        }

        /* 深色模式消息适配 */
        @media (prefers-color-scheme: dark) {
            .message.success {
                background: rgba(76, 175, 80, 0.15);
                color: #4caf50;
                border-color: rgba(76, 175, 80, 0.3);
            }
            .message.error {
                background: rgba(244, 67, 54, 0.15);
                color: #f44336;
                border-color: rgba(244, 67, 54, 0.3);
            }
            .message.warning {
                background: rgba(255, 193, 7, 0.15);
                color: #ffc107;
                border-color: rgba(255, 193, 7, 0.3);
            }
        }

        /* 表单组 */
        .form-group {
            margin-bottom: 20px;
        }

        /* 标签样式 */
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 15px;
            color: #1d1d1f;
        }
        @media (prefers-color-scheme: dark) {
            label {
                color: #f5f5f7;
            }
        }

        /* 输入框/文本域样式 - iOS风格 */
        input, textarea {
            width: 100%;
            padding: 16px 18px;
            border-radius: 14px;
            font-size: 16px;
            font-family: inherit;
            transition: all 0.2s ease;
            
            /* iOS输入框玻璃效果 */
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(200, 200, 200, 0.2);
            color: #1d1d1f;
        }

        /* 深色模式输入框 */
        @media (prefers-color-scheme: dark) {
            input, textarea {
                background: rgba(40, 40, 40, 0.6);
                border-color: rgba(80, 80, 80, 0.2);
                color: #f5f5f7;
                caret-color: #0071e3;
            }
            input::placeholder, textarea::placeholder {
                color: rgba(245, 245, 247, 0.5);
            }
        }

        /* 输入框聚焦效果 */
        input:focus, textarea:focus {
            outline: none;
            border-color: #0071e3;
            box-shadow: 0 0 0 4px rgba(0, 113, 227, 0.1);
        }

        textarea {
            min-height: 120px;
            resize: vertical;
            line-height: 1.6;
        }

        /* 保存按钮 - iOS风格 */
        button {
            background: #0071e3;
            color: white;
            border: none;
            padding: 16px 32px;
            border-radius: 14px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            width: 100%;
            margin-bottom: 24px;
        }
        button:hover {
            background: #0077ed;
            transform: translateY(-1px);
        }
        button:active {
            transform: translateY(0);
            background: #0066cc;
        }

        /* 预览区域 - 液态玻璃效果 */
        .preview {
            margin-top: 0;
            padding: 24px 20px;
            border-radius: 18px;
            
            /* 预览区玻璃效果 */
            background: rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(200, 200, 200, 0.1);
        }

        /* 深色模式预览区 */
        @media (prefers-color-scheme: dark) {
            .preview {
                background: rgba(50, 50, 50, 0.4);
                border-color: rgba(80, 80, 80, 0.1);
            }
        }

        /* 预览文本样式 */
        .preview h3 {
            font-size: 19px;
            margin-bottom: 8px;
            font-weight: 600;
            color: #1d1d1f;
        }
        .preview p {
            font-size: 15px;
            color: #666;
            line-height: 1.6;
        }

        /* 深色模式预览文本 */
        @media (prefers-color-scheme: dark) {
            .preview h3 {
                color: #f5f5f7;
            }
            .preview p {
                color: rgba(245, 245, 247, 0.8);
            }
        }

        /* 提示文本 */
        .tip {
            margin-top: 20px;
            font-size: 14px;
            color: #888;
            text-align: center;
            line-height: 1.5;
        }
        @media (prefers-color-scheme: dark) {
            .tip {
                color: rgba(245, 245, 247, 0.6);
            }
        }

        /* 响应式适配 */
        @media (max-width: 768px) {
            .editor-container {
                padding: 24px 20px;
                border-radius: 20px;
            }
            h2 {
                font-size: 20px;
                margin-bottom: 20px;
            }
            input, textarea {
                padding: 14px 16px;
                font-size: 15px;
            }
            button {
                padding: 14px 24px;
                font-size: 15px;
            }
            .preview {
                padding: 20px 16px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 20px 16px;
            }
            .editor-container {
                padding: 20px 16px;
                border-radius: 16px;
            }
            .message {
                padding: 14px 16px;
                font-size: 14px;
            }
            .form-group {
                margin-bottom: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="editor-container">
        <h2>编辑通知内容（iOS风格）</h2>
        
        <!-- 新增：JSON读取状态提示 -->
        <?php if (!file_exists($jsonFile)): ?>
            <div class="message warning">
                提示：未找到JSON文件，已自动创建默认内容文件
            </div>
        <?php elseif (isset($e)): ?>
            <div class="message error">
                警告：JSON文件读取失败，已使用默认内容（错误：<?php echo htmlspecialchars($e->getMessage()); ?>）
            </div>
        <?php endif; ?>
        
        <!-- 原有提示消息 -->
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- 编辑表单 -->
        <form method="POST">
            <div class="form-group">
                <label for="title">通知标题</label>
                <input type="text" id="title" name="title" 
                       value="<?php echo htmlspecialchars($currentContent['title']); ?>" 
                       placeholder="例如：📱 iOS风格每日提醒">
            </div>
            
            <div class="form-group">
                <label for="content">通知正文</label>
                <textarea id="content" name="content" placeholder="例如：这是你今日首次访问的专属提示" style="white-space: pre-wrap;">
                    <?php echo htmlspecialchars($currentContent['content']); ?>
                </textarea>
            </div>
            
            <div class="form-group">
                <label for="subContent">补充说明（可选）</label>
                <input type="text" id="subContent" name="subContent" 
                       value="<?php echo htmlspecialchars($currentContent['subContent']); ?>" 
                       placeholder="例如：明天再次访问会重新显示哦～">
            </div>
            
            <button type="submit">保存</button>
        </form>
        
        <!-- 预览区域 -->
        <div class="preview">
            <h3><?php echo htmlspecialchars($currentContent['title']); ?></h3>
            <p><?php echo htmlspecialchars($currentContent['content']); ?></p>
            <?php if (!empty($currentContent['subContent'])): ?>
                <p style="margin-top: 8px; font-size: 14px;"><?php echo htmlspecialchars($currentContent['subContent']); ?></p>
            <?php endif; ?>
        </div>
        
        <p class="tip">提示：可以设置密码</p>
    </div>
</body>
</html>