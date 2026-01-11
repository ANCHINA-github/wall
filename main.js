       // 全局变量
        const userInfo = JSON.parse(localStorage.getItem('userInfo')) || null;
        let currentCarouselIndex = 0;
        let carouselTimer = null;
        let currentTab = 'latest'; // 默认显示最新
        let lastRefreshTime = 0;
        
        // 分页相关变量
        let currentPage = 1;
        const postsPerPage = 10;
        let isLoading = false;
        let hasMorePosts = true;
        let postsLoaded = false;
        
        // 防止重复提交的全局锁
        const submitLock = {
            login: false,
            register: false,
            post: false,
            comment: false,
            like: false
        };
        
        // 图片查看器相关变量
        let currentViewerImages = [];
        let currentViewerIndex = 0;
        
        // 服务器上传限制信息
        const uploadLimits = {
            maxSingleSize: 3 * 1024 * 1024,
            maxTotalSize: 6 * 1024 * 1024,
            maxFiles: 3
        };

        // 页面加载完成后执行
        document.addEventListener('DOMContentLoaded', function() {
            // 初始化用户信息
            initUserInfo();
            // 初始化轮播图
            initCarousel();
            // 初始化搜索功能
            initSearch();
            // 初始化模态框事件
            initModalEvents();
            // 初始化按钮事件
            initButtonEvents();
            // 初始化标签切换
            initTabEvents();
            // 初始化图片上传功能
            initImageUpload();
            // 初始化图片查看器
            initImageViewer();
            // 初始化设备名获取
            initDeviceName();
            // 初始化关键词点击事件
            initKeywordEvents();
            
            // 检查登录状态，未登录则显示底部登录提示
            if (!userInfo) {
                document.getElementById('bottomLoginPrompt').style.display = 'flex';
            } else {
                document.getElementById('bottomLoginPrompt').style.display = 'none';
            }
            
            // 加载第一页帖子
            loadPosts(currentPage, postsPerPage, currentTab, true);
            
            // 检查是否有保存的上次登录ID
            checkLastLoginId();
            
            // 初始化内容折叠功能
            initContentCollapse();
            
            // 初始化评论折叠功能
            initCommentsCollapse();
            
            // 初始化伪输入框点击事件
            initFakeInputEvents();
            
            // 初始化帖子详情查看功能
            initPostDetailView();
            
            // 监听滚动事件实现无限滚动
            initInfiniteScroll();
        });
        
        // 初始化无限滚动
        function initInfiniteScroll() {
            let ticking = false;
            
            window.addEventListener('scroll', function() {
                if (!ticking) {
                    window.requestAnimationFrame(function() {
                        checkScrollPosition();
                        ticking = false;
                    });
                    ticking = true;
                }
            });
            
            // 加载更多按钮点击事件
            document.getElementById('loadMoreBtn').addEventListener('click', function() {
                loadMorePosts();
            });
        }
        
        // 检查滚动位置
        function checkScrollPosition() {
            if (isLoading || !hasMorePosts || !postsLoaded) return;
            
            const loadMoreContainer = document.getElementById('loadMoreContainer');
            const containerRect = loadMoreContainer.getBoundingClientRect();
            const windowHeight = window.innerHeight;
            
            // 如果"加载更多"按钮进入视口，自动加载
            if (containerRect.top <= windowHeight + 100) {
                loadMorePosts();
            }
        }
        
        // 加载更多帖子
        function loadMorePosts() {
            if (isLoading || !hasMorePosts) return;
            
            currentPage++;
            loadPosts(currentPage, postsPerPage, currentTab, false);
        }
        
        // 加载帖子数据
        function loadPosts(page, perPage, filter, clearExisting = false) {
            if (isLoading) return;
            
            isLoading = true;
            postsLoaded = false;
            
            // 显示加载指示器
            const loadingIndicator = document.getElementById('loadingIndicator');
            const loadMoreContainer = document.getElementById('loadMoreContainer');
            const postsList = document.getElementById('postsList');
            
            if (clearExisting) {
                postsList.innerHTML = '';
                currentPage = 1;
                loadMoreContainer.style.display = 'none';
            }
            
            loadingIndicator.style.display = 'block';
            
            // 发送AJAX请求获取帖子数据
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'login-service.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.timeout = 30000;
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    isLoading = false;
                    loadingIndicator.style.display = 'none';
                    
                    if (xhr.status === 200) {
                        try {
                            const result = JSON.parse(xhr.responseText);
                            
                            if (result.html) {
                                if (clearExisting) {
                                    postsList.innerHTML = result.html;
                                } else {
                                    postsList.insertAdjacentHTML('beforeend', result.html);
                                }
                                
                                // 重新绑定事件
                                bindPostsEvents();
                                
                                // 更新是否有更多帖子
                                hasMorePosts = result.has_more;
                                
                                // 显示或隐藏"加载更多"按钮
                                if (hasMorePosts) {
                                    loadMoreContainer.style.display = 'block';
                                } else {
                                    loadMoreContainer.style.display = 'none';
                                    if (page > 1 && result.total > 0) {
                                        showGlobalTip('已加载所有帖子', 'info');
                                    }
                                }
                                
                                postsLoaded = true;
                                
                                // 如果有搜索关键词，进行搜索
                                const searchBox = document.getElementById('postSearch');
                                if (searchBox.value.trim()) {
                                    performSearch(searchBox.value.trim());
                                }
                            }
                        } catch (e) {
                            console.error('解析帖子数据失败:', e);
                            showGlobalTip('加载帖子失败，请刷新重试', 'error');
                        }
                    } else {
                        showGlobalTip('网络错误，请检查连接后重试', 'error');
                    }
                }
            };
            
            xhr.ontimeout = function() {
                isLoading = false;
                loadingIndicator.style.display = 'none';
                showGlobalTip('加载超时，请稍后重试', 'error');
            };
            
            xhr.onerror = function() {
                isLoading = false;
                loadingIndicator.style.display = 'none';
                showGlobalTip('网络连接失败，请检查网络', 'error');
            };
            
            xhr.send(`action=get_posts&page=${page}&per_page=${perPage}&filter=${filter}`);
        }
        
        // 绑定帖子相关事件
        function bindPostsEvents() {
            // 绑定图片点击事件
            initExistingImageClickEvents();
            
            // 绑定点赞事件
            document.querySelectorAll('.post-like-btn, .comment-like-btn').forEach(btn => {
                btn.addEventListener('click', handleLikeClick);
            });
            
            // 绑定评论按钮事件
            document.querySelectorAll('.post-comment-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (!userInfo) {
                        showModal('loginModal');
                        return;
                    }
                    const pid = this.getAttribute('data-pid');
                    document.getElementById('commentPid').value = pid;
                    document.getElementById('commentPname').value = userInfo.pname;
                    document.getElementById('commentPortrait').value = userInfo.portrait || '';
                    showModal('commentModal');
                });
            });
            
            // 绑定内容展开按钮事件
            document.querySelectorAll('.expand-content-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const pid = this.getAttribute('data-pid');
                    const contentElement = document.getElementById(`postContent_${pid}`);
                    if (contentElement) {
                        contentElement.classList.remove('collapsed');
                        contentElement.classList.add('expanded');
                        this.style.display = 'none';
                    }
                });
            });
            
            // 绑定评论展开按钮事件
            document.querySelectorAll('.expand-comments-btn').forEach(btn => {
                btn.addEventListener('click', handleExpandComments);
            });
        }
        
        // 初始化伪输入框点击事件
        function initFakeInputEvents() {
            // 使用事件委托处理伪输入框点击
            document.addEventListener('click', function(e) {
                if (e.target.closest('.fake-input')) {
                    const fakeInput = e.target.closest('.fake-input');
                    const pid = fakeInput.getAttribute('data-pid');
                    
                    if (!userInfo) {
                        showModal('loginModal');
                        return;
                    }
                    
                    // 打开评论模态框
                    document.getElementById('commentPid').value = pid;
                    document.getElementById('commentPname').value = userInfo.pname;
                    document.getElementById('commentPortrait').value = userInfo.portrait || '';
                    showModal('commentModal');
                }
            });
        }
        
        // 初始化帖子详情查看功能
        function initPostDetailView() {
            // 帖子详情返回按钮
            document.getElementById('postDetailBack').addEventListener('click', function() {
                hideModal('postDetailModal');
            });
            
            // 帖子详情用户头像点击
            document.getElementById('postDetailAvatar').addEventListener('click', function(e) {
                e.stopPropagation();
                const panel = document.getElementById('userInfoPanel');
                panel.classList.toggle('show');
            });
        }
        
        // 初始化设备名获取
        function initDeviceName() {
            // 获取设备信息
            const userAgent = navigator.userAgent;
            let deviceName = '未知设备';
            
            // 检测常见设备
            if (/iPhone|iPad|iPod/.test(userAgent)) {
                deviceName = /iPhone/.test(userAgent) ? 'iPhone' : 'iPad';
            } else if (/Android/.test(userAgent)) {
                deviceName = 'Android';
            } else if (/Windows/.test(userAgent)) {
                deviceName = 'Windows PC';
            } else if (/Mac/.test(userAgent)) {
                deviceName = 'Mac';
            } else if (/Linux/.test(userAgent)) {
                deviceName = 'Linux';
            }
            
            // 设置设备名
            document.getElementById('postDeviceName').textContent = deviceName;
            document.getElementById('postDeviceNameValue').value = deviceName;
            document.getElementById('commentDeviceName').textContent = deviceName;
            document.getElementById('commentDeviceNameValue').value = deviceName;
            
            // 设备名选择区域交互
            const postDeviceHeader = document.getElementById('postDeviceHeader');
            const postDeviceOptions = document.getElementById('postDeviceOptions');
            const commentDeviceHeader = document.getElementById('commentDeviceHeader');
            const commentDeviceOptions = document.getElementById('commentDeviceOptions');
            
            // 发帖设备选择
            postDeviceHeader.addEventListener('click', function() {
                const isExpanded = postDeviceOptions.classList.contains('expanded');
                postDeviceHeader.classList.toggle('expanded', !isExpanded);
                postDeviceOptions.classList.toggle('expanded', !isExpanded);
            });
            
            // 评论设备选择
            commentDeviceHeader.addEventListener('click', function() {
                const isExpanded = commentDeviceOptions.classList.contains('expanded');
                commentDeviceHeader.classList.toggle('expanded', !isExpanded);
                commentDeviceOptions.classList.toggle('expanded', !isExpanded);
            });
            
            // 设备选择变化
            document.getElementById('postDeviceShow').addEventListener('change', function() {
                document.getElementById('postDevice').value = 'show';
            });
            
            document.getElementById('postDeviceHide').addEventListener('change', function() {
                document.getElementById('postDevice').value = 'hide';
            });
            
            document.getElementById('commentDeviceShow').addEventListener('change', function() {
                document.getElementById('commentDevice').value = 'show';
            });
            
            document.getElementById('commentDeviceHide').addEventListener('change', function() {
                document.getElementById('commentDevice').value = 'hide';
            });
        }
        
        // 初始化关键词点击事件
        function initKeywordEvents() {
            // 使用事件委托处理关键词点击
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('keyword')) {
                    const keyword = e.target.getAttribute('data-keyword');
                    searchByKeyword(keyword);
                }
            });
        }
        
        // 为已有的帖子图片添加点击事件
        function initExistingImageClickEvents() {
            // 使用事件委托，提高性能
            const postsList = document.getElementById('postsList');
            if (!postsList) return;
            
            postsList.addEventListener('click', function(e) {
                // 帖子图片点击
                let imageItem = e.target.closest('.post-image-item');
                if (imageItem) {
                    e.stopPropagation();
                    const imageSrc = imageItem.getAttribute('data-image-src');
                    const postCard = imageItem.closest('.post-card');
                    const allImages = Array.from(postCard.querySelectorAll('.post-image-item')).map(item => item.getAttribute('data-image-src'));
                    const currentIndex = allImages.indexOf(imageSrc);
                    
                    if (currentIndex !== -1) {
                        openImageViewer(allImages, currentIndex);
                    }
                    return;
                }
                
                // 评论图片点击
                imageItem = e.target.closest('.comment-image-item');
                if (imageItem) {
                    e.stopPropagation();
                    const imageSrc = imageItem.getAttribute('data-image-src');
                    const commentItem = imageItem.closest('.comment-item');
                    const allImages = Array.from(commentItem.querySelectorAll('.comment-image-item')).map(item => item.getAttribute('data-image-src'));
                    const currentIndex = allImages.indexOf(imageSrc);
                    
                    if (currentIndex !== -1) {
                        openImageViewer(allImages, currentIndex);
                    }
                }
                
                // 帖子卡片点击（打开详情）
                if (e.target.closest('.post-card') && 
                    !e.target.closest('.post-like-btn') &&
                    !e.target.closest('.post-comment-btn') &&
                    !e.target.closest('.expand-content-btn') &&
                    !e.target.closest('.fake-input') &&
                    !e.target.closest('.post-image-item') &&
                    !e.target.closest('.action-btn') &&
                    !e.target.closest('.expand-comments-btn') &&
                    !e.target.closest('.comment-like-btn')) {
                    
                    const postCard = e.target.closest('.post-card');
                    showPostDetail(postCard);
                }
            });
        }
        
        // 显示帖子详情
        function showPostDetail(postCard) {
            const pid = postCard.getAttribute('data-pid');
            const pname = postCard.getAttribute('data-pname');
            const portrait = postCard.getAttribute('data-portrait');
            const pdate = postCard.getAttribute('data-pdate');
            const device = postCard.getAttribute('data-device');
            const content = postCard.getAttribute('data-content');
            const likes = postCard.getAttribute('data-likes');
            
            // 获取帖子图片
            const images = [];
            const imageItems = postCard.querySelectorAll('.post-image-item');
            imageItems.forEach(item => {
                images.push(item.getAttribute('data-image-src'));
            });
            
            // 获取评论数据
            const commentItems = postCard.querySelectorAll('.comment-item');
            const comments = [];
            commentItems.forEach(comment => {
                const cid = comment.getAttribute('data-cid');
                const comContent = comment.querySelector('.comment-content').innerHTML;
                const comPname = comment.querySelector('.comment-username').textContent;
                const comPortrait = comment.querySelector('.comment-avatar').src;
                const comDate = comment.querySelector('.comment-date').textContent.split(' ')[0];
                const clikes = comment.querySelector('.comment-like-count').textContent;
                
                // 获取评论图片
                const comImages = [];
                const comImageItems = comment.querySelectorAll('.comment-image-item');
                comImageItems.forEach(item => {
                    comImages.push(item.getAttribute('data-image-src'));
                });
                
                comments.push({
                    cid,
                    content: comContent,
                    pname: comPname,
                    portrait: comPortrait,
                    date: comDate,
                    likes: clikes,
                    images: comImages
                });
            });
            
            const detailContent = document.getElementById('postDetailContent');
            const postDetailAvatar = document.getElementById('postDetailAvatar');
            
            // 设置用户头像
            if (userInfo) {
                postDetailAvatar.src = userInfo.portrait || 'default-avatar.png';
                postDetailAvatar.style.display = 'block';
            } else {
                postDetailAvatar.style.display = 'none';
            }
            
            // 构建详情HTML
            let html = `
                <div class="post-card" style="box-shadow: none; cursor: default;">
                    <div class="post-header">
                        <img src="${portrait || 'default-avatar.png'}" alt="用户头像" class="post-avatar">
                        <div class="post-user-info">
                            <div class="post-username">
                                ${pname}
                                ${pname.includes('管理员') || pname.includes('墙') ? 
                                    '<span class="post-badge badge-auth"><i class="fas fa-check-circle"></i> 权威认证</span>' : ''}
                            </div>
                            <div class="post-date">
                                ${pdate}
                                ${device ? `<span class="post-device">${device}</span>` : ''}
                            </div>
                            <div class="post-badges">
                                ${likes >= 18 ? 
                                    '<span class="post-badge badge-spark"><i class="fas fa-fire"></i> 火花</span>' : ''}
                                ${pdate.startsWith('<?php echo $today; ?>') ? 
                                    '<span class="post-badge badge-today"><i class="fas fa-calendar-day"></i> 今日发布</span>' : ''}
                            </div>
                        </div>
                    </div>
                    <div class="post-content" style="max-height: none;">
                        ${content.replace(/\n/g, '<br>')}
                    </div>
            `;
            
            // 添加图片
            if (images && images.length > 0) {
                html += `<div class="post-images-container">`;
                images.forEach((image, index) => {
                    const imageClass = images.length === 1 ? 'single' : 'multiple';
                    html += `
                        <div class="post-image-item ${imageClass}" data-image-src="${image}" data-index="${index}">
                            <img src="${image}" alt="帖子图片" class="post-image">
                        </div>
                    `;
                });
                html += `</div>`;
            }
            
            // 添加伪输入框
            html += `
                <div class="fake-input" data-pid="${pid}">
                    <i class="far fa-comment"></i>
                    <span>写下你的评论...</span>
                </div>
                
                <div class="post-actions">
                    <button class="action-btn post-like-btn" data-type="post" data-id="${pid}">
                        <i class="far fa-heart action-icon"></i>
                        <span class="like-count">${likes}</span> 
                    </button>
                    <button class="action-btn post-comment-btn" data-pid="${pid}">
                        <i class="far fa-comment action-icon"></i>
                        <span class="comment-count">${comments.length}</span>
                    </button>
                </div>
            `;
            
            // 添加评论
            if (comments.length > 0) {
                html += `<div class="comments-container">`;
                html += `<div class="comments-list">`;
                
                comments.forEach(comment => {
                    html += `
                        <div class="comment-item" data-cid="${comment.cid}">
                            <img src="${comment.portrait}" alt="评论用户头像" class="comment-avatar">
                            <div class="comment-content-wrap">
                                <div class="comment-header">
                                    <div class="comment-username">${comment.pname}</div>
                                    <div class="comment-date">${comment.date}</div>
                                </div>
                                <div class="comment-content">${comment.content}</div>
                    `;
                    
                    // 评论图片
                    if (comment.images && comment.images.length > 0) {
                        html += `<div class="comment-images-container">`;
                        comment.images.forEach(image => {
                            html += `
                                <div class="comment-image-item" data-image-src="${image}">
                                    <img src="${image}" alt="评论图片" class="comment-image">
                                </div>
                            `;
                        });
                        html += `</div>`;
                    }
                    
                    html += `
                                <div class="comment-actions">
                                    <button class="comment-like-btn" data-type="comment" data-id="${comment.cid}">
                                        <i class="far fa-heart"></i>
                                        <span class="comment-like-count">${comment.likes}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                html += `</div></div>`;
            }
            
            html += `</div>`;
            
            detailContent.innerHTML = html;
            
            // 重新绑定详情中的事件
            bindDetailEvents(pid);
            
            // 显示模态框
            showModal('postDetailModal');
        }
        
        // 绑定详情中的事件
        function bindDetailEvents(pid) {
            const detailContent = document.getElementById('postDetailContent');
            
            // 点赞按钮
            const likeBtns = detailContent.querySelectorAll('.post-like-btn, .comment-like-btn');
            likeBtns.forEach(btn => {
                btn.addEventListener('click', handleLikeClick);
            });
            
            // 评论按钮
            const commentBtn = detailContent.querySelector('.post-comment-btn');
            if (commentBtn) {
                commentBtn.addEventListener('click', function() {
                    if (!userInfo) {
                        showModal('loginModal');
                        return;
                    }
                    document.getElementById('commentPid').value = pid;
                    document.getElementById('commentPname').value = userInfo.pname;
                    document.getElementById('commentPortrait').value = userInfo.portrait || '';
                    showModal('commentModal');
                });
            }
            
            // 伪输入框
            const fakeInput = detailContent.querySelector('.fake-input');
            if (fakeInput) {
                fakeInput.addEventListener('click', function() {
                    if (!userInfo) {
                        showModal('loginModal');
                        return;
                    }
                    document.getElementById('commentPid').value = pid;
                    document.getElementById('commentPname').value = userInfo.pname;
                    document.getElementById('commentPortrait').value = userInfo.portrait || '';
                    showModal('commentModal');
                });
            }
            
            // 图片点击
            const imageItems = detailContent.querySelectorAll('.post-image-item, .comment-image-item');
            imageItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const imageSrc = this.getAttribute('data-image-src');
                    const container = this.closest('.post-images-container, .comment-images-container');
                    const allImages = Array.from(container.querySelectorAll('[data-image-src]'))
                        .map(img => img.getAttribute('data-image-src'));
                    const currentIndex = allImages.indexOf(imageSrc);
                    
                    if (currentIndex !== -1) {
                        openImageViewer(allImages, currentIndex);
                    }
                });
            });
        }
        
        // 初始化内容折叠功能
        function initContentCollapse() {
            // 使用事件委托处理内容展开/折叠
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('expand-content-btn')) {
                    e.stopPropagation();
                    const pid = e.target.getAttribute('data-pid');
                    const contentElement = document.getElementById(`postContent_${pid}`);
                    if (contentElement) {
                        contentElement.classList.remove('collapsed');
                        contentElement.classList.add('expanded');
                        e.target.style.display = 'none';
                    }
                }
            });
        }
        
        // 初始化评论折叠功能
        function initCommentsCollapse() {
            // 使用事件委托处理评论展开/折叠
            document.addEventListener('click', handleExpandComments);
        }
        
        // 处理评论展开
        function handleExpandComments(e) {
            if (e.target.classList.contains('expand-comments-btn')) {
                e.preventDefault();
                e.stopPropagation();
                
                const expandBtn = e.target;
                const pid = expandBtn.getAttribute('data-pid');
                const isLoaded = expandBtn.getAttribute('data-loaded') === 'true';
                
                // 如果已经加载过，直接展开
                if (isLoaded) {
                    const commentsList = document.getElementById(`comments_${pid}`);
                    if (commentsList) {
                        commentsList.classList.remove('collapsed');
                        expandBtn.style.display = 'none';
                    }
                    return;
                }
                
                // 显示加载状态
                expandBtn.disabled = true;
                expandBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 加载中...';
                
                // 获取所有评论
                fetch('login-service.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=get_post_comments&pid=${encodeURIComponent(pid)}`
                })
                .then(response => response.json())
                .then(result => {
                    if (result.status === 'success') {
                        const comments = result.comments;
                        const commentsList = document.getElementById(`comments_${pid}`);
                        
                        if (commentsList && comments.length > 0) {
                            // 清空当前显示的评论
                            commentsList.innerHTML = '';
                            
                            // 添加所有评论
                            comments.forEach(comment => {
                                const commentElement = createCommentElement(comment);
                                commentsList.appendChild(commentElement);
                            });
                            
                            // 移除折叠类，显示所有评论
                            commentsList.classList.remove('collapsed');
                            
                            // 标记为已加载
                            expandBtn.setAttribute('data-loaded', 'true');
                            
                            // 隐藏展开按钮
                            expandBtn.style.display = 'none';
                            
                            // 重新绑定事件
                            bindCommentEvents(pid);
                            
                            showGlobalTip('评论加载完成', 'success');
                        }
                    } else {
                        expandBtn.disabled = false;
                        expandBtn.innerHTML = '展开剩余评论';
                        showGlobalTip(result.msg || '加载失败', 'error');
                    }
                })
                .catch(error => {
                    console.error('加载评论失败:', error);
                    expandBtn.disabled = false;
                    expandBtn.innerHTML = '展开剩余评论';
                    showGlobalTip('网络错误，请重试', 'error');
                });
            }
        }
        
        // 创建评论元素
        function createCommentElement(comment) {
            const commentDiv = document.createElement('div');
            commentDiv.className = 'comment-item';
            commentDiv.setAttribute('data-cid', comment.com_cid);
            
            const comDevice = comment.com_device || '';
            const comImages = comment.com_images || [];
            
            // 处理评论内容中的关键词
            let comContent = escapeHtml(comment.com_content || '');
            comContent = comContent.replace(/#([^#]+)#/g, '<span class="keyword" data-keyword="$1">#$1#</span>');
            
            // 生成图片HTML
            let imagesHTML = '';
            if (comImages.length > 0) {
                imagesHTML = '<div class="comment-images-container">';
                comImages.forEach(image => {
                    imagesHTML += `
                        <div class="comment-image-item" data-image-src="${image}">
                            <img src="${image}" alt="评论图片" class="comment-image">
                        </div>`;
                });
                imagesHTML += '</div>';
            }
            
            // 生成设备HTML
            let deviceHTML = '';
            if (comDevice) {
                deviceHTML = `<span class="comment-device">${comDevice}</span>`;
            }
            
            commentDiv.innerHTML = `
                <img src="${comment.com_portrait || 'default-avatar.png'}" alt="评论用户头像" class="comment-avatar">
                <div class="comment-content-wrap">
                    <div class="comment-header">
                        <div class="comment-username">${comment.com_pname}</div>
                        <div class="comment-date">
                            ${comment.com_date}
                            ${deviceHTML}
                        </div>
                    </div>
                    <div class="comment-content">
                        ${comContent.replace(/\n/g, '<br>')}
                    </div>
                    ${imagesHTML}
                    <div class="comment-actions">
                        <button class="comment-like-btn" data-type="comment" data-id="${comment.com_cid}">
                            <i class="far fa-heart"></i>
                            <span class="comment-like-count">${comment.clikes || 0}</span>
                        </button>
                    </div>
                </div>`;
            
            return commentDiv;
        }
        
        // HTML转义函数
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // 重新绑定评论相关事件
        function bindCommentEvents(pid) {
            const commentsList = document.getElementById(`comments_${pid}`);
            if (!commentsList) return;
            
            // 绑定点赞事件
            const likeBtns = commentsList.querySelectorAll('.comment-like-btn');
            likeBtns.forEach(btn => {
                btn.addEventListener('click', handleLikeClick);
            });
            
            // 绑定图片点击事件
            const imageItems = commentsList.querySelectorAll('.comment-image-item');
            imageItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const imageSrc = this.getAttribute('data-image-src');
                    const allImages = Array.from(this.parentElement.querySelectorAll('.comment-image-item'))
                        .map(img => img.getAttribute('data-image-src'));
                    const currentIndex = allImages.indexOf(imageSrc);
                    
                    if (currentIndex !== -1) {
                        openImageViewer(allImages, currentIndex);
                    }
                });
            });
        }
        
        // 关键词搜索功能
        function searchByKeyword(keyword) {
            const searchBox = document.getElementById('postSearch');
            searchBox.value = `#${keyword}#`;
            
            // 触发搜索
            performSearch(searchBox.value);
            
            // 滚动到搜索框位置
            searchBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            // 显示提示
            showGlobalTip(`正在搜索关键词: #${keyword}#`, 'info');
        }

        // 初始化图片上传功能
        function initImageUpload() {
            // 发帖图片上传
            const postUploadPlaceholder = document.getElementById('postUploadPlaceholder');
            const postImagesInput = document.getElementById('postImages');
            const postImagePreview = document.getElementById('postImagePreview');
            const postImageCount = document.getElementById('postImageCount');
            
            // 评论图片上传
            const commentUploadPlaceholder = document.getElementById('commentUploadPlaceholder');
            const commentImagesInput = document.getElementById('commentImages');
            const commentImagePreview = document.getElementById('commentImagePreview');
            const commentImageCount = document.getElementById('commentImageCount');
            
            // 初始化发帖图片上传
            initSingleImageUpload(postUploadPlaceholder, postImagesInput, postImagePreview, postImageCount, 'post');
            
            // 初始化评论图片上传
            initSingleImageUpload(commentUploadPlaceholder, commentImagesInput, commentImagePreview, commentImageCount, 'comment');
        }
        
        // 初始化单个图片上传功能
        function initSingleImageUpload(uploadPlaceholder, imagesInput, previewContainer, countElement, type) {
            let selectedFiles = [];
            
            // 点击加号打开文件选择
            uploadPlaceholder.addEventListener('click', function() {
                imagesInput.click();
            });
            
            // 文件选择变化事件
            imagesInput.addEventListener('change', function(e) {
                const files = Array.from(e.target.files);
                
                // 验证文件
                const validation = validateFiles(files);
                if (!validation.valid) {
                    showGlobalTip(validation.message, 'error');
                    imagesInput.value = '';
                    return;
                }
                
                // 计算还可以上传多少张图片
                const remainingSlots = uploadLimits.maxFiles - selectedFiles.length;
                const filesToAdd = files.slice(0, remainingSlots);
                
                if (filesToAdd.length < files.length) {
                    showGlobalTip(`最多只能上传${uploadLimits.maxFiles}张图片，已自动选择前${remainingSlots}张`, 'warning');
                }
                
                // 检查是否有大文件
                const hasLargeFile = filesToAdd.some(file => file.size > 1 * 1024 * 1024);
                if (hasLargeFile && type === 'post') {
                    document.getElementById('compressTip').style.display = 'flex';
                }
                
                // 添加到已选文件列表
                selectedFiles.push(...filesToAdd);
                
                // 更新文件输入（只保留最后3张）
                if (selectedFiles.length > uploadLimits.maxFiles) {
                    selectedFiles = selectedFiles.slice(-uploadLimits.maxFiles);
                }
                
                // 更新显示
                updateImagePreview(selectedFiles, previewContainer, countElement, uploadLimits.maxFiles);
                updateFileInput(imagesInput, selectedFiles);
            });
            
            // 更新图片预览
            function updateImagePreview(files, container, countElement, maxFiles) {
                container.innerHTML = '';
                
                files.forEach((file, index) => {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        const previewItem = document.createElement('div');
                        previewItem.className = 'image-preview-item';
                        
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'preview-image';
                        img.alt = '预览图片';
                        
                        const removeBtn = document.createElement('div');
                        removeBtn.className = 'remove-image';
                        removeBtn.innerHTML = '×';
                        removeBtn.title = '移除图片';
                        
                        removeBtn.addEventListener('click', function(e) {
                            e.stopPropagation();
                            selectedFiles.splice(index, 1);
                            updateImagePreview(selectedFiles, container, countElement, maxFiles);
                            updateFileInput(imagesInput, selectedFiles);
                            
                            if (type === 'post') {
                                const stillHasLargeFile = selectedFiles.some(file => file.size > 1 * 1024 * 1024);
                                if (!stillHasLargeFile) {
                                    document.getElementById('compressTip').style.display = 'none';
                                }
                            }
                        });
                        
                        previewItem.appendChild(img);
                        previewItem.appendChild(removeBtn);
                        container.appendChild(previewItem);
                    };
                    
                    reader.readAsDataURL(file);
                });
                
                // 更新计数
                countElement.textContent = `${selectedFiles.length}/${maxFiles} 张图片`;
                
                // 如果已选满，隐藏上传按钮
                if (selectedFiles.length >= maxFiles) {
                    uploadPlaceholder.style.display = 'none';
                } else {
                    uploadPlaceholder.style.display = 'flex';
                }
            }
            
            // 更新文件输入
            function updateFileInput(inputElement, files) {
                const dataTransfer = new DataTransfer();
                files.forEach(file => dataTransfer.items.add(file));
                inputElement.files = dataTransfer.files;
            }
        }
        
        // 验证文件函数
        function validateFiles(files) {
            let totalSize = 0;
            
            // 检查文件数量
            if (files.length > uploadLimits.maxFiles) {
                return {
                    valid: false,
                    message: `最多只能选择${uploadLimits.maxFiles}张图片`
                };
            }
            
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                
                // 检查文件类型
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
                if (!allowedTypes.includes(file.type)) {
                    return {
                        valid: false,
                        message: `文件 "${file.name}" 不是支持的图片格式。支持格式：JPG, PNG, GIF, WEBP, BMP`
                    };
                }
                
                // 检查文件大小
                if (file.size > uploadLimits.maxSingleSize) {
                    const sizeInMB = (file.size / (1024 * 1024)).toFixed(2);
                    return {
                        valid: false,
                        message: `图片 "${file.name}" 过大 (${sizeInMB}MB)！每张图片不能超过3MB`
                    };
                }
                
                totalSize += file.size;
            }
            
            // 检查总文件大小
            if (totalSize > uploadLimits.maxTotalSize) {
                const totalSizeInMB = (totalSize / (1024 * 1024)).toFixed(2);
                return {
                    valid: false,
                    message: `所有图片总大小(${totalSizeInMB}MB)超过6MB限制，请减少图片数量或压缩图片`
                };
            }
            
            return { valid: true };
        }
        
        // 初始化图片查看器
        function initImageViewer() {
            const imageViewerClose = document.getElementById('imageViewerClose');
            const prevImageBtn = document.getElementById('prevImageBtn');
            const nextImageBtn = document.getElementById('nextImageBtn');
            const viewerImage = document.getElementById('viewerImage');
            const imageInfo = document.getElementById('imageInfo');
            const imageViewerHint = document.getElementById('imageViewerHint');
            
            // 关闭查看器
            imageViewerClose.addEventListener('click', function() {
                hideModal('imageViewerModal');
            });
            
            // 双击关闭
            viewerImage.addEventListener('dblclick', function() {
                hideModal('imageViewerModal');
            });
            
            // 上一张图片
            prevImageBtn.addEventListener('click', function() {
                if (currentViewerImages.length > 0) {
                    currentViewerIndex = (currentViewerIndex - 1 + currentViewerImages.length) % currentViewerImages.length;
                    updateViewerImage();
                }
            });
            
            // 下一张图片
            nextImageBtn.addEventListener('click', function() {
                if (currentViewerImages.length > 0) {
                    currentViewerIndex = (currentViewerIndex + 1) % currentViewerImages.length;
                    updateViewerImage();
                }
            });
            
            // 触摸滑动切换图片
            let touchStartX = 0;
            let touchEndX = 0;
            
            viewerImage.addEventListener('touchstart', function(e) {
                touchStartX = e.changedTouches[0].screenX;
            });
            
            viewerImage.addEventListener('touchend', function(e) {
                touchEndX = e.changedTouches[0].screenX;
                handleSwipe();
            });
            
            function handleSwipe() {
                const threshold = 50;
                const diff = touchEndX - touchStartX;
                
                if (Math.abs(diff) > threshold) {
                    if (diff > 0) {
                        // 向右滑动，显示上一张
                        prevImageBtn.click();
                    } else {
                        // 向左滑动，显示下一张
                        nextImageBtn.click();
                    }
                }
            }
            
            // 键盘导航
            document.addEventListener('keydown', function(e) {
                const viewerModal = document.getElementById('imageViewerModal');
                if (viewerModal.classList.contains('show')) {
                    if (e.key === 'Escape') {
                        hideModal('imageViewerModal');
                    } else if (e.key === 'ArrowLeft') {
                        prevImageBtn.click();
                    } else if (e.key === 'ArrowRight') {
                        nextImageBtn.click();
                    }
                }
            });
            
            // 更新查看器图片
            function updateViewerImage() {
                if (currentViewerImages.length > 0 && currentViewerIndex >= 0 && currentViewerIndex < currentViewerImages.length) {
                    viewerImage.src = currentViewerImages[currentViewerIndex];
                    imageInfo.textContent = `${currentViewerIndex + 1} / ${currentViewerImages.length}`;
                    
                    // 更新提示
                    if (currentViewerImages.length > 1) {
                        imageViewerHint.textContent = '提示：双击图片关闭 | 左右滑动切换图片';
                    } else {
                        imageViewerHint.textContent = '提示：双击图片关闭';
                    }
                }
            }
        }
        
        // 打开图片查看器
        function openImageViewer(images, startIndex = 0) {
            if (!images || images.length === 0) return;
            
            currentViewerImages = images;
            currentViewerIndex = startIndex;
            
            const viewerImage = document.getElementById('viewerImage');
            const imageInfo = document.getElementById('imageInfo');
            const prevImageBtn = document.getElementById('prevImageBtn');
            const nextImageBtn = document.getElementById('nextImageBtn');
            const imageViewerHint = document.getElementById('imageViewerHint');
            
            // 更新图片和信息
            viewerImage.src = currentViewerImages[currentViewerIndex];
            imageInfo.textContent = `${currentViewerIndex + 1} / ${currentViewerImages.length}`;
            
            // 显示/隐藏导航按钮
            if (currentViewerImages.length <= 1) {
                prevImageBtn.style.display = 'none';
                nextImageBtn.style.display = 'none';
                imageViewerHint.textContent = '提示：双击图片关闭';
            } else {
                prevImageBtn.style.display = 'flex';
                nextImageBtn.style.display = 'flex';
                imageViewerHint.textContent = '提示：双击图片关闭 | 左右滑动切换图片';
            }
            
            // 显示模态框
            showModal('imageViewerModal');
        }

        // 检查上次登录ID
        function checkLastLoginId() {
            const lastLoginId = localStorage.getItem('lastLoginId');
            const loginIdInput = document.getElementById('loginId');
            const idMemoryTip = document.getElementById('idMemoryTip');
            
            if (lastLoginId && !userInfo && loginIdInput) {
                loginIdInput.value = lastLoginId;
                loginIdInput.classList.add('highlight-id');
                
                if (idMemoryTip) {
                    idMemoryTip.innerHTML = `<i class="fas fa-history" style="color: #4a90e2;"></i> 检测到您上次登录的ID：${lastLoginId}`;
                    idMemoryTip.style.color = '#4a90e2';
                    idMemoryTip.style.fontWeight = 'bold';
                }
                
                setTimeout(() => {
                    loginIdInput.classList.remove('highlight-id');
                }, 3000);
            }
        }

        // 初始化用户信息
        function initUserInfo() {
            const userAvatar = document.getElementById('userAvatar');
            const panelPname = document.getElementById('panelPname');
            const panelGender = document.getElementById('panelGender');
            const panelId = document.getElementById('panelId');
            const bottomLoginPrompt = document.getElementById('bottomLoginPrompt');
            const postDetailAvatar = document.getElementById('postDetailAvatar');

            if (userInfo) {
                userAvatar.src = userInfo.portrait || 'default-avatar.png';
                userAvatar.style.display = 'block';
                panelPname.textContent = userInfo.pname;
                panelGender.textContent = userInfo.gender;
                panelId.textContent = userInfo.id;
                bottomLoginPrompt.style.display = 'none';
                
                // 设置帖子详情头像
                postDetailAvatar.src = userInfo.portrait || 'default-avatar.png';
                postDetailAvatar.style.display = 'block';
            } else {
                userAvatar.style.display = 'none';
                bottomLoginPrompt.style.display = 'flex';
                postDetailAvatar.style.display = 'none';
            }

            // 头像点击显示/隐藏用户信息面板
            userAvatar.addEventListener('click', function(e) {
                e.stopPropagation();
                const panel = document.getElementById('userInfoPanel');
                panel.classList.toggle('show');
            });
            
            // 帖子详情头像点击显示/隐藏用户信息面板
            postDetailAvatar.addEventListener('click', function(e) {
                e.stopPropagation();
                const panel = document.getElementById('userInfoPanel');
                panel.classList.toggle('show');
            });

            // 点击页面其他区域隐藏用户信息面板
            document.addEventListener('click', function() {
                const panel = document.getElementById('userInfoPanel');
                panel.classList.remove('show');
            });

            // 退出登录
            document.getElementById('logoutBtn').addEventListener('click', function() {
                localStorage.removeItem('userInfo');
                localStorage.removeItem('lastLoginId');
                location.reload();
            });
            
            // 复制ID按钮事件
            document.getElementById('copyPanelIdBtn')?.addEventListener('click', function() {
                const id = document.getElementById('panelId').textContent;
                copyToClipboard(id);
                showGlobalTip('ID已复制到剪贴板', 'success');
            });
            
            // 底部登录提示点击事件
            bottomLoginPrompt.addEventListener('click', function() {
                showModal('loginModal');
            });
        }

        // 初始化轮播图
        function initCarousel() {
            const carousel = document.getElementById('carousel');
            const carouselItems = carousel.querySelectorAll('.carousel-item');
            const indicators = document.getElementById('carouselIndicators').querySelectorAll('.indicator-dot');
            const itemCount = carouselItems.length;

            if (itemCount === 0) return;

            // 自动轮播
            startCarouselTimer();

            // 鼠标悬停时停止轮播，离开时继续
            document.getElementById('section2').addEventListener('mouseenter', stopCarouselTimer);
            document.getElementById('section2').addEventListener('mouseleave', startCarouselTimer);

            // 小圆点点击
            indicators.forEach((dot, index) => {
                dot.addEventListener('click', function() {
                    currentCarouselIndex = index;
                    updateCarousel();
                });
            });

            // 更新轮播图
            function updateCarousel() {
                carousel.style.transform = `translateX(-${currentCarouselIndex * 100}%)`;
                indicators.forEach((dot, index) => {
                    dot.classList.toggle('active', index === currentCarouselIndex);
                });
            }

            // 启动轮播定时器
            function startCarouselTimer() {
                if (carouselTimer) clearInterval(carouselTimer);
                carouselTimer = setInterval(() => {
                    currentCarouselIndex = (currentCarouselIndex + 1) % itemCount;
                    updateCarousel();
                }, 3000);
            }

            // 停止轮播定时器
            function stopCarouselTimer() {
                clearInterval(carouselTimer);
                carouselTimer = null;
            }
        }

        // 初始化搜索功能
        function initSearch() {
            const searchBox = document.getElementById('postSearch');
            
            searchBox.addEventListener('input', function() {
                performSearch(this.value.trim());
            });
        }
        
        // 执行搜索
        function performSearch(keyword) {
            if (!postsLoaded) return;
            
            const postCards = document.querySelectorAll('.post-card');
            const keywordLower = keyword.toLowerCase();
            
            if (keywordLower === '') {
                // 显示所有帖子
                postCards.forEach(card => {
                    card.style.display = 'block';
                });
                return;
            }
            
            let visibleCount = 0;
            
            postCards.forEach(card => {
                const content = card.querySelector('.post-content').textContent.toLowerCase();
                const comments = card.querySelectorAll('.comment-content');
                let commentText = '';
                comments.forEach(comment => {
                    commentText += comment.textContent.toLowerCase() + ' ';
                });
                
                const allText = content + ' ' + commentText;
                if (allText.includes(keywordLower)) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            // 如果没有匹配的帖子，显示提示
            if (visibleCount === 0 && keywordLower !== '') {
                showGlobalTip(`未找到包含"${keyword}"的帖子`, 'info');
            }
        }

        // 初始化模态框事件
        function initModalEvents() {
            // 登录模态框关闭
            document.getElementById('loginModalClose').addEventListener('click', function() {
                hideModal('loginModal');
                document.getElementById('loginTip').textContent = '';
                document.getElementById('loginId').value = '';
                document.getElementById('loginPwd').value = '';
                document.getElementById('loginSubmitBtn').disabled = false;
                document.getElementById('loginSubmitBtn').textContent = '登录';
                document.getElementById('loginSuccessId').style.display = 'none';
                
                document.getElementById('loginId').style.display = 'block';
                document.getElementById('loginPwd').style.display = 'block';
                document.getElementById('loginSubmitBtn').style.display = 'block';
                document.getElementById('registerBtn').style.display = 'flex';
                
                submitLock.login = false;
            });

            // 发帖模态框关闭
            document.getElementById('postModalClose').addEventListener('click', function() {
                hideModal('postModal');
                document.getElementById('postTip').textContent = '';
                document.getElementById('postContent').value = '';
                document.getElementById('postSubmitBtn').disabled = false;
                document.getElementById('postSubmitBtn').textContent = '发布';
                
                const postImagePreview = document.getElementById('postImagePreview');
                const postImageCount = document.getElementById('postImageCount');
                const postUploadPlaceholder = document.getElementById('postUploadPlaceholder');
                const postImagesInput = document.getElementById('postImages');
                
                postImagePreview.innerHTML = '';
                postImageCount.textContent = '0/3 张图片';
                postUploadPlaceholder.style.display = 'flex';
                postImagesInput.value = '';
                
                document.getElementById('postUploadProgress').style.display = 'none';
                document.getElementById('postUploadProgressBar').style.width = '0%';
                
                document.getElementById('compressTip').style.display = 'none';
                
                // 重置设备选择
                document.getElementById('postDeviceOptions').classList.remove('expanded');
                document.getElementById('postDeviceHeader').classList.remove('expanded');
                
                submitLock.post = false;
            });

            // 评论模态框关闭
            document.getElementById('commentModalClose').addEventListener('click', function() {
                hideModal('commentModal');
                document.getElementById('commentTip').textContent = '';
                document.getElementById('commentContent').value = '';
                document.getElementById('commentSubmitBtn').disabled = false;
                document.getElementById('commentSubmitBtn').textContent = '发布评论';
                
                const commentImagePreview = document.getElementById('commentImagePreview');
                const commentImageCount = document.getElementById('commentImageCount');
                const commentUploadPlaceholder = document.getElementById('commentUploadPlaceholder');
                const commentImagesInput = document.getElementById('commentImages');
                
                commentImagePreview.innerHTML = '';
                commentImageCount.textContent = '0/3 张图片';
                commentUploadPlaceholder.style.display = 'flex';
                commentImagesInput.value = '';
                
                document.getElementById('commentUploadProgress').style.display = 'none';
                document.getElementById('commentUploadProgressBar').style.width = '0%';
                
                // 重置设备选择
                document.getElementById('commentDeviceOptions').classList.remove('expanded');
                document.getElementById('commentDeviceHeader').classList.remove('expanded');
                
                submitLock.comment = false;
            });

            // 帖子详情模态框关闭（点击遮罩层）
            document.getElementById('postDetailModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    hideModal('postDetailModal');
                }
            });

            // 发帖建议标签点击事件
            const suggestionTags = document.querySelectorAll('.suggestion-tag');
            suggestionTags.forEach(tag => {
                tag.addEventListener('click', function() {
                    const tagText = this.getAttribute('data-tag');
                    const postContent = document.getElementById('postContent');
                    const currentValue = postContent.value;
                    
                    if (currentValue.includes(tagText)) {
                        // 如果已经包含该标签，则移除
                        postContent.value = currentValue.replace(tagText + ' ', '').replace(' ' + tagText, '').replace(tagText, '');
                    } else {
                        // 否则添加标签
                        postContent.value = currentValue + (currentValue ? ' ' : '') + tagText;
                    }
                    
                    // 聚焦到输入框
                    postContent.focus();
                });
            });

            // 登录表单提交
            document.getElementById('loginForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (submitLock.login) {
                    showGlobalTip('正在登录中，请稍候...', 'info');
                    return;
                }
                
                const id = document.getElementById('loginId').value.trim();
                const password = document.getElementById('loginPwd').value.trim();
                const tip = document.getElementById('loginTip');
                const submitBtn = document.getElementById('loginSubmitBtn');

                if (!id || !password) {
                    tip.textContent = '账号和密码不能为空';
                    tip.classList.remove('success-tip');
                    return;
                }

                if (!/^\d{10}$/.test(id)) {
                    tip.textContent = 'ID必须是10位数字';
                    tip.classList.remove('success-tip');
                    return;
                }

                submitLock.login = true;
                submitBtn.disabled = true;
                submitBtn.textContent = '登录中...';
                submitBtn.innerHTML = '<span class="spinner"></span> 登录中';

                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'login-service.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.timeout = 15000;
                
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        setTimeout(() => {
                            submitLock.login = false;
                        }, 2000);
                        
                        if (xhr.status === 200) {
                            try {
                                const res = JSON.parse(xhr.responseText);
                                if (res.status === 'success') {
                                    document.getElementById('displayUserId').textContent = id;
                                    document.getElementById('loginSuccessId').style.display = 'block';
                                    
                                    document.getElementById('loginId').style.display = 'none';
                                    document.getElementById('loginPwd').style.display = 'none';
                                    document.getElementById('idMemoryTip').style.display = 'none';
                                    document.getElementById('loginSubmitBtn').style.display = 'none';
                                    document.getElementById('registerBtn').style.display = 'none';
                                    
                                    tip.textContent = '';
                                    tip.classList.add('success-tip');
                                    
                                    localStorage.setItem('userInfo', JSON.stringify(res.data));
                                    localStorage.setItem('lastLoginId', id);
                                    
                                    showGlobalTip(`登录成功！您的ID是：${id}，请牢记`, 'success');
                                    
                                    setTimeout(() => {
                                        location.reload();
                                    }, 3000);
                                } else {
                                    submitBtn.disabled = false;
                                    submitBtn.textContent = '登录';
                                    submitBtn.innerHTML = '登录';
                                    tip.textContent = res.msg;
                                    tip.classList.remove('success-tip');
                                }
                            } catch (e) {
                                submitBtn.disabled = false;
                                submitBtn.textContent = '登录';
                                submitBtn.innerHTML = '登录';
                                tip.textContent = '服务器响应异常，请稍后重试';
                                tip.classList.remove('success-tip');
                            }
                        } else {
                            submitBtn.disabled = false;
                            submitBtn.textContent = '登录';
                            submitBtn.innerHTML = '登录';
                            tip.textContent = '网络错误，请稍后重试';
                            tip.classList.remove('success-tip');
                        }
                    }
                };
                
                xhr.ontimeout = function() {
                    submitLock.login = false;
                    submitBtn.disabled = false;
                    submitBtn.textContent = '登录';
                    submitBtn.innerHTML = '登录';
                    tip.textContent = '网络超时，请稍后重试';
                    tip.classList.remove('success-tip');
                };
                
                xhr.onerror = function() {
                    submitLock.login = false;
                    submitBtn.disabled = false;
                    submitBtn.textContent = '登录';
                    submitBtn.innerHTML = '登录';
                    tip.textContent = '网络连接失败，请检查网络';
                    tip.classList.remove('success-tip');
                };
                
                xhr.send(`action=login&id=${encodeURIComponent(id)}&password=${encodeURIComponent(password)}`);
            });

            // 复制ID功能
            document.getElementById('copyIdBtn')?.addEventListener('click', function() {
                const id = document.getElementById('displayUserId').textContent;
                copyToClipboard(id);
                showGlobalTip('ID已复制到剪贴板', 'success');
            });

            // 发帖表单提交
            document.getElementById('postForm').addEventListener('submit', function(e) {
                e.preventDefault();
                if (submitLock.post) {
                    showGlobalTip('正在发布中，请稍候...', 'info');
                    return;
                }
                
                const content = document.getElementById('postContent').value.trim();
                const pname = document.getElementById('postPname').value;
                const portrait = document.getElementById('postPortrait').value;
                const device = document.getElementById('postDevice').value;
                const deviceName = document.getElementById('postDeviceNameValue').value;
                const tip = document.getElementById('postTip');
                const submitBtn = document.getElementById('postSubmitBtn');
                const postImagesInput = document.getElementById('postImages');

                if (!content) {
                    tip.textContent = '帖子内容不能为空';
                    tip.classList.remove('success-tip');
                    return;
                }

                if (postImagesInput.files.length > uploadLimits.maxFiles) {
                    tip.textContent = `最多只能上传${uploadLimits.maxFiles}张图片`;
                    tip.classList.remove('success-tip');
                    return;
                }

                if (postImagesInput.files.length > 0) {
                    const validation = validateFiles(Array.from(postImagesInput.files));
                    if (!validation.valid) {
                        tip.textContent = validation.message;
                        tip.classList.remove('success-tip');
                        return;
                    }
                }

                submitLock.post = true;
                submitBtn.disabled = true;
                submitBtn.textContent = '发布中...';
                submitBtn.innerHTML = '<span class="spinner"></span> 发布中';

                const progressBar = document.getElementById('postUploadProgress');
                const progressBarInner = document.getElementById('postUploadProgressBar');
                progressBar.style.display = 'block';
                progressBarInner.style.width = '0%';

                const formData = new FormData();
                formData.append('action', 'publish_post');
                formData.append('content', content);
                formData.append('pname', pname);
                formData.append('portrait', portrait);
                formData.append('device', device);
                formData.append('device_name', deviceName);
                
                const postImages = document.getElementById('postImages').files;
                for (let i = 0; i < postImages.length; i++) {
                    formData.append('post_images[]', postImages[i]);
                }

                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'login-service.php', true);
                xhr.timeout = 60000;
                
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percent = Math.round((e.loaded / e.total) * 100);
                        progressBarInner.style.width = percent + '%';
                        
                        if (percent < 100) {
                            submitBtn.innerHTML = `<span class="spinner"></span> 上传中 ${percent}%`;
                        }
                    }
                });
                
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        setTimeout(() => {
                            submitLock.post = false;
                        }, 2000);
                        
                        if (xhr.status === 200) {
                            try {
                                const res = JSON.parse(xhr.responseText);
                                tip.textContent = res.msg;
                                if (res.status === 'success') {
                                    tip.classList.add('success-tip');
                                    showGlobalTip('发布成功！', 'success');
                                    
                                    setTimeout(() => {
                                        location.reload();
                                    }, 1500);
                                } else {
                                    tip.classList.remove('success-tip');
                                    let errorMsg = res.msg;
                                    if (res.msg.includes('size') || res.msg.includes('大') || res.msg.includes('MB')) {
                                        errorMsg += '。建议：1.压缩图片 2.减少图片数量 3.单张图片不超过3MB';
                                    }
                                    showGlobalTip(errorMsg, 'error');
                                    submitBtn.disabled = false;
                                    submitBtn.textContent = '发布';
                                    submitBtn.innerHTML = '发布';
                                }
                            } catch (e) {
                                tip.textContent = '服务器响应异常，请稍后重试';
                                tip.classList.remove('success-tip');
                                showGlobalTip('服务器响应异常，请稍后重试', 'error');
                                submitBtn.disabled = false;
                                submitBtn.textContent = '发布';
                                submitBtn.innerHTML = '发布';
                            }
                        } else {
                            let errorMsg = '网络错误，请稍后重试';
                            if (xhr.status === 413) {
                                errorMsg = '文件太大！服务器拒绝了上传请求。建议压缩图片或减少数量';
                            } else if (xhr.status === 0) {
                                errorMsg = '网络连接失败或请求超时。请检查网络连接后重试';
                            } else if (xhr.status === 500) {
                                errorMsg = '服务器内部错误，请稍后重试';
                            }
                            
                            tip.textContent = errorMsg;
                            tip.classList.remove('success-tip');
                            showGlobalTip(errorMsg, 'error');
                            submitBtn.disabled = false;
                            submitBtn.textContent = '发布';
                            submitBtn.innerHTML = '发布';
                        }
                        
                        progressBar.style.display = 'none';
                    }
                };
                
                xhr.ontimeout = function() {
                    submitLock.post = false;
                    submitBtn.disabled = false;
                    submitBtn.textContent = '发布';
                    submitBtn.innerHTML = '发布';
                    progressBar.style.display = 'none';
                    showGlobalTip('上传超时（60秒），可能是图片太大或网络慢。建议压缩图片再试', 'error');
                };
                
                xhr.onerror = function() {
                    submitLock.post = false;
                    submitBtn.disabled = false;
                    submitBtn.textContent = '发布';
                    submitBtn.innerHTML = '发布';
                    progressBar.style.display = 'none';
                    showGlobalTip('网络错误，可能是图片太大导致。建议：1.压缩图片 2.使用WiFi网络 3.分多次上传', 'error');
                };
                
                xhr.send(formData);
            });

            // 评论表单提交
            document.getElementById('commentForm').addEventListener('submit', function(e) {
                e.preventDefault();
                if (submitLock.comment) {
                    showGlobalTip('正在评论中，请稍候...', 'info');
                    return;
                }
                
                const comContent = document.getElementById('commentContent').value.trim();
                const pid = document.getElementById('commentPid').value;
                const pname = document.getElementById('commentPname').value;
                const portrait = document.getElementById('commentPortrait').value;
                const device = document.getElementById('commentDevice').value;
                const deviceName = document.getElementById('commentDeviceNameValue').value;
                const tip = document.getElementById('commentTip');
                const submitBtn = document.getElementById('commentSubmitBtn');
                const commentImagesInput = document.getElementById('commentImages');

                if (!comContent || !pid) {
                    tip.textContent = '评论内容不能为空';
                    tip.classList.remove('success-tip');
                    return;
                }

                if (commentImagesInput.files.length > uploadLimits.maxFiles) {
                    tip.textContent = `最多只能上传${uploadLimits.maxFiles}张图片`;
                    tip.classList.remove('success-tip');
                    return;
                }

                if (commentImagesInput.files.length > 0) {
                    const validation = validateFiles(Array.from(commentImagesInput.files));
                    if (!validation.valid) {
                        tip.textContent = validation.message;
                        tip.classList.remove('success-tip');
                        return;
                    }
                }

                submitLock.comment = true;
                submitBtn.disabled = true;
                submitBtn.textContent = '发布中...';
                submitBtn.innerHTML = '<span class="spinner"></span> 发布中';

                const progressBar = document.getElementById('commentUploadProgress');
                const progressBarInner = document.getElementById('commentUploadProgressBar');
                progressBar.style.display = 'block';
                progressBarInner.style.width = '0%';

                const formData = new FormData();
                formData.append('action', 'publish_comment');
                formData.append('com_content', comContent);
                formData.append('pid', pid);
                formData.append('pname', pname);
                formData.append('portrait', portrait);
                formData.append('device', device);
                formData.append('device_name', deviceName);
                
                const commentImages = document.getElementById('commentImages').files;
                for (let i = 0; i < commentImages.length; i++) {
                    formData.append('comment_images[]', commentImages[i]);
                }

                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'login-service.php', true);
                xhr.timeout = 60000;
                
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percent = Math.round((e.loaded / e.total) * 100);
                        progressBarInner.style.width = percent + '%';
                        
                        if (percent < 100) {
                            submitBtn.innerHTML = `<span class="spinner"></span> 上传中 ${percent}%`;
                        }
                    }
                });
                
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        setTimeout(() => {
                            submitLock.comment = false;
                        }, 2000);
                        
                        if (xhr.status === 200) {
                            try {
                                const res = JSON.parse(xhr.responseText);
                                tip.textContent = res.msg;
                                if (res.status === 'success') {
                                    tip.classList.add('success-tip');
                                    showGlobalTip('评论成功！', 'success');
                                    setTimeout(() => {
                                        location.reload();
                                    }, 1000);
                                } else {
                                    tip.classList.remove('success-tip');
                                    showGlobalTip(res.msg, 'error');
                                    submitBtn.disabled = false;
                                    submitBtn.textContent = '发布评论';
                                    submitBtn.innerHTML = '发布评论';
                                }
                            } catch (e) {
                                tip.textContent = '服务器响应异常，请稍后重试';
                                tip.classList.remove('success-tip');
                                showGlobalTip('服务器响应异常，请稍后重试', 'error');
                                submitBtn.disabled = false;
                                submitBtn.textContent = '发布评论';
                                submitBtn.innerHTML = '发布评论';
                            }
                        } else {
                            let errorMsg = '网络错误，请稍后重试';
                            if (xhr.status === 413) {
                                errorMsg = '评论图片太大！服务器拒绝了上传请求';
                            }
                            
                            tip.textContent = errorMsg;
                            tip.classList.remove('success-tip');
                            showGlobalTip(errorMsg, 'error');
                            submitBtn.disabled = false;
                            submitBtn.textContent = '发布评论';
                            submitBtn.innerHTML = '发布评论';
                        }
                        
                        progressBar.style.display = 'none';
                    }
                };
                
                xhr.ontimeout = function() {
                    submitLock.comment = false;
                    submitBtn.disabled = false;
                    submitBtn.textContent = '发布评论';
                    submitBtn.innerHTML = '发布评论';
                    progressBar.style.display = 'none';
                    showGlobalTip('评论上传超时，请稍后重试', 'error');
                };
                
                xhr.onerror = function() {
                    submitLock.comment = false;
                    submitBtn.disabled = false;
                    submitBtn.textContent = '发布评论';
                    submitBtn.innerHTML = '发布评论';
                    progressBar.style.display = 'none';
                    showGlobalTip('网络错误，请稍后重试', 'error');
                };
                
                xhr.send(formData);
            });
            
            // 注册按钮防重复点击
            document.getElementById('registerBtn')?.addEventListener('click', function(e) {
                if (window.location.href.includes('register.php')) return;
                
                if (submitLock.register) {
                    e.preventDefault();
                    showGlobalTip('正在跳转中，请稍候...', 'info');
                    return;
                }
                
                submitLock.register = true;
                const originalText = this.innerHTML;
                this.innerHTML = '<span class="spinner"></span> 跳转中...';
                this.style.opacity = '0.7';
                
                setTimeout(() => {
                    submitLock.register = false;
                    this.innerHTML = originalText;
                    this.style.opacity = '1';
                }, 3000);
            });
        }

        // 复制到剪贴板函数
        function copyToClipboard(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            
            try {
                document.execCommand('copy');
                return true;
            } catch (err) {
                console.error('复制失败:', err);
                return false;
            } finally {
                document.body.removeChild(textarea);
            }
        }

        // 初始化标签切换事件
        function initTabEvents() {
            const latestTab = document.getElementById('latestTab');
            const loveTab = document.getElementById('loveTab');
            const gossipTab = document.getElementById('gossipTab');
            const refreshBtn = document.getElementById('refreshBtn');
            const currentTabText = document.getElementById('currentTab');
            
            // 最新标签点击
            latestTab.addEventListener('click', function() {
                if (currentTab === 'latest') {
                    refreshPosts();
                    return;
                }
                
                switchTab('latest');
            });
            
            // 表白区标签点击
            loveTab.addEventListener('click', function() {
                if (currentTab === 'love') {
                    refreshPosts();
                    return;
                }
                
                switchTab('love');
            });
            
            // 八卦区标签点击
            gossipTab.addEventListener('click', function() {
                if (currentTab === 'gossip') {
                    refreshPosts();
                    return;
                }
                
                switchTab('gossip');
            });
            
            refreshBtn.addEventListener('click', function() {
                refreshPosts();
            });
            
            function switchTab(newTab) {
                currentTab = newTab;
                updateTabUI();
                loadPosts(1, postsPerPage, currentTab, true);
            }
            
            function updateTabUI() {
                latestTab.classList.toggle('active', currentTab === 'latest');
                loveTab.classList.toggle('active', currentTab === 'love');
                gossipTab.classList.toggle('active', currentTab === 'gossip');
                currentTabText.textContent = currentTab === 'latest' ? '最新' : 
                                            currentTab === 'love' ? '表白区' : '八卦区';
            }
            
            function refreshPosts() {
                const now = Date.now();
                if (now - lastRefreshTime < 3000) {
                    showGlobalTip('刷新太频繁了，请稍后再试', 'info');
                    return;
                }
                
                lastRefreshTime = now;
                loadPosts(1, postsPerPage, currentTab, true);
                showGlobalTip('刷新成功', 'success');
            }
        }

        // 初始化按钮事件
        function initButtonEvents() {
            // 发帖按钮
            document.getElementById('publishPostBtn').addEventListener('click', function() {
                if (!userInfo) {
                    showModal('loginModal');
                    return;
                }
                document.getElementById('postPname').value = userInfo.pname;
                document.getElementById('postPortrait').value = userInfo.portrait || '';
                showModal('postModal');
            });

            // 置顶按钮
            document.getElementById('topIcon').addEventListener('click', function() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
        }
        
        // 点赞点击处理
        function handleLikeClick() {
            if (!userInfo) {
                showModal('loginModal');
                return;
            }
            
            if (submitLock.like) {
                showGlobalTip('正在点赞中，请稍候...', 'info');
                return;
            }
            
            const type = this.getAttribute('data-type');
            const id = this.getAttribute('data-id');
            const heartIcon = this.querySelector('.fa-heart');
            const countSpan = this.querySelector('.like-count, .comment-like-count');
            let currentCount = parseInt(countSpan.textContent) || 0;
            
            // 先更新前端状态
            if (heartIcon.classList.contains('far')) {
                heartIcon.classList.remove('far');
                heartIcon.classList.add('fas');
                countSpan.textContent = currentCount + 1;
            } else {
                heartIcon.classList.remove('fas');
                heartIcon.classList.add('far');
                countSpan.textContent = currentCount - 1;
            }
            
            submitLock.like = true;
            
            // 添加动画效果
            this.classList.add('liked');
            setTimeout(() => this.classList.remove('liked'), 500);
            
            // 发送点赞请求
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'login-service.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.timeout = 10000;
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    submitLock.like = false;
                    
                    if (xhr.status === 200) {
                        const res = JSON.parse(xhr.responseText);
                        if (res.status !== 'success') {
                            // 如果请求失败，回退状态
                            if (heartIcon.classList.contains('fas')) {
                                heartIcon.classList.remove('fas');
                                heartIcon.classList.add('far');
                                countSpan.textContent = currentCount;
                            } else {
                                heartIcon.classList.remove('far');
                                heartIcon.classList.add('fas');
                                countSpan.textContent = currentCount;
                            }
                            showGlobalTip(res.msg, 'error');
                        } else {
                            showGlobalTip('点赞成功！', 'success');
                        }
                    } else {
                        // 网络错误，回退状态
                        if (heartIcon.classList.contains('fas')) {
                            heartIcon.classList.remove('fas');
                            heartIcon.classList.add('far');
                            countSpan.textContent = currentCount;
                        } else {
                            heartIcon.classList.remove('far');
                            heartIcon.classList.add('fas');
                            countSpan.textContent = currentCount;
                        }
                        showGlobalTip('网络错误，请稍后重试', 'error');
                    }
                }
            };
            
            xhr.ontimeout = function() {
                submitLock.like = false;
                if (heartIcon.classList.contains('fas')) {
                    heartIcon.classList.remove('fas');
                    heartIcon.classList.add('far');
                    countSpan.textContent = currentCount;
                } else {
                    heartIcon.classList.remove('far');
                    heartIcon.classList.add('fas');
                    countSpan.textContent = currentCount;
                }
                showGlobalTip('点赞超时，请稍后重试', 'error');
            };
            
            xhr.send(`action=like&type=${encodeURIComponent(type)}&id=${encodeURIComponent(id)}`);
        }
        
        // 显示全局提示
        function showGlobalTip(message, type = 'info') {
            const tip = document.getElementById('globalTip');
            tip.textContent = message;
            tip.className = 'global-tip ' + type;
            tip.classList.add('show');
            
            let duration = 3000;
            if (type === 'success') duration = 4000;
            if (type === 'error') duration = 5000;
            if (type === 'warning') duration = 4500;
            if (message.includes('ID')) duration = 5000;
            
            if (tip.timeoutId) clearTimeout(tip.timeoutId);
            
            tip.timeoutId = setTimeout(() => {
                tip.classList.remove('show');
            }, duration);
        }

        // 显示模态框
        function showModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.add('show');
        }

        // 隐藏模态框
        function hideModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('show');
        }
        
        // 处理链接点击
        function handleLinkClick(linkElement) {
            const href = linkElement.getAttribute('href');
            const fallback = linkElement.getAttribute('data-fallback');
            
            window.location.href = href;
            
            setTimeout(function() {
                if (window.location.href.indexOf(href) === -1 && fallback) {
                    window.location.href = fallback;
                }
            }, 3000);
            
            return false;
        }