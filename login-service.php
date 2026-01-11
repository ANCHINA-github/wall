<?php
// ============== PHP 优化部分 ==============

// 开启错误显示（开发环境）
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 调整PHP配置以适应大文件上传
ini_set('upload_max_filesize', '10M');
ini_set('post_max_size', '20M');
ini_set('max_execution_time', 300);
ini_set('max_input_time', 300);
ini_set('memory_limit', '128M');

date_default_timezone_set('Asia/Shanghai');

// 定义缓存文件路径
$cacheDir = 'cache';
$rootJsonPath = 'root.json';
$postsJsonPath = 'posts.json';
$postsCachePath = $cacheDir . '/posts_cache.json';
$cacheDuration = 60; // 缓存时间（秒）

// 创建缓存目录
if (!file_exists($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

// 确保JSON文件存在，不存在则创建并初始化
if (!file_exists($rootJsonPath)) {
    file_put_contents($rootJsonPath, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
if (!file_exists($postsJsonPath)) {
    file_put_contents($postsJsonPath, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// 创建img文件夹
$imgDir = 'img';
if (!file_exists($imgDir)) {
    mkdir($imgDir, 0755, true);
}

// 获取服务器上传限制信息（缓存结果）
function getUploadLimits() {
    static $limits = null;
    
    if ($limits === null) {
        $limits = [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_file_uploads' => ini_get('max_file_uploads'),
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit')
        ];
        
        // 将人类可读的大小转换为字节
        $limits['max_single_bytes'] = toBytes($limits['upload_max_filesize']);
        $limits['max_total_bytes'] = toBytes($limits['post_max_size']);
    }
    
    return $limits;
}

function toBytes($size) {
    $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
    $size = preg_replace('/[^0-9\.]/', '', $size);
    if ($unit) {
        return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
    } else {
        return round($size);
    }
}

// 获取缓存的帖子数据（添加分页参数）
function getCachedPostsData($cachePath, $sourcePath, $cacheDuration, $page = 1, $perPage = 10) {
    // 如果缓存存在且未过期，直接使用缓存
    if (file_exists($cachePath) && 
        (time() - filemtime($cachePath)) < $cacheDuration) {
        $cachedData = file_get_contents($cachePath);
        $allPostsData = json_decode($cachedData, true);
    } else {
        // 重新读取并缓存数据
        $sourceData = file_get_contents($sourcePath);
        $allPostsData = json_decode($sourceData, true);
        
        if ($allPostsData !== null) {
            // 按时间倒序排序
            usort($allPostsData, function($a, $b) {
                return strtotime($b['pdate']) - strtotime($a['pdate']);
            });
            
            // 写入缓存
            file_put_contents($cachePath, json_encode($allPostsData, JSON_UNESCAPED_UNICODE));
        } else {
            $allPostsData = [];
        }
    }
    
    // 分页处理
    $totalPosts = count($allPostsData);
    $totalPages = ceil($totalPosts / $perPage);
    $startIndex = ($page - 1) * $perPage;
    $endIndex = min($startIndex + $perPage, $totalPosts);
    
    // 获取当前页的数据
    $currentPageData = array_slice($allPostsData, $startIndex, $perPage);
    
    return [
        'data' => $currentPageData,
        'total' => $totalPosts,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => $totalPages,
        'has_more' => $page < $totalPages
    ];
}

// 新增：获取特定页数的帖子数据
function getPostsByPage($page = 1, $perPage = 10, $filter = 'latest') {
    global $postsCachePath, $postsJsonPath, $cacheDuration;
    
    // 获取所有数据
    if (file_exists($postsCachePath) && 
        (time() - filemtime($postsCachePath)) < $cacheDuration) {
        $cachedData = file_get_contents($postsCachePath);
        $allPostsData = json_decode($cachedData, true);
    } else {
        $sourceData = file_get_contents($postsJsonPath);
        $allPostsData = json_decode($sourceData, true);
        
        if ($allPostsData !== null) {
            usort($allPostsData, function($a, $b) {
                return strtotime($b['pdate']) - strtotime($a['pdate']);
            });
            
            file_put_contents($postsCachePath, json_encode($allPostsData, JSON_UNESCAPED_UNICODE));
        } else {
            $allPostsData = [];
        }
    }
    
    // 根据筛选条件过滤数据
    if ($filter === 'love') {
        $allPostsData = array_filter($allPostsData, function($post) {
            return strpos($post['content'] ?? '', '#表白#') !== false;
        });
        $allPostsData = array_values($allPostsData); // 重新索引数组
    } elseif ($filter === 'gossip') {
        $allPostsData = array_filter($allPostsData, function($post) {
            return strpos($post['content'] ?? '', '#八卦#') !== false;
        });
        $allPostsData = array_values($allPostsData); // 重新索引数组
    }
    
    // 分页处理
    $totalPosts = count($allPostsData);
    $totalPages = ceil($totalPosts / $perPage);
    $startIndex = ($page - 1) * $perPage;
    
    // 确保起始索引有效
    if ($startIndex >= $totalPosts) {
        return [
            'data' => [],
            'total' => $totalPosts,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
            'has_more' => false
        ];
    }
    
    $currentPageData = array_slice($allPostsData, $startIndex, $perPage);
    
    return [
        'data' => $currentPageData,
        'total' => $totalPosts,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => $totalPages,
        'has_more' => $page < $totalPages
    ];
}

// 后端处理逻辑：登录验证
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $id = trim($_POST['id']);
    $password = trim($_POST['password']);
    
    // 使用文件锁避免并发读取
    $rootData = json_decode(file_get_contents($rootJsonPath), true);
    $userFound = false;
    $userInfo = [];
    
    foreach ($rootData as $user) {
        if (isset($user['id']) && $user['id'] === $id && isset($user['password']) && $user['password'] === $password) {
            $userFound = true;
            $userInfo = [
                'id' => $user['id'],
                'pname' => $user['pname'],
                'gender' => $user['gender'],
                'portrait' => $user['portrait']
            ];
            break;
        }
    }
    
    if ($userFound) {
        echo json_encode(['status' => 'success', 'data' => $userInfo]);
    } else {
        echo json_encode(['status' => 'error', 'msg' => '账号或密码错误']);
    }
    exit;
}

// 新增：获取帖子列表（分页）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_posts') {
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $perPage = isset($_POST['per_page']) ? intval($_POST['per_page']) : 10;
    $filter = isset($_POST['filter']) ? $_POST['filter'] : 'latest';
    
    $result = getPostsByPage($page, $perPage, $filter);
    
    // 处理HTML输出
    ob_start();
    
    if (!empty($result['data'])):
        $today = date('Y-m-d');
        foreach ($result['data'] as $post):
            $device = isset($post['device']) ? $post['device'] : '';
            // 处理关键词高亮
            $content = htmlspecialchars($post['content']);
            // 匹配井号包围的关键词
            $content = preg_replace('/#([^#]+)#/', '<span class="keyword" data-keyword="$1">#$1#</span>', $content);
            
            // 判断内容是否需要折叠
            $needsCollapse = mb_strlen($post['content'], 'UTF-8') > 100;
            $contentClass = $needsCollapse ? 'collapsed' : '';
            
            // 获取评论
            $comments = isset($post['comments']) ? $post['comments'] : [];
            $commentCount = count($comments);
            $showExpand = $commentCount > 3;
            $commentsToShow = $showExpand ? array_slice($comments, 0, 3) : $comments;
            
            // 判断帖子标记
            $isToday = (date('Y-m-d', strtotime($post['pdate'])) == $today);
            $hasSpark = ($post['plikes'] >= 18);
            $isAuth = (strpos($post['pname'], '管理员') !== false || strpos($post['pname'], '墙') !== false);
    ?>
    <div class="post-card" data-pid="<?php echo $post['pid']; ?>" data-likes="<?php echo $post['plikes']; ?>" data-content="<?php echo htmlspecialchars($post['content']); ?>" data-pname="<?php echo htmlspecialchars($post['pname']); ?>" data-portrait="<?php echo $post['portrait']; ?>" data-pdate="<?php echo $post['pdate']; ?>" data-device="<?php echo $device; ?>">
        <div class="post-header">
            <img src="<?php echo $post['portrait'] ?: 'default-avatar.png'; ?>" alt="用户头像" class="post-avatar">
            <div class="post-user-info">
                <div class="post-username">
                    <?php echo $post['pname']; ?>
                    <?php if ($isAuth): ?>
                        <span class="post-badge badge-auth">
                            <i class="fas fa-check-circle"></i> 权威认证
                        </span>
                    <?php endif; ?>
                </div>
                <div class="post-date">
                    <?php echo $post['pdate']; ?>
                    <?php if (!empty($device)): ?>
                        <span class="post-device"><?php echo $device; ?></span>
                    <?php endif; ?>
                </div>
                <div class="post-badges">
                    <?php if ($hasSpark): ?>
                        <span class="post-badge badge-spark">
                            <i class="fas fa-fire"></i>
                        </span>
                    <?php endif; ?>
                    <?php if ($isToday): ?>
                        <span class="post-badge badge-today">
                            <i class="fas fa-calendar-day"></i> 今日发布
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="post-content <?php echo $contentClass; ?>" id="postContent_<?php echo $post['pid']; ?>">
            <?php echo nl2br($content); ?>
            <?php if ($needsCollapse): ?>
                <div class="fade-out"></div>
            <?php endif; ?>
        </div>
        <?php if ($needsCollapse): ?>
            <button class="expand-content-btn" data-pid="<?php echo $post['pid']; ?>">展开全部</button>
        <?php endif; ?>
        
        <!-- 帖子图片展示区域 -->
        <?php if (!empty($post['images'])): 
            $imageCount = count($post['images']);
            $imageClass = $imageCount === 1 ? 'single' : 'multiple';
        ?>
            <div class="post-images-container">
                <?php foreach ($post['images'] as $index => $image): ?>
                    <div class="post-image-item <?php echo $imageClass; ?>" data-image-src="<?php echo $image; ?>" data-index="<?php echo $index; ?>">
                        <img src="<?php echo $image; ?>" alt="帖子图片" class="post-image">
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- 新增：伪输入框 -->
        <div class="fake-input" data-pid="<?php echo $post['pid']; ?>">
            <i class="far fa-comment"></i>
            <span>写下你的评论...</span>
        </div>
        
        <div class="post-actions">
            <button class="action-btn post-like-btn" data-type="post" data-id="<?php echo $post['pid']; ?>">
                <i class="far fa-heart action-icon"></i>
                <span class="like-count"><?php echo $post['plikes']; ?></span> 
            </button>
            <button class="action-btn post-comment-btn" data-pid="<?php echo $post['pid']; ?>">
                <i class="far fa-comment action-icon"></i>
                <span class="comment-count"><?php echo $commentCount; ?></span>
            </button>
        </div>
        
        <!-- 评论区域 -->
        <?php if ($commentCount > 0): ?>
            <div class="comments-container">
                <div class="comments-list <?php echo $showExpand ? 'collapsed' : ''; ?>" id="comments_<?php echo $post['pid']; ?>">
                    <?php foreach ($commentsToShow as $comment): 
                        $comDevice = isset($comment['com_device']) ? $comment['com_device'] : '';
                        $comImages = isset($comment['com_images']) ? $comment['com_images'] : [];
                    ?>
                    <div class="comment-item" data-cid="<?php echo $comment['com_cid']; ?>">
                        <img src="<?php echo $comment['com_portrait'] ?: 'default-avatar.png'; ?>" alt="评论用户头像" class="comment-avatar">
                        <div class="comment-content-wrap">
                            <div class="comment-header">
                                <div class="comment-username"><?php echo $comment['com_pname']; ?></div>
                                <div class="comment-date">
                                    <?php echo $comment['com_date']; ?>
                                    <?php if (!empty($comDevice)): ?>
                                        <span class="comment-device"><?php echo $comDevice; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="comment-content">
                                <?php 
                                    $comContent = htmlspecialchars($comment['com_content']);
                                    // 匹配井号包围的关键词
                                    $comContent = preg_replace('/#([^#]+)#/', '<span class="keyword" data-keyword="$1">#$1#</span>', $comContent);
                                    echo nl2br($comContent);
                                ?>
                            </div>
                            
                            <!-- 评论图片展示区域 -->
                            <?php if (!empty($comImages)): ?>
                                <div class="comment-images-container">
                                    <?php foreach ($comImages as $comImage): ?>
                                        <div class="comment-image-item" data-image-src="<?php echo $comImage; ?>">
                                            <img src="<?php echo $comImage; ?>" alt="评论图片" class="comment-image">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="comment-actions">
                                <button class="comment-like-btn" data-type="comment" data-id="<?php echo $comment['com_cid']; ?>">
                                    <i class="far fa-heart"></i>
                                    <span class="comment-like-count"><?php echo $comment['clikes']; ?></span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($showExpand): ?>
                    <button class="expand-comments-btn" data-pid="<?php echo $post['pid']; ?>" data-loaded="false">
                        展开剩余评论
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php 
        endforeach;
    else:
        if ($page === 1):
    ?>
        <div style="text-align: center; padding: 20px; color: #999999;">暂无帖子，快来发布第一条动态吧~</div>
    <?php
        endif;
    endif;
    
    $html = ob_get_clean();
    
    $result['html'] = $html;
    echo json_encode($result);
    exit;
}

// 文件上传验证函数
function validateUploadedFiles($files) {
    $errors = [];
    $totalSize = 0;
    $fileCount = 0;
    
    $maxFiles = 3;
    $maxSingleSize = 3 * 1024 * 1024;
    $maxTotalSize = 6 * 1024 * 1024;
    
    // 允许的文件类型
    $allowedMimeTypes = [
        'image/jpeg',
        'image/jpg', 
        'image/png',
        'image/gif',
        'image/webp',
        'image/bmp'
    ];
    
    if (empty($files['name'][0])) {
        return ['success' => true, 'files' => []];
    }
    
    // 检查文件数量
    $fileCount = count(array_filter($files['name']));
    if ($fileCount > $maxFiles) {
        return ['success' => false, 'msg' => "最多只能上传{$maxFiles}张图片"];
    }
    
    for ($i = 0; $i < $fileCount; $i++) {
        $fileName = $files['name'][$i];
        $fileSize = $files['size'][$i];
        $fileTmp = $files['tmp_name'][$i];
        $fileError = $files['error'][$i];
        
        // 检查上传错误
        if ($fileError !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE   => "图片 '{$fileName}' 超过服务器限制大小（3MB）",
                UPLOAD_ERR_FORM_SIZE  => "图片 '{$fileName}' 超过表单限制大小",
                UPLOAD_ERR_PARTIAL    => "图片 '{$fileName}' 只有部分被上传",
                UPLOAD_ERR_NO_FILE    => "没有选择图片文件",
                UPLOAD_ERR_NO_TMP_DIR => "服务器临时文件夹不存在",
                UPLOAD_ERR_CANT_WRITE => "无法写入服务器磁盘",
                UPLOAD_ERR_EXTENSION  => "图片上传被PHP扩展阻止"
            ];
            
            $errorMsg = isset($errorMessages[$fileError]) 
                ? $errorMessages[$fileError] 
                : "图片 '{$fileName}' 上传失败 (错误代码: {$fileError})";
            
            return ['success' => false, 'msg' => $errorMsg];
        }
        
        // 检查文件大小
        if ($fileSize > $maxSingleSize) {
            return ['success' => false, 'msg' => "图片 '{$fileName}' 过大！每张图片不能超过3MB"];
        }
        
        // 验证文件是否真的是图片
        if (!is_uploaded_file($fileTmp)) {
            return ['success' => false, 'msg' => "图片 '{$fileName}' 上传验证失败"];
        }
        
        // 获取MIME类型
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fileTmp);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedMimeTypes)) {
            return ['success' => false, 'msg' => "图片 '{$fileName}' 格式不支持。只支持 JPG, PNG, GIF, WEBP, BMP 格式"];
        }
        
        // 检查图片是否有效
        $imageInfo = @getimagesize($fileTmp);
        if ($imageInfo === false) {
            return ['success' => false, 'msg' => "图片 '{$fileName}' 不是有效的图片文件"];
        }
        
        $totalSize += $fileSize;
    }
    
    // 检查总文件大小
    if ($totalSize > $maxTotalSize) {
        return ['success' => false, 'msg' => "请减少图片数量或压缩图片"];
    }
    
    return ['success' => true, 'fileCount' => $fileCount];
}

// 后端处理逻辑：发布帖子（包含图片上传）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'publish_post') {
    $content = trim($_POST['content']);
    $pname = trim($_POST['pname']);
    $portrait = trim($_POST['portrait']);
    $device = isset($_POST['device']) && $_POST['device'] === 'show' ? $_POST['device_name'] : '';
    
    if (empty($content)) {
        echo json_encode(['status' => 'error', 'msg' => '帖子内容不能为空!']);
        exit;
    }
    
    // 验证上传的文件
    $fileValidation = validateUploadedFiles($_FILES['post_images'] ?? []);
    if (!$fileValidation['success']) {
        echo json_encode(['status' => 'error', 'msg' => $fileValidation['msg']]);
        exit;
    }
    
    // 读取现有数据
    $postsData = json_decode(file_get_contents($postsJsonPath), true);
    
    // 生成6位pid
    $maxPid = 0;
    foreach ($postsData as $post) {
        if (isset($post['pid']) && (int)$post['pid'] > $maxPid) {
            $maxPid = (int)$post['pid'];
        }
    }
    $newPid = str_pad($maxPid + 1, 6, '0', STR_PAD_LEFT);
    $pdate = date('Y-m-d H:i:s');
    
    // 处理图片上传
    $uploadedImages = [];
    if (isset($_FILES['post_images']) && !empty($_FILES['post_images']['name'][0])) {
        $maxFiles = 3;
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
        
        for ($i = 0; $i < min($maxFiles, count($_FILES['post_images']['name'])); $i++) {
            if ($_FILES['post_images']['error'][$i] === UPLOAD_ERR_OK) {
                // 验证文件类型
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $fileType = finfo_file($finfo, $_FILES['post_images']['tmp_name'][$i]);
                finfo_close($finfo);
                
                if (!in_array($fileType, $allowedTypes)) {
                    continue;
                }
                
                // 生成唯一文件名
                $extension = strtolower(pathinfo($_FILES['post_images']['name'][$i], PATHINFO_EXTENSION));
                $fileName = 'post_' . $newPid . '_' . time() . '_' . uniqid() . '.' . $extension;
                $filePath = $imgDir . '/' . $fileName;
                
                // 移动文件到img文件夹
                if (move_uploaded_file($_FILES['post_images']['tmp_name'][$i], $filePath)) {
                    // 异步压缩图片（如果超过1MB）
                    if (filesize($filePath) > 1024 * 1024) {
                        // 记录需要压缩的文件，稍后处理
                        $compressQueue[] = $filePath;
                    }
                    $uploadedImages[] = $filePath;
                }
            }
        }
    }
    
    // 新帖子数据
    $newPost = [
        'pname' => $pname,
        'portrait' => $portrait,
        'content' => $content,
        'pdate' => $pdate,
        'pid' => $newPid,
        'plikes' => 0,
        'images' => $uploadedImages,
        'comments' => [],
        'device' => $device
    ];
    
    // 添加到帖子列表头部
    array_unshift($postsData, $newPost);
    
    // 保存数据
    $result = file_put_contents($postsJsonPath, json_encode($postsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // 清除缓存
    if (file_exists($postsCachePath)) {
        unlink($postsCachePath);
    }
    
    if ($result) {
        echo json_encode(['status' => 'success', 'msg' => '发布成功', 'pid' => $newPid]);
    } else {
        // 如果保存失败，删除已上传的图片
        foreach ($uploadedImages as $imagePath) {
            if (file_exists($imagePath)) {
                @unlink($imagePath);
            }
        }
        echo json_encode(['status' => 'error', 'msg' => '发布失败，请稍后重试']);
    }
    exit;
}

// 后端处理逻辑：发布评论（包含图片上传）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'publish_comment') {
    $comContent = trim($_POST['com_content']);
    $pid = trim($_POST['pid']);
    $pname = trim($_POST['pname']);
    $portrait = trim($_POST['portrait']);
    $device = isset($_POST['device']) && $_POST['device'] === 'show' ? $_POST['device_name'] : '';
    
    if (empty($comContent) || empty($pid)) {
        echo json_encode(['status' => 'error', 'msg' => '评论内容不能为空']);
        exit;
    }
    
    // 验证上传的文件
    $fileValidation = validateUploadedFiles($_FILES['comment_images'] ?? []);
    if (!$fileValidation['success']) {
        echo json_encode(['status' => 'error', 'msg' => $fileValidation['msg']]);
        exit;
    }
    
    $postsData = json_decode(file_get_contents($postsJsonPath), true);
    $postIndex = -1;
    $maxCid = 0;
    
    // 找到对应帖子，并获取全局最大com-cid
    foreach ($postsData as $index => $post) {
        if (isset($post['pid']) && $post['pid'] === $pid) {
            $postIndex = $index;
        }
        if (isset($post['comments']) && is_array($post['comments'])) {
            foreach ($post['comments'] as $comment) {
                if (isset($comment['com_cid']) && (int)$comment['com_cid'] > $maxCid) {
                    $maxCid = (int)$comment['com_cid'];
                }
            }
        }
    }
    
    if ($postIndex === -1) {
        echo json_encode(['status' => 'error', 'msg' => '未找到对应帖子']);
        exit;
    }
    
    // 生成7位com_cid
    $newCid = str_pad($maxCid + 1, 7, '0', STR_PAD_LEFT);
    $comDate = date('Y-m-d H:i:s');
    
    // 处理评论图片上传
    $uploadedCommentImages = [];
    if (isset($_FILES['comment_images']) && !empty($_FILES['comment_images']['name'][0])) {
        $maxFiles = 3;
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
        
        for ($i = 0; $i < min($maxFiles, count($_FILES['comment_images']['name'])); $i++) {
            if ($_FILES['comment_images']['error'][$i] === UPLOAD_ERR_OK) {
                // 验证文件类型
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $fileType = finfo_file($finfo, $_FILES['comment_images']['tmp_name'][$i]);
                finfo_close($finfo);
                
                if (!in_array($fileType, $allowedTypes)) {
                    continue;
                }
                
                // 生成唯一文件名
                $extension = strtolower(pathinfo($_FILES['comment_images']['name'][$i], PATHINFO_EXTENSION));
                $fileName = 'comment_' . $pid . '_' . $newCid . '_' . time() . '_' . uniqid() . '.' . $extension;
                $filePath = $imgDir . '/' . $fileName;
                
                // 移动文件到img文件夹
                if (move_uploaded_file($_FILES['comment_images']['tmp_name'][$i], $filePath)) {
                    $uploadedCommentImages[] = $filePath;
                }
            }
        }
    }
    
    // 新评论数据
    $newComment = [
        'com_pname' => $pname,
        'com_portrait' => $portrait,
        'com_content' => $comContent,
        'com_date' => $comDate,
        'com_cid' => $newCid,
        'clikes' => 0,
        'com_images' => $uploadedCommentImages,
        'com_device' => $device
    ];
    
    // 添加到评论列表头部
    if (!isset($postsData[$postIndex]['comments']) || !is_array($postsData[$postIndex]['comments'])) {
        $postsData[$postIndex]['comments'] = [];
    }
    array_unshift($postsData[$postIndex]['comments'], $newComment);
    
    // 保存数据
    $result = file_put_contents($postsJsonPath, json_encode($postsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // 清除缓存
    if (file_exists($postsCachePath)) {
        unlink($postsCachePath);
    }
    
    if ($result) {
        echo json_encode(['status' => 'success', 'msg' => '评论成功']);
    } else {
        // 如果保存失败，删除已上传的图片
        foreach ($uploadedCommentImages as $imagePath) {
            if (file_exists($imagePath)) {
                @unlink($imagePath);
            }
        }
        echo json_encode(['status' => 'error', 'msg' => '评论失败，请稍后重试']);
    }
    exit;
}

// 后端处理逻辑：点赞
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'like') {
    $type = trim($_POST['type']);
    $id = trim($_POST['id']);
    
    if (empty($type) || empty($id)) {
        echo json_encode(['status' => 'error', 'msg' => '点赞参数错误']);
        exit;
    }
    
    $postsData = json_decode(file_get_contents($postsJsonPath), true);
    $updated = false;
    
    if ($type === 'post') {
        // 帖子点赞
        foreach ($postsData as &$post) {
            if (isset($post['pid']) && $post['pid'] === $id) {
                $post['plikes'] += 1;
                $updated = true;
                break;
            }
        }
    } elseif ($type === 'comment') {
        // 评论点赞
        foreach ($postsData as &$post) {
            if (isset($post['comments']) && is_array($post['comments'])) {
                foreach ($post['comments'] as &$comment) {
                    if (isset($comment['com_cid']) && $comment['com_cid'] === $id) {
                        $comment['clikes'] += 1;
                        $updated = true;
                        break 2;
                    }
                }
            }
        }
    }
    
    if (!$updated) {
        echo json_encode(['status' => 'error', 'msg' => '未找到对应内容']);
        exit;
    }
    
    $result = file_put_contents($postsJsonPath, json_encode($postsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // 清除缓存
    if (file_exists($postsCachePath)) {
        unlink($postsCachePath);
    }
    exit;
}

// 后端处理逻辑：获取帖子的所有评论
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_post_comments') {
    $pid = trim($_POST['pid']);
    
    if (empty($pid)) {
        echo json_encode(['status' => 'error', 'msg' => '帖子ID不能为空']);
        exit;
    }
    
    $postsData = json_decode(file_get_contents($postsJsonPath), true);
    $postFound = false;
    $comments = [];
    
    foreach ($postsData as $post) {
        if (isset($post['pid']) && $post['pid'] === $pid) {
            $postFound = true;
            $comments = isset($post['comments']) ? $post['comments'] : [];
            break;
        }
    }
    
    if ($postFound) {
        echo json_encode(['status' => 'success', 'comments' => $comments]);
    } else {
        echo json_encode(['status' => 'error', 'msg' => '未找到对应帖子']);
    }
    exit;
}

// 获取上传限制信息
$uploadLimits = getUploadLimits();

// 获取今天日期用于标记
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="author" content="305运营团队">
    <link rel="shortcut icon" href="/logo.png">
    <title>武冈信息墙 | 武冈墙</title> 
        <script src="./main.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./main.css">
</head>
<body>
    <!-- 全局提示框 -->
    <div class="global-tip" id="globalTip" style="z-index: 308;"></div>
    
    <!-- 图片查看器模态框 -->
    <div class="modal-mask" id="imageViewerModal" style="z-index: 306;">
        <div class="modal-content image-viewer-content">
            <button class="modal-close-btn" id="imageViewerClose">关闭</button>
            <img class="image-viewer-img" id="viewerImage" src="" alt="查看图片">
            <div class="image-viewer-controls">
                <div style="display: flex; gap: 15px; align-items: center;">
                    <button class="image-viewer-btn" id="prevImageBtn">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <div class="image-viewer-info" id="imageInfo">1 / 1</div>
                    <button class="image-viewer-btn" id="nextImageBtn">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                <div class="image-viewer-hint" id="imageViewerHint">
                    提示：双击图片关闭 | 左右滑动切换图片
                </div>
            </div>
        </div>
    </div>

    <!-- 帖子详情模态框 -->
    <div class="modal-mask" id="postDetailModal" style="z-index: 305;">
        <div class="modal-content post-detail-content">
            <div class="post-detail-header">
                <button class="post-detail-back" id="postDetailBack">
                    <i class="fas fa-arrow-left"></i>
                    返回
                </button>
                <div class="post-detail-title">动态详情</div>
                <div class="post-detail-user">
                    <img src="default-avatar.jpg" alt="用户头像" class="post-detail-avatar" id="postDetailAvatar" style="display: none;">
                </div>
            </div>
            <div class="post-detail-body">
                <div class="post-detail-scrollable" id="postDetailContent">
                    <!-- 帖子详情内容将通过JavaScript动态填充 -->
                </div>
            </div>
        </div>
    </div>

    <!-- 登录模态框 -->
    <div class="modal-mask" id="loginModal" style="z-index: 307;">
        <div class="modal-content">
            <button class="modal-close-btn" id="loginModalClose">关闭</button>
            <h3 class="modal-title">登录</h3>
            
            <!-- ID重要性提醒 -->
            <div class="id-reminder">
                <i class="fas fa-exclamation-circle"></i>
                <strong>提醒：</strong>你只需要记住最后的数字，其他的都是零！
            </div>
            
            <form id="loginForm">
                <div class="form-group">
                    <label for="loginId">账号ID</label>
                    <div class="input-with-tip">
                        <input type="text" id="loginId" name="id" required placeholder="请输入十位数ID">
                        <div class="id-format-tip">10位数</div>
                    </div>
                    <!-- ID记忆提示 -->
                    <div class="id-memory-tip" id="idMemoryTip">
                        <i class="far fa-lightbulb" style="color: #f39c12;"></i>
                        <span>忘记了可以去账号搜索那搜自己的账号名找到ID</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="loginPwd">密码</label>
                    <input type="password" id="loginPwd" name="password" required placeholder="请输入密码">
                </div>
                
                <button type="submit" class="btn" id="loginSubmitBtn">登录</button>
                
                <div class="register-btn-wrapper">
                    <a href="register2.php" class="btn register-btn" id="registerBtn">
                        还没有账号？去注册
                    </a>
                </div>
                
                <!-- 登录成功后显示ID -->
                <div class="login-success-id" id="loginSuccessId">
                    <div style="font-size: 14px; margin-bottom: 5px; opacity: 0.9;">
                        <i class="fas fa-check-circle"></i> 登录成功！
                    </div>
                    <div style="font-size: 16px; font-weight: bold;">
                        您的ID：<span id="displayUserId" style="
                            background: rgba(255,255,255,0.2);
                            padding: 3px 8px;
                            border-radius: 4px;
                            margin: 0 5px;
                        "></span>
                    </div>
                    <div style="font-size: 12px; margin-top: 5px; opacity: 0.8;">
                        <i class="fas fa-copy" id="copyIdBtn" style="cursor: pointer; margin-right: 5px;"></i>
                        点击复制ID
                    </div>
                </div>
                
                <div class="modal-tip" id="loginTip"></div>
            </form>
        </div>
    </div>

    <!-- 发帖模态框 -->
    <div class="modal-mask bottom-modal" id="postModal" style="z-index: 306;">
        <div class="modal-content">
            <button class="modal-close-btn" id="postModalClose">取消</button>
            <h3 class="modal-title">发布动态</h3>
            
            <div class="modal-scrollable">  
                <!-- 压缩图片提示 -->
                <div class="compress-tip" id="compressTip" style="display: none;">
                    <i class="fas fa-compress-alt"></i>
                    <span>检测到大图片，系统将自动压缩...</span>
                </div>
                
                <form id="postForm" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="postContent">武冈墙</label>
                        <textarea id="postContent" name="content" required placeholder="请输入你想分享的内容..."></textarea>
                    </div>

                                    <!-- 发帖建议区域 -->
                <div class="suggestions-area">
                    <div class="suggestions-title">推荐标签:(你也可以自己增加##标签!)</div>
                    <div class="suggestion-tags" id="postSuggestionTags">
                        <div class="suggestion-tag" data-tag="#表白#">#表白#</div>
                        <div class="suggestion-tag" data-tag="#寻物启事#">#寻物启事#</div>
                        <div class="suggestion-tag" data-tag="#寻人启事#">#寻人启事#</div>
                        <div class="suggestion-tag" data-tag="#八卦#">#八卦#</div>
                        <div class="suggestion-tag" data-tag="#讽刺#">#讽刺#</div>
                        <div class="suggestion-tag" data-tag="#王者荣耀#">#王者荣耀#</div>
                        <div class="suggestion-tag" data-tag="#和平精英#">#和平精英#</div>
                        <div class="suggestion-tag" data-tag="#避雷#">#避雷#</div>
                        <div class="suggestion-tag" data-tag="#提问#">#提问#</div>
                    </div>
                </div>
                    
                    <!-- 帖子图片上传区域 -->
                    <div class="form-group image-upload-area">
                        <label class="image-upload-label">上传图片（最多3张）</label>
                        <input type="file" id="postImages" name="post_images[]" accept="image/*" multiple style="display: none;">
                        <div class="upload-placeholder" id="postUploadPlaceholder">
                            <i class="fas fa-plus"></i>
                        </div>
                        <div class="image-preview-container" id="postImagePreview"></div>
                        <div class="image-count" id="postImageCount">0/3 张图片</div>
                        <div class="file-size-hint">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>注意：选择大文件可能需要较长时间</span>
                        </div>
                    </div>
                    
                                    <!-- 设备名选择区域 -->
                <div class="device-select-area" id="postDeviceSelect">
                    <div class="device-header" id="postDeviceHeader">
                        <span>设备名：<span id="postDeviceName">获取中...</span></span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="device-options" id="postDeviceOptions">
                        <div class="device-option">
                            <input type="radio" id="postDeviceShow" name="postDevice" value="show" checked>
                            <label for="postDeviceShow">显示设备名</label>
                        </div>
                        <div class="device-option">
                            <input type="radio" id="postDeviceHide" name="postDevice" value="hide">
                            <label for="postDeviceHide">不显示设备名</label>
                        </div>
                    </div>
                    <input type="hidden" id="postDeviceNameValue" name="device_name" value="">
                </div>

                    <!-- 上传进度条 -->
                    <div class="upload-progress" id="postUploadProgress">
                        <div class="upload-progress-bar" id="postUploadProgressBar"></div>
                    </div>
                    
                    <input type="hidden" id="postPname" name="pname">
                    <input type="hidden" id="postPortrait" name="portrait">
                    <input type="hidden" id="postDevice" name="device" value="show">
                    <button type="submit" class="btn" id="postSubmitBtn">发布</button>
                    <div class="modal-tip" id="postTip"></div>
                </form>
            </div>
        </div>
    </div>

    <!-- 评论模态框 -->
    <div class="modal-mask bottom-modal" id="commentModal" style="z-index: 306;">
        <div class="modal-content">
            <button class="modal-close-btn" id="commentModalClose">取消</button>
            <h3 class="modal-title">评论</h3>
            
            <div class="modal-scrollable">
                <form id="commentForm" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="commentContent">武冈墙</label>
                        <textarea id="commentContent" name="com_content" required placeholder="请输入你的评论..."></textarea>
                    </div>
                    
                    <!-- 评论图片上传区域 -->
                    <div class="form-group image-upload-area">
                        <label class="image-upload-label">上传图片（最多3张）</label>
                        <input type="file" id="commentImages" name="comment_images[]" accept="image/*" multiple style="display: none;">
                        <div class="upload-placeholder" id="commentUploadPlaceholder">
                            <i class="fas fa-plus"></i>
                        </div>
                        <div class="image-preview-container" id="commentImagePreview"></div>
                        <div class="image-count" id="commentImageCount">0/3 张图片</div>
                    </div>
                    
                    <!-- 设备名选择区域 -->
                <div class="device-select-area" id="commentDeviceSelect">
                    <div class="device-header" id="commentDeviceHeader">
                        <span>设备名：<span id="commentDeviceName">获取中...</span></span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="device-options" id="commentDeviceOptions">
                        <div class="device-option">
                            <input type="radio" id="commentDeviceShow" name="commentDevice" value="show" checked>
                            <label for="commentDeviceShow">显示设备名</label>
                        </div>
                        <div class="device-option">
                            <input type="radio" id="commentDeviceHide" name="commentDevice" value="hide">
                            <label for="commentDeviceHide">不显示设备名</label>
                        </div>
                    </div>
                    <input type="hidden" id="commentDeviceNameValue" name="device_name" value="">
                </div>

                    <!-- 上传进度条 -->
                    <div class="upload-progress" id="commentUploadProgress">
                        <div class="upload-progress-bar" id="commentUploadProgressBar"></div>
                    </div>
                    
                    <input type="hidden" id="commentPid" name="pid">
                    <input type="hidden" id="commentPname" name="pname">
                    <input type="hidden" id="commentPortrait" name="portrait">
                    <input type="hidden" id="commentDevice" name="device" value="show">
                    <button type="submit" class="btn" id="commentSubmitBtn">发布评论</button>
                    <div class="modal-tip" id="commentTip"></div>
                </form>
            </div>
        </div>
    </div>

    <!-- 主内容区 -->
    <div class="container" >
        <!-- 导航 section1 -->
        <div id="section1">
            <div class="section1-top">
                <div class="logo-area">
                    <div class="logo">
                        <img src="logo.png" alt="logo" style="width: 40px; height: 40px;">
                    </div>
                    <div class="service-name">武冈墙</div>
                </div>
                <div class="nav-right">
                    <a href="../../no10.html" class="back-btn">
                        <img src="./arrow-alt-circle-left.svg" alt="返回" style="width: 25px; height: 25px; display: block;">
                    </a>
                    <img src="default-avatar.jpg" alt="用户头像" class="user-avatar" id="userAvatar" style="display: none;">
                    <!-- 用户信息面板 -->
                    <div class="user-info-panel" id="userInfoPanel">
                        <div class="user-info-item">昵称：<span id="panelPname"></span></div>
                        <div class="user-info-item">性别：<span id="panelGender"></span></div>
                        <div class="user-info-item">账号：
                            <span id="panelId" style="
                                color: #ff6b6b;
                                font-weight: bold;
                                background: rgba(255,107,107,0.1);
                                padding: 2px 6px;
                                border-radius: 4px;
                                margin-right: 5px;
                            "></span>
                            <i class="fas fa-copy" id="copyPanelIdBtn" 
                               style="font-size: 12px; color: #4a90e2; cursor: pointer;" 
                               title="复制ID"></i>
                        </div>
                        <button class="logout-btn" id="logoutBtn">退出登录</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 轮播 section2 -->
        <div id="section2">
            <div class="carousel" id="carousel">
                <div class="carousel-item">
                    <a href="mqqapi://card/show_pslcard?src_type=internal&version=1&uin=3349114637&card_type=person&source=qrcode" 
                           data-fallback="#" 
                           onclick="return handleLinkClick(this)"><img src="./floatimg/1.png" alt="1" class="carousel-img"></a>
                </div>
                <div class="carousel-item">
                    <a href="./admin-employ/apply.php"><img src="./floatimg/4.png" alt="2" class="carousel-img"></a>
                </div>
                <div class="carousel-item">
                    <img src="./floatimg/3.png" alt="3" class="carousel-img">
                </div>
            </div>
            <div class="carousel-indicators" id="carouselIndicators">
                <span class="indicator-dot active"></span>
                <span class="indicator-dot"></span>
                <span class="indicator-dot"></span>
            </div>
        </div>

        <!-- section3 外接服务版块 -->
        <div id="section3">
            <div class="service-button" onclick="location.href='./mp.html'">
                <div class="service-icon">
                    <i class="fas fa-search"></i>
                </div>
                <div class="service-text">搜索账号</div>
            </div>
            <div class="service-button" onclick="location.href='./jb/report_submit.php'">
                <div class="service-icon">
                    <i class="fas fa-flag"></i>
                </div>
                <div class="service-text">举报违规内容</div>
            </div>
            <div class="service-button" onclick="location.href='./problem/problem.php'">
                <div class="service-icon">
                    <i class="fas fa-flag"></i>
                </div>
            <div class="service-text">反馈问题</div>
            </div>
        </div>
        
        <!-- section1.5 切换栏 -->
        <div id="section1_5">
            <button class="tab-button active" id="latestTab">最新</button>
            <button class="tab-button" id="loveTab">表白</button>
            <button class="tab-button" id="gossipTab">八卦</button>
        </div>

        <!-- 帖子 section4 -->
        <div id="section4">
            <div class="section4-header">
                <div class="current-tab" id="currentTab">最新</div>
                <div class="refresh-btn" id="refreshBtn">刷新</div>
            </div>
            <input type="text" class="search-box" id="postSearch" placeholder="搜索帖子内容或关键词...">
            
            <!-- 帖子列表容器 -->
            <div class="posts-list" id="postsList">
                <!-- 帖子将通过JavaScript动态加载 -->
            </div>
            
            <!-- 加载更多按钮 -->
            <div class="load-more-container" id="loadMoreContainer" style="display: none;">
                <button class="load-more-btn" id="loadMoreBtn">
                    <span>加载更多</span>
                </button>
            </div>
            
            <!-- 加载中提示 -->
            <div class="loading-indicator" id="loadingIndicator" style="text-align: center; padding: 20px; display: none;">
                <div class="loading-spinner"></div>
                <div style="margin-top: 10px; color: #666;">加载中...</div>
            </div>
        </div>

        <!-- 页脚 section5 -->
        <div id="section5">
            <div class="footer-logo-area">
                <div class="footer-logo">
                    <img src="logo.png" alt="logo" style="width: 45px;">
                </div>
                <div class="footer-service-name">武冈墙 | 305</div>
            </div>
        </div>
    </div>

    <!-- 底部登录提示 -->
    <div class="bottom-login-prompt" id="bottomLoginPrompt" style="z-index: 304;">
        <i class="fas fa-user"></i>
        <span>登录/注册</span>
    </div>

    <!-- 右下角固定图标 -->
    <div class="fixed-icons" style="z-index: 304;">
        <div class="fixed-icon top-icon" id="topIcon"></div>
        <div class="fixed-icon post-btn" id="publishPostBtn"></div>
    </div>
</body>
</html>